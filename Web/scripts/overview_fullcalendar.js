// Prototype-only init for Web/overview_fullcalendar.php — evaluating FullCalendar Standard
// (Web/assets/vendor/fullcalendar/6.1.20, MIT licensed, no resourceTimeGrid/resourceTimeline/
// Scheduler code in the bundle) against the hand-rolled grid in Web/scripts/overview.js
// (untouched by this file). Read-only for this phase: no drag, resize, or same-screen create.
$(function () {
    var opts = window.overviewFullCalendarOpts;
    var rootEl = document.getElementById('fullcalendarRoot');
    if (!rootEl || !opts) {
        return;
    }

    // ---- Resource selection (toolbar left "資源選擇器") — one control now covers what
    // used to be three separate things: the top physical/video category tabs, AND the
    // sidebar's own duplicate resource checklist. Exactly one specific resource is always
    // selected (no "全部資源"/show-everything option — dropped per explicit request; this
    // page shows one room's calendar at a time, same as before, just picked from one unified
    // dropdown instead of category tabs + a sidebar checklist). categoryOf() is kept exactly
    // as it was: the reservation Drawer's OWN independent category state
    // (activeResourceCategory, further down) still needs it and must not be touched by
    // anything in this section. ----
    var RESOURCE_STORAGE_KEY = 'overview-fc-selected-resource';

    function categoryOf(resourceId) {
        var groups = opts.resourceGroups || { physical: [], video: [] };
        var isVideo = groups.video.some(function (r) { return String(r.id) === String(resourceId); });
        return isVideo ? 'video' : 'physical';
    }

    function allResourceIds() {
        var groups = opts.resourceGroups || { physical: [], video: [] };
        return (groups.physical || []).concat(groups.video || []).map(function (r) { return String(r.id); });
    }

    // Default room when there's no (valid) stored selection yet: whichever physical resource
    // is Shanghai's (resource names carry a flag emoji + English city name, e.g. "🇨🇳
    // Shanghai" — confirmed live against the actual resources table), falling back to the
    // first physical resource, then the first resource overall, so this never returns
    // nothing as long as any resource exists.
    function defaultShanghaiResourceId() {
        var groups = opts.resourceGroups || { physical: [], video: [] };
        var physical = groups.physical || [];
        var shanghai = physical.filter(function (r) {
            return /上海|shanghai/i.test(r.name);
        })[0];
        if (shanghai) {
            return String(shanghai.id);
        }
        var first = physical[0] || (groups.video && groups.video[0]);
        return first ? String(first.id) : null;
    }

    function loadStoredResourceSelection() {
        try {
            var stored = localStorage.getItem(RESOURCE_STORAGE_KEY);
            if (stored && allResourceIds().indexOf(stored) !== -1) {
                return stored;
            }
        } catch (e) {}
        return defaultShanghaiResourceId();
    }

    // selectedResourceId is the single source of truth for both the events() fetch filter
    // and the resource picker's own checkmark/label — never read back from the DOM. Restored
    // from localStorage on load (falling back to the Shanghai default if the stored id no
    // longer exists, e.g. the resource was deleted since), and persisted on every change so
    // the user's last choice survives a reload/revisit, per the "記住使用者最後選擇" requirement.
    var selectedResourceId = loadStoredResourceSelection();

    function updateResourcePickerTrigger() {
        var $items = $('#resourcePickerList .resource-picker-item');
        $items.removeClass('selected');
        $items.filter('[data-resource-id="' + selectedResourceId + '"]').addClass('selected');

        var $dot = $('#resourcePickerDot');
        var color = (opts.resourceColors || {})[selectedResourceId];
        // jQuery's .css() doesn't reliably round-trip custom properties (`--dot-color`) across
        // versions — set it via the native CSSStyleDeclaration API instead.
        $dot.toggle(!!color);
        $dot.get(0).style.setProperty('--dot-color', color || '');

        $('#resourcePickerLabel').text((opts.resourceNames || {})[selectedResourceId] || '');
    }

    function setSelectedResource(resourceId) {
        selectedResourceId = resourceId;
        try {
            localStorage.setItem(RESOURCE_STORAGE_KEY, resourceId);
        } catch (e) {}
        updateResourcePickerTrigger();
        if (typeof calendar !== 'undefined') {
            calendar.refetchEvents();
        }
    }

    // Registered against allDropdownClosers (declared further below, alongside the
    // quick-create modal's own dropdowns) so this panel closes along with every other
    // toolbar/modal dropdown on an outside click — initResourcePicker() itself isn't called
    // until after that array exists (see the bottom of this file), same ordering the
    // view-picker dropdown further down relies on.
    function initResourcePicker() {
        var $picker = $('#resourcePicker');
        var $trigger = $('#resourcePickerTrigger');
        var $panel = $('#resourcePickerPanel');
        var $search = $('#resourcePickerSearch');

        function open() {
            closeAllDropdowns();
            $picker.addClass('open');
            $trigger.attr('aria-expanded', 'true');
            $search.val('').trigger('input');
            setTimeout(function () { $search.trigger('focus'); }, 0);
        }
        function close() {
            $picker.removeClass('open');
            $trigger.attr('aria-expanded', 'false');
        }
        allDropdownClosers.push(close);

        $trigger.on('click', function (e) {
            e.stopPropagation();
            if ($picker.hasClass('open')) {
                close();
            } else {
                open();
            }
        });
        $panel.on('click', function (e) { e.stopPropagation(); });
        $panel.on('click', '.resource-picker-item', function () {
            setSelectedResource(String($(this).data('resource-id')));
            close();
        });

        $search.on('input', function () {
            var term = $(this).val().trim().toLowerCase();
            var anyVisible = { physical: false, video: false };
            $('#resourcePickerList .resource-picker-item[data-section]').each(function () {
                var label = $(this).find('.resource-picker-item-label').text().toLowerCase();
                var match = label.indexOf(term) !== -1;
                $(this).toggleClass('d-none', !match);
                if (match) {
                    anyVisible[$(this).data('section')] = true;
                }
            });
            $('#resourcePickerList .resource-picker-section-label').each(function () {
                $(this).toggleClass('d-none', !anyVisible[$(this).data('section')]);
            });
            $('#resourcePickerEmpty').toggleClass('d-none', anyVisible.physical || anyVisible.video);
        });

        updateResourcePickerTrigger();
    }

    // ---- Reservation scope (全體／只看我的) — a SEPARATE dimension from resource
    // selection. Independent of selectedResourceId: switching scope must not touch the
    // resource picker, the calendar's current date/view, or its scroll position — it only
    // changes whether the fetched events are further narrowed to this user's own
    // reservations (see the events() callback below, and
    // OverviewFullCalendarEventsPresenter::GetEvents()'s $mineOnly branch on the server).
    var mineOnly = false;

    $('#fcMineOnlyToggle').on('change', function () {
        mineOnly = this.checked;
        if (typeof calendar !== 'undefined') {
            calendar.refetchEvents();
        }
    });

    // ---- Default resource for quick-create actions (drag-select, sidebar "+新增預約") ----
    // Always the currently selected resource — there's exactly one at any time now, so this
    // no longer needs a fallback chain.
    function defaultResourceId() {
        return selectedResourceId;
    }

    function pad(n) {
        return n < 10 ? '0' + n : '' + n;
    }

    // arg.date (dayHeaderContent) and info.start/info.end (datesSet) are FullCalendar's
    // "fake-UTC" markers: with a named `timeZone` option, FullCalendar constructs these as
    // plain JS Date objects whose *UTC* getters already reflect the correct wall-clock
    // date in that zone — so getUTCDay()/getUTCDate()/etc. are what we want here, not the
    // browser's local getDay()/getDate().
    var WEEKDAY_LABELS = ['日', '一', '二', '三', '四', '五', '六'];

    function weekdayLabel(date) {
        return WEEKDAY_LABELS[date.getUTCDay()];
    }

    function isoUTC(date) {
        var y = date.getUTCFullYear();
        var m = String(date.getUTCMonth() + 1).padStart(2, '0');
        var d = String(date.getUTCDate()).padStart(2, '0');
        return y + '-' + m + '-' + d;
    }

    // Always a single "Y年n月" — never a "7月－8月"-style range. A week view's visible range
    // can span two months; rather than growing the label to cover both (which changed the
    // label's width/length depending on the exact week, making the whole centered toolbar
    // visibly shift as you navigate), this picks ONE month to report by looking at the
    // week's middle day (start + 3, i.e. Thursday for a Monday-start week) — whichever month
    // holds 4+ of the week's 7 days "owns" the label.
    function formatMonthLabel(viewType, start) {
        var reference = start;
        if (viewType === 'timeGridWeek') {
            reference = new Date(start.getTime());
            reference.setUTCDate(reference.getUTCDate() + 3);
        }
        return reference.getUTCFullYear() + '年' + (reference.getUTCMonth() + 1) + '月';
    }

    function todayIsoInZone(timeZone) {
        return new Intl.DateTimeFormat('en-CA', {
            timeZone: timeZone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        }).format(new Date());
    }

    // The current instant, expressed as the same "fake-UTC" marker convention as
    // arg.event.start/end elsewhere in this file — lets a plain getTime() comparison tell
    // whether an event has already ended in the configured timezone's wall-clock time.
    function nowFakeUtcInZone(timeZone) {
        var parts = new Intl.DateTimeFormat('en-CA', {
            timeZone: timeZone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hourCycle: 'h23',
        }).formatToParts(new Date());
        var map = {};
        parts.forEach(function (p) { map[p.type] = p.value; });
        return Date.UTC(map.year, map.month - 1, map.day, map.hour, map.minute, map.second);
    }

    // ---- Mini month calendar (same #miniCal markup from tpl/globalheader.tpl that
    // Web/scripts/overview.js drives — reimplemented here since that file isn't loaded on
    // this page. The one behavioral difference: "current week" tracks FullCalendar's live
    // view via setWeekStart()/datesSet below, instead of a server-rendered start date, and
    // clicking a day calls calendar.gotoDate() instead of a full-page navigation. ----
    var miniCal = (function () {
        var $label = $('#miniCalLabel');
        var $grid = $('#miniCalGrid');
        if (!$grid.length) {
            return null;
        }

        function pad(n) {
            return n < 10 ? '0' + n : '' + n;
        }

        function addDays(d, n) {
            var copy = new Date(d.getTime());
            copy.setDate(copy.getDate() + n);
            return copy;
        }

        function isoDate(d) {
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
        }

        function mondayOfWeek(d) {
            var day = d.getDay(); // Sunday=0..Saturday=6
            var diff = day === 0 ? -6 : 1 - day;
            return addDays(d, diff);
        }

        // A synthetic LOCAL date carrying only today's Y/M/D in the configured timezone
        // (mirrors overview.js's parseISODate pattern) — keeps every getFullYear()/
        // getMonth()/getDate()/getDay() call below correct regardless of the browser's
        // own local timezone.
        function todayLocalEquivalent() {
            var parts = todayIsoInZone(opts.timezone).split('-');
            return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        }

        var weekStart = todayLocalEquivalent();
        var weekDates = [];
        var viewYear = weekStart.getFullYear();
        var viewMonth = weekStart.getMonth();

        function render() {
            $label.text(viewYear + '年' + (viewMonth + 1) + '月');
            $grid.empty();

            var firstOfMonth = new Date(viewYear, viewMonth, 1);
            var gridStart = mondayOfWeek(firstOfMonth);
            var today = todayLocalEquivalent();

            for (var i = 0; i < 42; i++) {
                var cellDate = addDays(gridStart, i);
                var $btn = $('<button type="button"></button>').text(cellDate.getDate());

                if (cellDate.getMonth() !== viewMonth) {
                    $btn.addClass('outside-month');
                }
                var isToday = cellDate.getTime() === today.getTime();
                var isInWeek = weekDates.indexOf(cellDate.getTime()) !== -1;

                if (isToday && isInWeek) {
                    $btn.addClass('selected-today');
                } else if (isToday) {
                    $btn.addClass('today');
                } else if (isInWeek) {
                    $btn.addClass('in-week');
                }

                (function (targetDate) {
                    $btn.on('click', function () {
                        calendar.gotoDate(isoDate(mondayOfWeek(targetDate)));
                    });
                })(cellDate);

                $grid.append($btn);
            }
        }

        $('#miniCalPrev').on('click', function () {
            viewMonth -= 1;
            if (viewMonth < 0) {
                viewMonth = 11;
                viewYear -= 1;
            }
            render();
        });

        $('#miniCalNext').on('click', function () {
            viewMonth += 1;
            if (viewMonth > 11) {
                viewMonth = 0;
                viewYear += 1;
            }
            render();
        });

        return {
            // fakeUtcWeekStart is FullCalendar's datesSet info.start (a fake-UTC marker) —
            // only its UTC Y/M/D components are meaningful, same convention as elsewhere
            // in this file.
            setWeekStart: function (fakeUtcWeekStart) {
                weekStart = new Date(
                    fakeUtcWeekStart.getUTCFullYear(),
                    fakeUtcWeekStart.getUTCMonth(),
                    fakeUtcWeekStart.getUTCDate()
                );
                weekDates = [];
                for (var i = 0; i < 7; i++) {
                    weekDates.push(addDays(weekStart, i).getTime());
                }
                viewYear = weekStart.getFullYear();
                viewMonth = weekStart.getMonth();
                render();
            },
        };
    })();

    // ---- Quick-create modal (same booking UX as Web/scripts/overview.js's version, reusing
    // the identical #quickCreateModal markup/CSS and ajax/reservation_save.php endpoint —
    // the only structural difference is how a time range gets selected in the first place:
    // FullCalendar's own selectable:true + select() callback below, instead of a hand-rolled
    // mousedown/mousemove/mouseup pixel-math drag implementation. ----
    var $modalEl = $('#quickCreateModal');
    var quickCreateModal = $modalEl.length && window.bootstrap ? new bootstrap.Modal($modalEl[0]) : null;
    var pending = null; // { resourceId, startDate, endDate, beginMinutes, endMinutes } — beginMinutes/endMinutes are each 0-1439 within their OWN date; a cross-day reservation has endDate > startDate, not endMinutes > 1440.

    $modalEl.find('.qc-close').on('click', function () {
        if (quickCreateModal) {
            quickCreateModal.hide();
        }
    });

    function minutesToHHMMSS(minutes) {
        var h = Math.floor(minutes / 60) % 24;
        var m = minutes % 60;
        return pad(h) + ':' + pad(m) + ':00';
    }

    function formatTimeLabel(minutes) {
        var h = Math.floor(minutes / 60) % 24;
        var m = minutes % 60;
        var period = h < 12 ? '上午' : '下午';
        var h12 = h % 12;
        if (h12 === 0) {
            h12 = 12;
        }
        return period + h12 + ':' + pad(m);
    }

    function formatDuration(minutes) {
        if (minutes < 60) {
            return minutes + ' 分鐘';
        }
        var hours = minutes / 60;
        return (hours % 1 === 0 ? hours : hours.toFixed(1)) + ' 小時';
    }

    // ---- Generic pill + dropdown control shared by the start/end/resource pickers ----
    function TimeDropdown(pillId, dropdownId, onPick) {
        var $pill = $(pillId);
        var $dropdown = $(dropdownId);

        function close() {
            $dropdown.removeClass('show');
            $pill.attr('aria-expanded', 'false');
        }

        function open() {
            closeAllDropdowns();
            var $selected = $dropdown.find('.qc-option.selected');
            if ($selected.length) {
                $dropdown[0].scrollTop = $selected[0].offsetTop - $dropdown[0].offsetHeight / 2;
            }
            $dropdown.addClass('show');
            $pill.attr('aria-expanded', 'true');
        }

        $pill.on('click', function (e) {
            e.stopPropagation();
            if ($dropdown.hasClass('show')) {
                close();
            } else {
                open();
            }
        });

        function render(options, selectedMinutes) {
            $dropdown.empty();
            options.forEach(function (opt) {
                var $opt = $('<button type="button" class="qc-option" role="option"></button>').text(opt.label);
                if (opt.minutes === selectedMinutes) {
                    $opt.addClass('selected');
                }
                $opt.on('click', function (e) {
                    e.stopPropagation();
                    close();
                    onPick(opt.minutes);
                });
                $dropdown.append($opt);
            });
        }

        return { render: render, close: close };
    }

    var allDropdownClosers = [];
    function closeAllDropdowns() {
        allDropdownClosers.forEach(function (close) { close(); });
    }
    $(document).on('click', closeAllDropdowns);

    var startPicker = TimeDropdown('#qcStartPill', '#qcStartDropdown', function (minutes) {
        // Preserve the current duration when nudging the start time, but only when start/end
        // are still the same calendar day — once the end date is a later day, its time is
        // independent of the start time and shouldn't be dragged along.
        if (pending.startDate === pending.endDate) {
            var duration = Math.max(30, pending.endMinutes - pending.beginMinutes);
            pending.beginMinutes = minutes;
            pending.endMinutes = Math.min(24 * 60 - 30, minutes + duration);
        } else {
            pending.beginMinutes = minutes;
        }
        refreshTimeUI();
    });
    var endPicker = TimeDropdown('#qcEndPill', '#qcEndDropdown', function (minutes) {
        pending.endMinutes = minutes;
        refreshTimeUI();
    });
    allDropdownClosers.push(startPicker.close, endPicker.close);

    function refreshTimeUI() {
        // Full 00:00-23:30 range, no artificial duration cap (previously capped at +8 hours).
        // Same-day end time must be strictly after the start time; once the end date is a
        // later day, the end time is free to be anything — a genuine cross-midnight range
        // (e.g. 22:00 -> next day 02:00) is expressed via the separate end DATE input, not by
        // letting minutes run past 1440.
        var sameDay = pending.startDate === pending.endDate;
        var minEndMinutes = sameDay ? pending.beginMinutes + 30 : 0;
        // A date-input change (or a start-time change that pushes past the old end time) can
        // leave pending.endMinutes stale/invalid for the now-current start/end date pair (e.g.
        // switching the end date back to match the start date after picking an end time that
        // only made sense cross-day) — snap it back into range rather than silently submitting
        // an end time that's actually before the start time on the same day.
        if (pending.endMinutes < minEndMinutes) {
            pending.endMinutes = minEndMinutes;
        }
        if (pending.endMinutes > 24 * 60 - 30) {
            pending.endMinutes = 24 * 60 - 30;
        }

        $('#qcStartPill').text(formatTimeLabel(pending.beginMinutes));
        $('#qcEndPill').text(formatTimeLabel(pending.endMinutes));

        var startOptions = [];
        for (var m = 0; m < 24 * 60; m += 30) {
            startOptions.push({ minutes: m, label: formatTimeLabel(m) });
        }
        startPicker.render(startOptions, pending.beginMinutes);

        var endOptions = [];
        for (var e = minEndMinutes; e <= 24 * 60 - 30; e += 30) {
            var label = formatTimeLabel(e);
            if (sameDay) {
                label += '（' + formatDuration(e - pending.beginMinutes) + '）';
            }
            endOptions.push({ minutes: e, label: label });
        }
        endPicker.render(endOptions, pending.endMinutes);

        $('#qcTimeError').addClass('d-none').empty();

        $('#qcMoreOptions').attr('href', moreOptionsUrl());
    }

    function resourceNameFor(resourceId) {
        return (opts.resourceNames || {})[resourceId] || '';
    }

    var activeResourceCategory = 'physical';
    var resourcePicker = TimeDropdown('#qcResourcePill', '#qcResourceDropdown', function (resourceId) {
        pending.resourceId = resourceId;
        refreshResourceUI();
    });
    allDropdownClosers.push(resourcePicker.close);

    function refreshResourceUI() {
        $('#qcResourcePill').text(resourceNameFor(pending.resourceId));
        $('#qcResourceTabs .qc-resource-tab').each(function () {
            $(this).toggleClass('active', $(this).data('category') === activeResourceCategory);
        });

        var groups = opts.resourceGroups || { physical: [], video: [] };
        var list = groups[activeResourceCategory] || [];
        var options = list.map(function (r) { return { minutes: r.id, label: r.name }; });
        // TimeDropdown's option objects are keyed on `minutes` generically — reused here for resource ids.
        resourcePicker.render(options, pending.resourceId);

        $('#qcMoreOptions').attr('href', moreOptionsUrl());
    }

    $('#qcResourceTabs .qc-resource-tab').on('click', function () {
        var category = $(this).data('category');
        if (category === activeResourceCategory) {
            return;
        }
        activeResourceCategory = category;
        var list = (opts.resourceGroups || {})[category] || [];
        if (list.length) {
            pending.resourceId = list[0].id;
        }
        refreshResourceUI();
    });

    function moreOptionsUrl() {
        return opts.reservationUrl +
            '?rid=' + encodeURIComponent(pending.resourceId) +
            '&sd=' + pending.startDate + '+' + minutesToHHMMSS(pending.beginMinutes) +
            '&ed=' + pending.endDate + '+' + minutesToHHMMSS(pending.endMinutes);
    }

    function openQuickCreate(p) {
        var startDate = p.startDate || p.date;
        var endDate = p.endDate || p.date;
        if (!quickCreateModal) {
            window.location = opts.reservationUrl +
                '?rid=' + encodeURIComponent(p.resourceId) + '&sd=' + startDate + '+' + p.beginTime + '&ed=' + endDate + '+' + p.endTime;
            return; // no Bootstrap JS available — fall back to the full form
        }
        var beginMinutes = parseInt(p.beginTime.slice(0, 2), 10) * 60 + parseInt(p.beginTime.slice(3, 5), 10);
        var endMinutes = parseInt(p.endTime.slice(0, 2), 10) * 60 + parseInt(p.endTime.slice(3, 5), 10);
        pending = { resourceId: p.resourceId, startDate: startDate, endDate: endDate, beginMinutes: beginMinutes, endMinutes: endMinutes };
        activeResourceCategory = categoryOf(p.resourceId);

        $('#qcTitle').val('');
        $('#qcDescription').val('');
        $('#qcStartDate').val(startDate);
        $('#qcEndDate').val(endDate);
        $('#qcError').addClass('d-none').empty();
        $('#qcTimeError').addClass('d-none').empty();
        refreshTimeUI();
        refreshResourceUI();
        quickCreateModal.show();
        setTimeout(function () { $('#qcTitle').trigger('focus'); }, 200);
    }

    // Keep the end date from ever landing before the start date — bump it forward instead of
    // silently accepting an invalid range, same principle as the Drawer's own date fields.
    $('#qcStartDate').on('change', function () {
        pending.startDate = $(this).val();
        if (pending.endDate < pending.startDate) {
            pending.endDate = pending.startDate;
            $('#qcEndDate').val(pending.endDate);
        }
        refreshTimeUI();
    });
    $('#qcEndDate').on('change', function () {
        pending.endDate = $(this).val();
        if (pending.startDate > pending.endDate) {
            pending.startDate = pending.endDate;
            $('#qcStartDate').val(pending.startDate);
        }
        refreshTimeUI();
    });

    // CoAsia: the sidebar's own "+新增預約" button (and its click handler that used to live
    // here) was removed — creating a reservation now happens in exactly one place, clicking
    // or dragging a blank slot directly on the calendar (the `select` callback further down),
    // instead of two separate entry points into the same quick-create modal.

    // ---- Existing event: quick-view popover instead of an immediate full-page jump ----
    var $eventPopover = $('#eventPopover');

    function closeEventPopover() {
        $eventPopover.removeClass('show');
    }

    $(document).on('click', function (e) {
        if (!$(e.target).closest('#eventPopover, .fc-event').length) {
            closeEventPopover();
        }
    });

    // Explicit close button — clicking outside still works (handler above), but a card
    // with edit/delete actions on it needs its own unambiguous "I'm done" control too.
    $('#epCloseBtn').on('click', function (e) {
        e.preventDefault();
        closeEventPopover();
    });

    // Edit AND "查看詳情" (View Details) both open the same Drawer in place — never navigate
    // to reservation.php. There's no separate read-only mode: the Drawer's edit mode already
    // disables every field and hides save/delete based on the server-computed canEdit/canDelete
    // (see populateDrawerFromData()), so opening it for someone without edit rights naturally
    // renders as view-only. (openReservationDrawer is defined further down; safe to reference
    // here since this handler only runs on a later click, same pattern used elsewhere in this
    // file for the `calendar` variable, which is also referenced before its own declaration.)
    $('#epEditLink, #epViewLink').on('click', function (e) {
        e.preventDefault();
        var referenceNumber = $(this).data('reference-number');
        if (!referenceNumber) {
            return;
        }
        closeEventPopover();
        openReservationDrawer({ mode: 'edit', referenceNumber: referenceNumber });
    });

    // ---- Delete confirmation — reuses the existing ajax/reservation_delete.php pipeline
    // (Presenters/Reservation/ReservationDeletePresenter.php, SeriesUpdateScope) rather than
    // a new endpoint/format; view/edit both open the Drawer (see above), only delete needs a
    // request built from here, since there's no form on this page to submit. ----
    var $deleteModalEl = $('#deleteReservationModal');
    var deleteReservationModal = $deleteModalEl.length && window.bootstrap ? new bootstrap.Modal($deleteModalEl[0]) : null;
    var pendingDelete = null; // { referenceNumber, isRecurring }
    var deleteInFlight = false;

    function setDeleteInFlight(inFlight) {
        deleteInFlight = inFlight;
        $('#epDeleteConfirmBtn').prop('disabled', inFlight);
        $('.del-btn-spinner').toggleClass('d-none', !inFlight);
        $('#epDeleteConfirmLabel').toggleClass('d-none', inFlight);
    }

    function submitDelete() {
        if (!pendingDelete || deleteInFlight) {
            return;
        }
        // Single reservations never show the scope radios (checked value defaults to
        // 'full' either way) — a series-of-one being deleted "in full" is just that one
        // reservation, same default the classic edit.tpl form ships with.
        var scope = pendingDelete.isRecurring
            ? ($('input[name="epDeleteScope"]:checked').val() || 'full')
            : 'full';

        setDeleteInFlight(true);

        var body = new URLSearchParams();
        body.set('CSRF_TOKEN', opts.csrfToken);
        body.set('referenceNumber', pendingDelete.referenceNumber);
        body.set('seriesUpdateScope', scope);
        body.set('DELETE_REASON', $('#epDeleteReason').val());
        if (pendingDelete.version != null) {
            body.set('expectedVersion', pendingDelete.version);
        }

        $('#epDeleteError').addClass('d-none').empty();

        fetch(opts.deleteUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin',
        })
            .then(function (res) { return res.json().catch(function () { return {}; }); })
            .then(function (json) {
                // Pages/Ajax/ReservationDeletePage.php's JSON sibling (ReservationDeleteJsonPage,
                // selected by ?{QueryStringKeys::RESPONSE_TYPE}=json i.e. ?rs=json) returns
                // {"deleted":"1"} on success, or {"response":{"deleted":""},"errors":[...]} on
                // failure (Pages/Page.php::SetJson()) — both shapes are handled here rather than
                // assuming just one, so a real backend error message reaches the user instead of a
                // generic "something went wrong".
                var deleted = json && (json.deleted === '1' || (json.response && json.response.deleted === '1'));
                if (deleted) {
                    if (deleteReservationModal) {
                        deleteReservationModal.hide();
                    }
                    closeEventPopover();
                    pendingDelete = null;
                    calendar.refetchEvents();
                    return;
                }
                var errors = (json && json.errors) || [];
                var code = json && json.response && json.response.code;
                if (code === 'reservation_changed') {
                    // Someone else already changed (or deleted) this reservation — refresh the
                    // calendar so stale event data doesn't linger, and say why nothing happened
                    // rather than silently failing or retrying the same stale delete.
                    calendar.refetchEvents();
                }
                $('#epDeleteError').removeClass('d-none').html(
                    errors.length ? errors.join('<br>') : 'Delete failed.'
                );
            })
            .catch(function () {
                $('#epDeleteError').removeClass('d-none').text('Network error — please try again.');
            })
            .finally(function () {
                setDeleteInFlight(false);
            });
    }

    $('#epDeleteConfirmBtn').on('click', submitDelete);

    $('#epDeleteBtn').on('click', function () {
        var referenceNumber = $(this).data('reference-number');
        if (!referenceNumber) {
            return;
        }
        var isRecurring = !!$(this).data('is-recurring');
        pendingDelete = { referenceNumber: referenceNumber, isRecurring: isRecurring };

        $('#epDeleteReason').val('');
        $('#epDeleteError').addClass('d-none').empty();
        $('#epDeleteRecurringBody').toggleClass('d-none', !isRecurring);
        $('input[name="epDeleteScope"][value="full"]').prop('checked', true);
        setDeleteInFlight(false);

        closeEventPopover();
        if (deleteReservationModal) {
            deleteReservationModal.show();
        }
    });

    // ---- Reservation Drawer — the one full editor shared by "更多選項" (create) and the
    // event card's pencil icon (edit). Replaces navigating to reservation.php for the normal
    // flow; that page stays reachable directly (bookmarks/admin) but nothing in this UI
    // routes a regular user through it anymore.
    //
    // Edit-mode saves hit a DEDICATED endpoint (ajax/overview_fullcalendar_reservation_update.php
    // / OverviewFullCalendarReservationUpdatePresenter), not ajax/reservation_update.php. That
    // classic endpoint rebuilds the whole reservation from submitted form fields — anything
    // omitted (participants, invitees, guests, accessories, attributes, reminders, repeat
    // rule) reads as "clear it" — so echoing loaded data back through the browser to survive
    // a basic edit was a real data-loss risk (stale/incomplete/tampered payload). The
    // dedicated endpoint instead loads the existing series server-side and touches ONLY
    // title/description/start/end/resource, leaving everything else exactly as persisted.
    var $drawerEl = $('#reservationDrawer');
    var reservationDrawer = $drawerEl.length && window.bootstrap ? new bootstrap.Modal($drawerEl[0]) : null;
    var drawerState = null; // { mode: 'create'|'edit', referenceNumber, echo, saved }
    var drawerDirty = false;
    var rdSelectedWeekdays = []; // int[] 0(Sun)-6(Sat), synced with #rdWeekdayPicker button state
    var rdCustomDates = []; // string[] 'YYYY-MM-DD', synced with #rdRepeatCustomList chips

    function buildTimeSelectOptions($select) {
        $select.empty();
        for (var m = 0; m < 24 * 60; m += 30) {
            var value = minutesToHHMMSS(m);
            $select.append($('<option>').val(value).text(formatTimeLabel(m)));
        }
    }

    // A reservation's real time (e.g. 09:15) may not land on this UI's 30-minute grid.
    // .val('09:15:00') on a <select> with no matching <option> silently leaves nothing
    // selected — the field would look right but submit empty. Inject the exact value as its
    // own option first so it's always selectable and never gets rounded off.
    function ensureTimeOption($select, value) {
        if (value && !$select.find('option[value="' + value + '"]').length) {
            var minutes = parseInt(value.slice(0, 2), 10) * 60 + parseInt(value.slice(3, 5), 10);
            $select.append($('<option>').val(value).text(formatTimeLabel(minutes)));
        }
        $select.val(value);
    }

    function populateDrawerResourceOptions(category, selectedResourceId) {
        var groups = opts.resourceGroups || { physical: [], video: [] };
        var list = groups[category] || [];
        var $select = $('#rdResource').empty();
        list.forEach(function (r) {
            $select.append($('<option>').val(String(r.id)).text(r.name));
        });
        if (selectedResourceId != null) {
            $select.val(String(selectedResourceId));
        } else if (list.length) {
            $select.val(String(list[0].id));
        }
    }

    function setDrawerCategory(category, selectedResourceId) {
        $('#rdCategoryTabs .rd-category-tab').each(function () {
            $(this).toggleClass('active', $(this).data('category') === category);
        });
        populateDrawerResourceOptions(category, selectedResourceId);
    }

    $('#rdCategoryTabs .rd-category-tab').on('click', function () {
        var category = $(this).data('category');
        if (!$(this).hasClass('active')) {
            setDrawerCategory(category, null);
            drawerDirty = true;
        }
    });

    ['#rdTitle', '#rdDescription', '#rdStartDate', '#rdStartTime', '#rdEndDate', '#rdEndTime', '#rdResource'].forEach(function (sel) {
        $(sel).on('input change', function () { drawerDirty = true; });
    });

    function resetDrawerUI() {
        $('#rdTitle').val('').prop('disabled', false);
        $('#rdDescription').val('').prop('disabled', false);
        $('#rdStartDate, #rdEndDate').prop('disabled', false);
        $('#rdStartTime, #rdEndTime, #rdResource').prop('disabled', false);
        $('#rdCategoryTabs .rd-category-tab').prop('disabled', false);
        $('#rdAdditionalResourcesSummary, #rdAccessoriesSummary, #rdAllowParticipationSummary, #rdParticipantsSummary, #rdGuestsSummary, #rdRemindersSummary, #rdAttributesSummary, #rdAttachmentsSummary').addClass('d-none');
        $('#rdAdditionalResourcesList, #rdAccessoriesList, #rdParticipantsList, #rdGuestsList, #rdAttributesList, #rdAttachmentsList').empty();

        $('#rdRepeatType').val('none').prop('disabled', false);
        $('#rdRepeatInterval').val('1').prop('disabled', false);
        $('#rdRepeatMonthlyType').val('dayOfMonth').prop('disabled', false);
        $('#rdRepeatEndDate').val('').prop('disabled', false);
        $('#rdRepeatCustomDateInput, #rdRepeatCustomAddBtn').prop('disabled', false);
        rdSelectedWeekdays = [];
        $('#rdWeekdayPicker .rd-weekday-btn').removeClass('active').prop('disabled', false);
        rdCustomDates = [];
        renderRepeatCustomDatesList();
        refreshRepeatUI();
        $('#rdLoadError').addClass('d-none').empty();
        $('#rdSaveError').addClass('d-none').empty();
        $('#rdTimeError').addClass('d-none').empty();
        $('#rdRepeatError').addClass('d-none').empty();
        $('#rdSkeleton').addClass('d-none');
        $('#rdForm').removeClass('d-none');
        $('#rdSaveBtn').removeClass('d-none').prop('disabled', false);
        $('.rd-save-spinner').addClass('d-none');
        $('#rdDeleteBtn, #rdDeleteFooterBtn').addClass('d-none');
        drawerDirty = false;
    }

    function reminderIntervalLabel(interval) {
        var key = { minutes: 'minutes', hours: 'hours', days: 'days' }[interval];
        return key ? (opts.translations && opts.translations[key]) || interval : interval;
    }

    function repeatIntervalUnitLabel(type) {
        return { daily: '天', weekly: '週', monthly: '月' }[type] || '';
    }

    function refreshRepeatUI() {
        var type = $('#rdRepeatType').val();
        $('#rdRepeatIntervalRow').toggleClass('d-none', type === 'none' || type === 'custom');
        $('#rdRepeatWeekdaysRow').toggleClass('d-none', type !== 'weekly');
        $('#rdRepeatMonthlyTypeRow').toggleClass('d-none', type !== 'monthly');
        $('#rdRepeatEndRow').toggleClass('d-none', type === 'none' || type === 'custom');
        $('#rdRepeatCustomRow').toggleClass('d-none', type !== 'custom');
        $('#rdRepeatIntervalUnit').text(repeatIntervalUnitLabel(type));
    }

    $('#rdRepeatType').on('change', refreshRepeatUI);

    $('#rdWeekdayPicker').on('click', '.rd-weekday-btn', function () {
        if ($(this).prop('disabled')) {
            return;
        }
        var day = parseInt($(this).data('day'), 10);
        var idx = rdSelectedWeekdays.indexOf(day);
        if (idx === -1) {
            rdSelectedWeekdays.push(day);
            $(this).addClass('active');
        } else {
            rdSelectedWeekdays.splice(idx, 1);
            $(this).removeClass('active');
        }
    });

    function renderRepeatCustomDatesList() {
        var $list = $('#rdRepeatCustomList').empty();
        rdCustomDates.forEach(function (dateStr) {
            var $chip = $('<span class="reservation-chip"></span>').text(dateStr);
            $('<button type="button" class="btn-close btn-close-white" style="font-size:8px" aria-label="Remove"></button>')
                .on('click', function () {
                    var idx = rdCustomDates.indexOf(dateStr);
                    if (idx !== -1) {
                        rdCustomDates.splice(idx, 1);
                    }
                    renderRepeatCustomDatesList();
                })
                .appendTo($chip);
            $list.append($chip);
        });
    }

    $('#rdRepeatCustomAddBtn').on('click', function () {
        var val = $('#rdRepeatCustomDateInput').val();
        if (val && rdCustomDates.indexOf(val) === -1) {
            rdCustomDates.push(val);
            rdCustomDates.sort();
            renderRepeatCustomDatesList();
        }
        $('#rdRepeatCustomDateInput').val('');
    });

    function populateDrawerFromData(data) {
        var canEdit = !!data.canEdit;

        $('#rdTitle').val(data.title || '').prop('disabled', !canEdit);
        $('#rdDescription').val(data.description || '').prop('disabled', !canEdit);
        $('#rdStartDate').val(data.startDate).prop('disabled', !canEdit);
        $('#rdEndDate').val(data.endDate).prop('disabled', !canEdit);
        $('#rdStartTime, #rdEndTime').prop('disabled', !canEdit);
        buildTimeSelectOptions($('#rdStartTime'));
        buildTimeSelectOptions($('#rdEndTime'));
        ensureTimeOption($('#rdStartTime'), data.startTime);
        ensureTimeOption($('#rdEndTime'), data.endTime);
        $('#rdResource').prop('disabled', !canEdit);
        $('#rdCategoryTabs .rd-category-tab').prop('disabled', !canEdit);
        setDrawerCategory(categoryOf(data.resourceId), data.resourceId);

        var $additionalResourcesList = $('#rdAdditionalResourcesList').empty();
        if ((data.additionalResourceIds || []).length > 0) {
            $('#rdAdditionalResourcesSummary').removeClass('d-none');
            data.additionalResourceIds.forEach(function (resourceId) {
                $('<span class="reservation-chip"></span>').text(resourceNameFor(resourceId) || resourceId).appendTo($additionalResourcesList);
            });
        }

        var $accessoriesList = $('#rdAccessoriesList').empty();
        if ((data.accessories || []).length > 0) {
            $('#rdAccessoriesSummary').removeClass('d-none');
            data.accessories.forEach(function (accessory) {
                $('<span class="reservation-chip"></span>')
                    .text(accessory.name + (accessory.quantityReserved > 1 ? ' × ' + accessory.quantityReserved : ''))
                    .appendTo($accessoriesList);
            });
        }

        if (data.allowParticipation) {
            $('#rdAllowParticipationSummary').removeClass('d-none');
        }

        var repeat = data.repeat || { type: 'none' };
        $('#rdRepeatType').val(repeat.type || 'none').prop('disabled', !canEdit);
        $('#rdRepeatInterval').val(repeat.interval || 1).prop('disabled', !canEdit);
        $('#rdRepeatMonthlyType').val(repeat.monthlyType || 'dayOfMonth').prop('disabled', !canEdit);
        $('#rdRepeatEndDate').val(repeat.terminationDate || '').prop('disabled', !canEdit);
        rdSelectedWeekdays = (repeat.weekdays || []).slice();
        $('#rdWeekdayPicker .rd-weekday-btn').each(function () {
            var day = parseInt($(this).data('day'), 10);
            $(this).toggleClass('active', rdSelectedWeekdays.indexOf(day) !== -1).prop('disabled', !canEdit);
        });
        rdCustomDates = (repeat.customDates || []).slice();
        renderRepeatCustomDatesList();
        $('#rdRepeatCustomDateInput, #rdRepeatCustomAddBtn').prop('disabled', !canEdit);
        refreshRepeatUI();

        // Full read-only lists (not just a count) — everyone who can open this Drawer sees
        // who's involved, whether or not they can edit; management (add/remove) still only
        // exists via "更多選項" into the classic form (see COASIA-CHANGES.md P0-1 scope note).
        var people = (data.participants || []).concat((data.invitees || []).map(function (u) {
            return { userId: u.userId, fullName: u.fullName, invitee: true };
        }));
        var $participantsList = $('#rdParticipantsList').empty();
        if (people.length > 0) {
            $('#rdParticipantsSummary').removeClass('d-none');
            people.forEach(function (person) {
                $('<span class="reservation-chip"></span>')
                    .text(person.fullName + (person.invitee ? '（受邀）' : ''))
                    .appendTo($participantsList);
            });
        }

        var guests = (data.participatingGuests || []).map(function (email) {
            return { email: email, invited: false };
        }).concat((data.invitedGuests || []).map(function (email) {
            return { email: email, invited: true };
        }));
        var $guestsList = $('#rdGuestsList').empty();
        if (guests.length > 0) {
            $('#rdGuestsSummary').removeClass('d-none');
            guests.forEach(function (guest) {
                $('<span class="reservation-chip"></span>')
                    .text(guest.email + (guest.invited ? '（受邀）' : ''))
                    .appendTo($guestsList);
            });
        }

        var attributes = data.attributes || {};
        var $attributesList = $('#rdAttributesList').empty();
        var hasAttributeValue = Object.keys(attributes).some(function (id) {
            return attributes[id] && attributes[id].value;
        });
        if (hasAttributeValue) {
            $('#rdAttributesSummary').removeClass('d-none');
            Object.keys(attributes).forEach(function (id) {
                var attr = attributes[id];
                if (!attr || !attr.value) {
                    return;
                }
                $('<div class="rd-attribute-row"></div>')
                    .append($('<span class="rd-attribute-label"></span>').text((attr.label || id) + '：'))
                    .append($('<span class="rd-attribute-value"></span>').text(attr.value))
                    .appendTo($attributesList);
            });
        }

        var $attachmentsList = $('#rdAttachmentsList').empty();
        if ((data.attachments || []).length > 0) {
            $('#rdAttachmentsSummary').removeClass('d-none');
            data.attachments.forEach(function (attachment) {
                $('<span class="reservation-chip"></span>')
                    .html('<i class="bi bi-paperclip"></i> ')
                    .append(document.createTextNode(attachment.fileName))
                    .appendTo($attachmentsList);
            });
        }

        var reminderParts = [];
        if (data.reminders.start.enabled) {
            reminderParts.push('開始前 ' + data.reminders.start.value + ' ' + reminderIntervalLabel(data.reminders.start.interval));
        }
        if (data.reminders.end.enabled) {
            reminderParts.push('結束前 ' + data.reminders.end.value + ' ' + reminderIntervalLabel(data.reminders.end.interval));
        }
        if (reminderParts.length) {
            $('#rdRemindersSummary').removeClass('d-none');
            $('#rdRemindersSummaryText').text(reminderParts.join('、'));
        }

        $('#rdDeleteBtn').toggleClass('d-none', !data.canDelete);
        $('#rdDeleteFooterBtn').toggleClass('d-none', !data.canDelete);
        $('#rdSaveBtn').toggleClass('d-none', !canEdit);
    }

    // One UUID per logical "create a reservation" attempt — kept for the lifetime of that
    // attempt (including any HTTP retries a double-click or network hiccup causes) and only
    // replaced when a genuinely new attempt starts (the Drawer opens fresh in create mode).
    // Lets the server (IdempotencyService, reservation_requests table) recognize a resend of
    // the same attempt and return the original reservation instead of creating a second one.
    function generateRequestId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = (Math.random() * 16) | 0;
            var v = c === 'x' ? r : (r & 0x3) | 0x8;
            return v.toString(16);
        });
    }

    function openReservationDrawer(options) {
        options = options || {};
        var mode = options.mode || 'create';
        resetDrawerUI();
        drawerState = {
            mode: mode,
            referenceNumber: options.referenceNumber || null,
            echo: null,
            saved: false,
            requestId: mode === 'create' ? generateRequestId() : null,
        };

        $('#rdHeaderTitle').text(mode === 'edit' ? opts.translations.editReservation : opts.translations.newReservation);
        $('#rdSaveLabel').text(mode === 'edit' ? opts.translations.update : opts.translations.create);

        if (reservationDrawer) {
            reservationDrawer.show();
        }

        if (mode === 'create') {
            var draft = options.draft || {};
            buildTimeSelectOptions($('#rdStartTime'));
            buildTimeSelectOptions($('#rdEndTime'));
            ensureTimeOption($('#rdStartTime'), draft.beginTime);
            ensureTimeOption($('#rdEndTime'), draft.endTime);
            $('#rdTitle').val(draft.title || '');
            $('#rdDescription').val(draft.description || '');
            $('#rdStartDate').val(draft.startDate || draft.date || '');
            $('#rdEndDate').val(draft.endDate || draft.date || '');
            setDrawerCategory(draft.resourceId ? categoryOf(draft.resourceId) : activeResourceCategory, draft.resourceId);
            drawerDirty = false;
            return;
        }

        // Edit mode — never trust the calendar's own lightweight event fields for anything
        // beyond what to show while loading; the real data always comes from the server.
        $('#rdForm').addClass('d-none');
        $('#rdSkeleton').removeClass('d-none');

        fetch(opts.reservationDataUrl + '?' + new URLSearchParams({ rn: options.referenceNumber }).toString(), {
            credentials: 'same-origin',
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                $('#rdSkeleton').addClass('d-none');
                if (!data || !data.found || !data.canView) {
                    $('#rdLoadError').removeClass('d-none').text(opts.translations.loadReservationFailed);
                    return;
                }
                $('#rdForm').removeClass('d-none');
                drawerState.echo = data;
                populateDrawerFromData(data);
                drawerDirty = false;
            })
            .catch(function () {
                $('#rdSkeleton').addClass('d-none');
                $('#rdLoadError').removeClass('d-none').text(opts.translations.networkErrorRetry);
            });
    }

    // Leaving with unsaved edits (backdrop click, ESC, close/cancel buttons — all funnel
    // through Bootstrap's hide()) prompts once rather than silently discarding input.
    $drawerEl.on('hide.bs.modal', function (e) {
        if (drawerDirty && !(drawerState && drawerState.saved)) {
            if (!window.confirm(opts.translations.unsavedChangesPrompt)) {
                e.preventDefault();
            }
        }
    });
    $drawerEl.on('hidden.bs.modal', function () {
        drawerDirty = false;
    });

    $('#rdCloseBtn').on('click', function () {
        if (reservationDrawer) {
            reservationDrawer.hide();
        }
    });

    function openDrawerDeleteConfirm() {
        if (!drawerState || !drawerState.referenceNumber) {
            return;
        }
        var isRecurring = !!(drawerState.echo && drawerState.echo.isRecurring);
        // version: only available when the Drawer itself loaded the reservation (has echo);
        // the event-popover's direct delete button below has no loaded version to offer, so
        // that path falls back to whatever version gets loaded fresh at delete time server-side
        // (still race-safe against a concurrent writer, just not stale-edit-safe).
        var version = drawerState.echo && drawerState.echo.version != null ? drawerState.echo.version : null;
        pendingDelete = { referenceNumber: drawerState.referenceNumber, isRecurring: isRecurring, version: version };

        $('#epDeleteReason').val('');
        $('#epDeleteError').addClass('d-none').empty();
        $('#epDeleteRecurringBody').toggleClass('d-none', !isRecurring);
        $('input[name="epDeleteScope"][value="full"]').prop('checked', true);
        setDeleteInFlight(false);

        if (reservationDrawer) {
            reservationDrawer.hide();
        }
        if (deleteReservationModal) {
            deleteReservationModal.show();
        }
    }

    $('#rdDeleteBtn, #rdDeleteFooterBtn').on('click', openDrawerDeleteConfirm);

    // ---- Save. Create still goes through the existing ajax/reservation_save.php (a brand
    // new reservation has no prior participants/reminders/etc to lose). Edit goes through
    // the dedicated basic-fields-only endpoint described above, NOT ajax/reservation_update.php
    // — see the Drawer's opening comment for why. Neither path reimplements validation,
    // conflict checking, permissions, or notifications. ----

    function drawerStartEndValues() {
        return {
            startDate: $('#rdStartDate').val(),
            startTime: $('#rdStartTime').val(),
            endDate: $('#rdEndDate').val(),
            endTime: $('#rdEndTime').val(),
        };
    }

    function validateDrawerTimes() {
        var v = drawerStartEndValues();
        $('#rdTimeError').addClass('d-none').empty();
        if (!v.startDate || !v.startTime || !v.endDate || !v.endTime) {
            return true; // let the backend's own required-field validation handle blanks
        }
        // Plain string comparison works here since both are zero-padded ISO-ish
        // (YYYY-MM-DD / HH:MM:SS) — correctly orders across day boundaries too.
        var start = v.startDate + ' ' + v.startTime;
        var end = v.endDate + ' ' + v.endTime;
        if (end <= start) {
            $('#rdTimeError').removeClass('d-none').text(opts.translations.endBeforeStart);
            return false;
        }
        return true;
    }

    // Mirrors the classic form's own required-field rules (Web/scripts/recurrence.js's
    // RequiresTerminationDate()) rather than inventing new ones: daily/weekly/monthly repeats
    // need an end date (open-ended recurrence isn't supported), weekly needs at least one
    // weekday, custom needs at least one date.
    function validateRepeatOptions() {
        $('#rdRepeatError').addClass('d-none').empty();
        var type = $('#rdRepeatType').val();

        if ((type === 'daily' || type === 'weekly' || type === 'monthly') && !$('#rdRepeatEndDate').val()) {
            $('#rdRepeatError').removeClass('d-none').text(opts.translations.repeatEndDateRequired);
            return false;
        }
        if (type === 'weekly' && rdSelectedWeekdays.length === 0) {
            $('#rdRepeatError').removeClass('d-none').text(opts.translations.repeatWeekdayRequired);
            return false;
        }
        if (type === 'custom' && rdCustomDates.length === 0) {
            $('#rdRepeatError').removeClass('d-none').text(opts.translations.repeatCustomDateRequired);
            return false;
        }
        return true;
    }

    // Same field names the classic form's RecurrenceControl already submits (FormKeys::
    // REPEAT_OPTIONS etc) — RepeatOptionsFactory needs no changes to read these.
    function appendRepeatFields(body) {
        var type = $('#rdRepeatType').val();
        body.set('repeatOptions', type);
        body.set('repeatEvery', $('#rdRepeatInterval').val() || '1');
        var dayKeys = ['repeatSunday', 'repeatMonday', 'repeatTuesday', 'repeatWednesday', 'repeatThursday', 'repeatFriday', 'repeatSaturday'];
        dayKeys.forEach(function (key, day) {
            if (rdSelectedWeekdays.indexOf(day) !== -1) {
                body.set(key, '1');
            }
        });
        body.set('repeatMonthlyType', $('#rdRepeatMonthlyType').val());
        body.set('endRepeatDate', $('#rdRepeatEndDate').val() || '');
        rdCustomDates.forEach(function (d) {
            body.append('repeatCustomDates[]', d);
        });
    }

    function buildCreateBody() {
        var v = drawerStartEndValues();
        var body = new URLSearchParams();
        body.set('CSRF_TOKEN', opts.csrfToken);
        body.set('userId', opts.currentUserId);
        body.set('resourceId', $('#rdResource').val());
        body.set('reservationTitle', $('#rdTitle').val());
        body.set('reservationDescription', $('#rdDescription').val());
        body.set('beginDate', v.startDate);
        body.set('beginPeriod', v.startTime);
        body.set('endDate', v.endDate);
        body.set('endPeriod', v.endTime);
        appendRepeatFields(body);
        if (drawerState.requestId) {
            body.set('requestId', drawerState.requestId);
        }
        return body;
    }

    function buildEditBody(scope) {
        var v = drawerStartEndValues();
        var body = new URLSearchParams();
        body.set('CSRF_TOKEN', opts.csrfToken);
        body.set('referenceNumber', drawerState.referenceNumber);
        body.set('seriesUpdateScope', scope || 'full');
        body.set('resourceId', $('#rdResource').val());
        body.set('reservationTitle', $('#rdTitle').val());
        body.set('reservationDescription', $('#rdDescription').val());
        body.set('beginDate', v.startDate);
        body.set('beginPeriod', v.startTime);
        body.set('endDate', v.endDate);
        body.set('endPeriod', v.endTime);
        appendRepeatFields(body);
        // The version this Drawer loaded the reservation at (OverviewFullCalendarReservationPresenter's
        // 'version' field) — round-tripped so the server can detect someone else having changed
        // it in between (Domain/Access/ReservationRepository.php's WHERE version=? check), not
        // just a request racing with THIS one.
        if (drawerState.echo && drawerState.echo.version != null) {
            body.set('expectedVersion', drawerState.echo.version);
        }
        return body;
    }

    function setDrawerSaving(saving) {
        $('#rdSaveBtn').prop('disabled', saving);
        $('.rd-save-spinner').toggleClass('d-none', !saving);
    }

    function submitReservationCreate() {
        setDrawerSaving(true);
        $('#rdSaveError').addClass('d-none').empty();

        fetch(opts.saveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: buildCreateBody().toString(),
            credentials: 'same-origin',
        })
            .then(function (res) { return res.text(); })
            .then(function (html) {
                var $result = $('<div></div>').html(html);
                if ($result.find('#reservation-created').length) {
                    drawerState.saved = true;
                    drawerDirty = false;
                    if (reservationDrawer) {
                        reservationDrawer.hide();
                    }
                    calendar.refetchEvents();
                    return;
                }
                var $failed = $result.find('#reservation-failed');
                var messages = $failed.find('.error > div').map(function () {
                    return $(this).text().trim();
                }).get().filter(Boolean);
                $('#rdSaveError').removeClass('d-none').html(
                    messages.length ? messages.join('<br>') : $failed.text().trim() || 'Save failed.'
                );
            })
            .catch(function () {
                $('#rdSaveError').removeClass('d-none').text(opts.translations.networkErrorRetry);
            })
            .finally(function () {
                setDrawerSaving(false);
            });
    }

    function submitReservationEdit(scope) {
        setDrawerSaving(true);
        $('#rdSaveError').addClass('d-none').empty();

        fetch(opts.reservationUpdateUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: buildEditBody(scope).toString(),
            credentials: 'same-origin',
        })
            .then(function (res) { return res.json().catch(function () { return {}; }); })
            .then(function (json) {
                if (json && json.success) {
                    drawerState.saved = true;
                    drawerDirty = false;
                    if (reservationDrawer) {
                        reservationDrawer.hide();
                    }
                    calendar.refetchEvents();
                    return;
                }
                if (json && json.code === 'reservation_changed') {
                    showDrawerVersionConflict(json.message);
                    return;
                }
                if (json && json.code === 'resource_conflict') {
                    // Someone else just booked the resource/time this Drawer was trying to
                    // save into — refresh the calendar so it's visible, but keep the Drawer
                    // open with the user's input intact rather than closing or discarding it.
                    calendar.refetchEvents();
                }
                var errors = (json && json.errors) || [];
                $('#rdSaveError').removeClass('d-none').html(
                    errors.length ? errors.join('<br>') : 'Save failed.'
                );
            })
            .catch(function () {
                $('#rdSaveError').removeClass('d-none').text(opts.translations.networkErrorRetry);
            })
            .finally(function () {
                setDrawerSaving(false);
            });
    }

    // Someone else changed this reservation since the Drawer loaded it (409 reservation_changed
    // — see Domain/Access/ReservationRepository.php's version check). Never silently overwrite
    // their change or silently discard the user's own in-progress edit: show both options and
    // let the user pick. "重新載入" re-fetches the current server data into the Drawer (discarding
    // local edits); doing nothing leaves the form as-is so the user can copy their text elsewhere
    // before deciding, but resubmitting will keep failing the same way until they reload.
    function showDrawerVersionConflict(message) {
        var $error = $('#rdSaveError').removeClass('d-none').empty();
        $('<div></div>').text(message || opts.translations.reservationChanged).appendTo($error);
        $('<button type="button" class="btn btn-sm btn-warning mt-2"></button>')
            .text(opts.translations.reloadLatest)
            .on('click', function () {
                if (!drawerState || !drawerState.referenceNumber) {
                    return;
                }
                openReservationDrawer({ mode: 'edit', referenceNumber: drawerState.referenceNumber });
            })
            .appendTo($error);
    }

    function submitReservationSave(scope) {
        if (drawerState.mode === 'edit') {
            submitReservationEdit(scope);
        } else {
            submitReservationCreate();
        }
    }

    var $updateScopeModalEl = $('#reservationUpdateScopeModal');
    var reservationUpdateScopeModal = $updateScopeModalEl.length && window.bootstrap ? new bootstrap.Modal($updateScopeModalEl[0]) : null;

    $('#rdSaveBtn').on('click', function () {
        if (!validateDrawerTimes()) {
            return;
        }
        if (!validateRepeatOptions()) {
            return;
        }
        var isEditingRecurring = drawerState.mode === 'edit' && drawerState.echo && drawerState.echo.isRecurring;
        if (isEditingRecurring) {
            $('input[name="rdUpdateScope"][value="full"]').prop('checked', true);
            if (reservationUpdateScopeModal) {
                reservationUpdateScopeModal.show();
            }
            return;
        }
        submitReservationSave('full');
    });

    $('#rdUpdateScopeConfirmBtn').on('click', function () {
        var scope = $('input[name="rdUpdateScope"]:checked').val() || 'full';
        if (reservationUpdateScopeModal) {
            reservationUpdateScopeModal.hide();
        }
        submitReservationSave(scope);
    });

    // "更多選項" no longer navigates away — it opens the same Drawer in create mode,
    // carrying over whatever the user already typed into the quick-create popup.
    $('#qcMoreOptions').on('click', function (e) {
        e.preventDefault();
        var draft = {
            title: $('#qcTitle').val(),
            description: $('#qcDescription').val(),
            startDate: pending ? pending.startDate : '',
            endDate: pending ? pending.endDate : '',
            beginTime: pending ? minutesToHHMMSS(pending.beginMinutes) : '',
            endTime: pending ? minutesToHHMMSS(pending.endMinutes) : '',
            resourceId: pending ? pending.resourceId : null,
        };
        if (quickCreateModal) {
            quickCreateModal.hide();
        }
        openReservationDrawer({ mode: 'create', draft: draft });
    });

    // Repositions the custom #fcNowDot from the REAL, current geometry of the now-indicator
    // line itself — never a fixed pixel guess — so it stays correct across window resize,
    // vertical scroll, and view/date navigation (all of which can change the line's position).
    // Anchored at the line's own left edge (not the axis/day-grid boundary) so the dot always
    // sits at the start of the actual red line, regardless of which day column "today" is.
    // No-ops (hides the dot) when there's no now-indicator line in the currently rendered range
    // (viewing a week/day that doesn't include today, or month view, which has no time grid).
    function positionNowDot() {
        var line = document.querySelector('.fc-timegrid-now-indicator-line');
        // #fcNowDot is a SIBLING of #fullcalendarRoot (not a descendant — FullCalendar owns
        // #fullcalendarRoot's entire innerHTML and would wipe out a child element on its own
        // re-renders), so its CSS positioning ancestor is .fc-frame (position:relative), the
        // nearest element that actually contains both — offsets must be computed against
        // THAT rect, not #fullcalendarRoot's own, or every coordinate is off by whatever the
        // two ancestors don't share (confirmed live: was off by double digits to tens of px).
        var root = document.querySelector('.fc-frame');
        if (!line || !root) {
            $('#fcNowDot').addClass('d-none');
            return;
        }
        var lineRect = line.getBoundingClientRect();
        var rootRect = root.getBoundingClientRect();
        $('#fcNowDot').removeClass('d-none').css({
            left: (lineRect.left - rootRect.left) + 'px',
            top: (lineRect.top + lineRect.height / 2 - rootRect.top) + 'px',
        });
    }

    $(window).on('resize', positionNowDot);
    // The time-grid body's scroller is created/destroyed by FullCalendar itself on every
    // render, so this listens on the stable #fullcalendarRoot ancestor rather than looking up
    // the scroller element directly (which wouldn't survive a re-render) — but native `scroll`
    // events don't bubble, so normal (bubble-phase) event delegation, jQuery's .on(event,
    // selector, handler) included, never actually fires here. Capture-phase listening does see
    // it, since capture runs top-down before the event reaches its target.
    document.getElementById('fullcalendarRoot').addEventListener('scroll', function (e) {
        if (e.target.classList && e.target.classList.contains('fc-scroller-liquid-absolute')) {
            positionNowDot();
        }
    }, true);
    // FullCalendar's own now-indicator line advances on its own internal timer as real time
    // passes; this keeps the dot following it without needing to hook that internal timer.
    setInterval(positionNowDot, 30000);
    // requestAnimationFrame after render()/datesSet fires too early — FullCalendar hasn't
    // always inserted the now-indicator-line/axis-frame elements into the DOM yet at that
    // point (confirmed live: positionNowDot() ran but found them missing, then never got
    // called again until something else happened to trigger it). A MutationObserver reacts to
    // whatever FullCalendar's own DOM insertion actually looks like instead of guessing how
    // many frames to wait, so it's correct regardless of timing.
    // Deliberately synchronous, no RAF/debounce wrapper: FullCalendar tears down and rebuilds
    // the now-indicator-line/axis-frame elements more than once around a render (its own
    // internal now-indicator refresh included), and a "run once on the next frame" debounce
    // could end up reading the DOM in between two of those rebuilds — i.e. exactly the "found
    // nothing, hid the dot, and nothing re-triggered a re-check before the next unrelated
    // mutation" gap this is meant to close. positionNowDot() itself is cheap, so there's no
    // real cost to just running it on every mutation batch instead.
    var nowDotObserver = new MutationObserver(positionNowDot);
    nowDotObserver.observe(document.getElementById('fullcalendarRoot'), {
        childList: true,
        subtree: true,
        // FullCalendar's own now-indicator refresh moves the existing line/arrow elements by
        // updating their `style` attribute in place rather than replacing them — childList
        // alone would miss that.
        attributes: true,
        attributeFilter: ['style'],
    });

    var calendar = new FullCalendar.Calendar(rootEl, {
        // Free/Standard plugins only — no resourceTimeGrid/resourceTimeline/Scheduler.
        initialView: 'timeGridWeek',
        locale: 'zh-tw',
        firstDay: 1, // Monday
        height: '100%',
        timeZone: opts.timezone,
        // FullCalendar's own toolbar is fully replaced by the .cal-toolbar markup in
        // overview_fullcalendar.tpl (same classes/visual language as overview.tpl's
        // toolbar) — wired up via the click handlers and datesSet callback below.
        headerToolbar: false,
        nowIndicator: true,
        scrollTime: '08:00:00',
        scrollTimeReset: false,
        slotDuration: '00:30:00',
        slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false, meridiem: false },
        eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false, meridiem: false },
        allDaySlot: false,
        // Dim individual events that have already ended (matches #page-overview .event.past
        // in overview.css) rather than the whole day — a whole PAST DAY can still have
        // upcoming events on it (e.g. an evening reservation on today's date).
        eventClassNames: function (arg) {
            var now = nowFakeUtcInZone(opts.timezone);
            return arg.event.end && arg.event.end.getTime() < now ? ['fc-event-past'] : [];
        },
        // Month view's default for timed events is a plain dot + text (no color fill) —
        // force the same solid colored block week/day view uses so all three views read
        // as one consistent color language instead of two different event styles.
        eventDisplay: 'block',
        // Force fully separate side-by-side columns for overlapping events — same
        // "no stacking/no z-index trick" requirement the hand-rolled grid's own overlap
        // layout was just built to satisfy (Presenters/OverviewPresenter.php::AssignEventColumns()).
        slotEventOverlap: false,
        editable: false,
        eventStartEditable: false,
        eventDurationEditable: false,
        // Dragging out a range opens the same quick-create modal overview.js's manual
        // mousedown/mousemove/mouseup implementation did — FullCalendar's own
        // .fc-highlight (fixed color, see overview_fullcalendar.css) gives the drag
        // feedback, and slotDuration (30min) already provides the snapping overview.js
        // computed by hand. selectMirror is off: it renders a second, separate
        // .fc-event-mirror preview block on top of .fc-highlight, which is what actually
        // caused the color flicker/jump during drag — .fc-highlight alone is sufficient
        // feedback and doesn't recompute or vary by resource color.
        selectable: true,
        selectMirror: false,
        select: function (info) {
            calendar.unselect();
            var resourceId = defaultResourceId();
            if (!resourceId) {
                return;
            }
            // Month view selects whole days, not a time-of-day range, so there's no
            // begin/end time to read off the selection itself — default to a normal
            // business-hour slot on the clicked day and let the modal's own time pickers
            // handle any adjustment, same as the sidebar "+新增預約" button's default.
            if (info.view.type === 'dayGridMonth') {
                openQuickCreate({
                    resourceId: resourceId,
                    startDate: isoUTC(info.start),
                    endDate: isoUTC(info.start),
                    beginTime: '09:00:00',
                    endTime: '09:30:00',
                });
                return;
            }
            // info.start/info.end are each read independently here (including their OWN date,
            // not just time-of-day) — a drag that crosses midnight (e.g. 7/30 22:00 -> 7/31
            // 02:00) previously collapsed to a single date taken from info.start alone, silently
            // discarding info.end's real date and corrupting the selected range.
            openQuickCreate({
                resourceId: resourceId,
                startDate: isoUTC(info.start),
                endDate: isoUTC(info.end),
                beginTime: pad(info.start.getUTCHours()) + ':' + pad(info.start.getUTCMinutes()) + ':00',
                endTime: pad(info.end.getUTCHours()) + ':' + pad(info.end.getUTCMinutes()) + ':00',
            });
        },
        dayHeaderContent: function (arg) {
            // dayGridMonth's header row is generic (one label per weekday column, not tied
            // to a single date) — showing a day-number circle there would misleadingly
            // imply the header refers to one specific date, so only timeGrid views (week/
            // day) get the dow+circled-number layout.
            if (arg.view.type === 'dayGridMonth') {
                return { html: '<div class="dow">' + weekdayLabel(arg.date) + '</div>' };
            }
            return {
                html: '<div class="dow">' + weekdayLabel(arg.date) + '</div>'
                    + '<div class="num">' + arg.date.getUTCDate() + '</div>',
            };
        },
        // Month view's day-of-month number, in its OWN cell body (not the header row
        // dayHeaderContent above touches) — FullCalendar's zh-tw locale default renders this
        // as e.g. "27日"; overview.tpl's own week/day view never shows that suffix (matches
        // {formatdate...format="j"}, a bare number), so match it here too.
        //
        // Scoped to dayGridMonth only — timeGridWeek/timeGridDay have "day cells" too (the
        // all-day row's cells), and even with allDaySlot:false hiding that row, FullCalendar
        // still calls this for it; returning the day number unconditionally injected a stray
        // "27"/"28"/... into each column that rendered squeezed into the 00:00 slot instead of
        // a proper (hidden) row, since there's no all-day row space reserved for it.
        dayCellContent: function (arg) {
            if (arg.view.type !== 'dayGridMonth') {
                return '';
            }
            return String(arg.date.getUTCDate());
        },
        eventContent: function (arg) {
            // Same wrapper/classes in every view so month view's chips share the exact same
            // color CSS as week/day view's (both read backgroundColor/borderColor from the
            // same event data) — only difference is month view's cells are too short for a
            // second time line, so .rr is simply omitted there rather than switching to a
            // differently-styled element.
            var wrapper = document.createElement('div');
            wrapper.className = 'evc';

            var tt = document.createElement('div');
            tt.className = 'tt';
            tt.textContent = arg.event.title;
            wrapper.appendChild(tt);

            if (arg.view.type !== 'dayGridMonth') {
                var rr = document.createElement('div');
                rr.className = 'rr';
                rr.textContent = arg.timeText;
                wrapper.appendChild(rr);
            }

            return { domNodes: [wrapper] };
        },
        datesSet: function (info) {
            // info.start/info.end are the actual RENDERED range, which for month view is
            // padded out to full weeks (often bleeding into the adjacent month on either
            // side) — using those here made the label read like "6月" instead of "7月" when
            // the visible grid's first row is still June. info.view.currentStart is the
            // unpadded logical range start (exactly the 1st of the target month), which is
            // what the label should reflect; week/day view use the real visible start since
            // there's no such padding for them.
            var labelStart = info.view.type === 'dayGridMonth' ? info.view.currentStart : info.start;
            $('#fcMonthLabel').text(formatMonthLabel(info.view.type, labelStart));

            var todayStr = todayIsoInZone(opts.timezone);
            var isCurrentWeek = todayStr >= isoUTC(info.start) && todayStr < isoUTC(info.end);
            $('#fcTodayBtn').toggleClass('disabled', isCurrentWeek).attr('aria-disabled', isCurrentWeek ? 'true' : null);

            $('.view-picker-item').each(function () {
                $(this).toggleClass('active', $(this).data('view') === info.view.type);
            });
            $('#viewPickerLabel').text($('.view-picker-item[data-view="' + info.view.type + '"]').text());

            if (miniCal) {
                miniCal.setWeekStart(info.start);
            }
            // A view/date change tears down and rebuilds the whole time grid (new axis frame,
            // new now-indicator-line element or none at all) — the nowDotObserver MutationObserver
            // (set up alongside positionNowDot()) reacts to that DOM rebuild on its own, no
            // explicit call needed here.
        },
        eventClick: function (info) {
            // Preserve normal browser behavior for modified clicks (open in new tab, etc.)
            // and non-primary buttons — only intercept a plain left click, same guard
            // overview.js's event-click handler uses.
            var jsEvent = info.jsEvent;
            if (jsEvent.button !== 0 || jsEvent.metaKey || jsEvent.ctrlKey || jsEvent.shiftKey || jsEvent.altKey) {
                return;
            }
            jsEvent.preventDefault();

            var eventStart = info.event.start;
            var eventEnd = info.event.end;
            var timeRangeText = eventStart && eventEnd
                ? formatTimeLabel(eventStart.getUTCHours() * 60 + eventStart.getUTCMinutes())
                    + ' – ' + formatTimeLabel(eventEnd.getUTCHours() * 60 + eventEnd.getUTCMinutes())
                : '';

            var extendedProps = info.event.extendedProps || {};
            var canEdit = !!extendedProps.canEdit;
            var canDelete = !!extendedProps.canDelete;

            $('#epColorBar').css('background', info.event.borderColor || info.event.backgroundColor);
            $('#epTitle').text(info.event.title);
            $('#epTime').text(timeRangeText);
            $('#epResource').text(extendedProps.resourceName || '');
            $('#epOwnerRow').toggleClass('d-none', !extendedProps.ownerName);
            $('#epOwner').text(extendedProps.ownerName || '');
            // "查看詳情" always opens the Drawer (never reservation.php — see the #epViewLink
            // click handler above); everyone gets this link regardless of canEdit, since it's
            // the read/view path. Edit/delete are gated on the server-computed canEdit/canDelete
            // (ReservationAuthorization::CanEdit(), see OverviewFullCalendarEventsPresenter::
            // GetEvents()), never assumed client-side.
            $('#epViewLink').data('reference-number', extendedProps.referenceNumber || null);
            $('#epEditLink')
                .toggleClass('d-none', !canEdit)
                .data('reference-number', extendedProps.referenceNumber || null);
            $('#epDeleteBtn')
                .toggleClass('d-none', !canDelete)
                .data('reference-number', extendedProps.referenceNumber || null)
                .data('is-recurring', !!extendedProps.isRecurring);

            var $container = $('#page-overview-fc');
            var containerOffset = $container.offset();
            var $el = $(info.el);
            var elOffset = $el.offset();
            var top = elOffset.top - containerOffset.top;
            var left = elOffset.left - containerOffset.left + $el.outerWidth() + 8;
            var maxLeft = $container.outerWidth() - 300;
            $eventPopover.css({
                top: top + 'px',
                left: Math.min(left, Math.max(0, maxLeft)) + 'px',
            }).addClass('show');
        },
        events: function (fetchInfo, successCallback, failureCallback) {
            // Scope (mine/all) and resource type (physical/video) are independent filters —
            // both apply together, not one-or-the-other. mine=1 narrows the server-side
            // query to this user's own reservations (any role — see
            // OverviewFullCalendarEventsPresenter::GetEvents()'s $mineOnly branch); the
            // category + checked-resource filter below still applies on top of that, same
            // as when scope is "all".
            var url = opts.eventsUrl
                + '?start=' + encodeURIComponent(fetchInfo.startStr)
                + '&end=' + encodeURIComponent(fetchInfo.endStr)
                + (mineOnly ? '&mine=1' : '');

            fetch(url, { credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(function (allEvents) {
                    successCallback(allEvents.filter(function (event) {
                        var resourceId = event.extendedProps && event.extendedProps.resourceId;
                        if (resourceId === undefined) {
                            return true;
                        }
                        return String(resourceId) === selectedResourceId;
                    }));
                })
                .catch(failureCallback);
        },
    });

    calendar.render();

    // FullCalendar measures its container once at render() time; if that happened before
    // the surrounding flexbox/sidebar had settled into its final size (e.g. web fonts still
    // loading), the header/body columns and the scrollbar-width compensation between them
    // can end up based on stale numbers. A follow-up measurement on the next frame is cheap
    // and fixes that class of drift without waiting for the user to resize the window.
    window.requestAnimationFrame(function () {
        calendar.updateSize();
    });

    initResourcePicker();

    $('#fcPrevBtn').on('click', function (e) {
        e.preventDefault();
        calendar.prev();
    });
    $('#fcNextBtn').on('click', function (e) {
        e.preventDefault();
        calendar.next();
    });
    $('#fcTodayBtn').on('click', function (e) {
        e.preventDefault();
        if (!$(this).hasClass('disabled')) {
            calendar.today();
        }
    });

    // ---- Week/day/month view switcher (dropdown, "月 ▾") — active state + trigger label
    // are kept in sync from datesSet above (the single source of truth for "what view is
    // this"), not set here; this handler only asks FullCalendar to change view. ----
    (function initViewPicker() {
        var $picker = $('#viewPicker');
        var $trigger = $('#viewPickerTrigger');
        var $panel = $('#viewPickerPanel');

        function open() {
            closeAllDropdowns();
            $picker.addClass('open');
            $trigger.attr('aria-expanded', 'true');
        }
        function close() {
            $picker.removeClass('open');
            $trigger.attr('aria-expanded', 'false');
        }
        allDropdownClosers.push(close);

        $trigger.on('click', function (e) {
            e.stopPropagation();
            if ($picker.hasClass('open')) {
                close();
            } else {
                open();
            }
        });
        $panel.on('click', function (e) { e.stopPropagation(); });
        $panel.on('click', '.view-picker-item', function () {
            calendar.changeView($(this).data('view'));
            close();
        });
    })();

    // Cross-day-safe: compares full 'YYYY-MM-DD HH:MM:SS' strings (same approach as the
    // Drawer's validateDrawerTimes()), not just the time-of-day, so a legitimate cross-midnight
    // range (e.g. start 22:00 / end next-day 02:00) isn't mistaken for an invalid one.
    function quickCreateTimesValid() {
        var start = pending.startDate + ' ' + minutesToHHMMSS(pending.beginMinutes);
        var end = pending.endDate + ' ' + minutesToHHMMSS(pending.endMinutes);
        return end > start;
    }

    $('#qcSave').on('click', function () {
        if (!pending) {
            return;
        }
        if (!quickCreateTimesValid()) {
            $('#qcTimeError').removeClass('d-none').text(opts.translations.endBeforeStart);
            return;
        }
        var title = $('#qcTitle').val();
        var description = $('#qcDescription').val();
        var $btn = $(this).prop('disabled', true);
        var $error = $('#qcError').addClass('d-none').empty();

        var body = new URLSearchParams();
        body.set('CSRF_TOKEN', opts.csrfToken);
        body.set('userId', opts.currentUserId);
        body.set('resourceId', pending.resourceId);
        body.set('reservationTitle', title);
        body.set('reservationDescription', description);
        body.set('beginDate', pending.startDate);
        body.set('beginPeriod', minutesToHHMMSS(pending.beginMinutes));
        body.set('endDate', pending.endDate);
        body.set('endPeriod', minutesToHHMMSS(pending.endMinutes));

        fetch(opts.saveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin',
        })
            .then(function (res) { return res.text(); })
            .then(function (html) {
                var $result = $('<div></div>').html(html);
                if ($result.find('#reservation-created').length) {
                    quickCreateModal.hide();
                    calendar.refetchEvents();
                    return;
                }
                var $failed = $result.find('#reservation-failed');
                if ($failed.length) {
                    var messages = $failed.find('.error > div').map(function () {
                        return $(this).text().trim();
                    }).get().filter(Boolean);
                    $error.removeClass('d-none').html(messages.length ? messages.join('<br>') : $failed.text().trim());
                } else {
                    $error.removeClass('d-none').text('Something went wrong — please use ' + '"' + $('#qcMoreOptions').text() + '"' + ' instead.');
                }
            })
            .catch(function () {
                $error.removeClass('d-none').text('Network error — please try again.');
            })
            .finally(function () {
                $btn.prop('disabled', false);
            });
    });
});
