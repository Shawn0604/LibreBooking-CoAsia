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

    function checkedResourceIds() {
        var ids = {};
        $('#resourceSidebar input[type="checkbox"][data-resource]:checked').each(function () {
            ids[String($(this).data('resource'))] = true;
        });
        return ids;
    }

    // ---- Physical/video category tabs — same "one category's resources shown at a time"
    // model as Web/scripts/overview.js's setCalCategory(), driven by the same
    // resourceGroups shape (tpl/Overview/overview_fullcalendar.tpl mirrors overview.tpl's
    // window.overviewOpts.resourceGroups output). ----
    var activeCalCategory = 'physical';

    function categoryOf(resourceId) {
        var groups = opts.resourceGroups || { physical: [], video: [] };
        var isVideo = groups.video.some(function (r) { return String(r.id) === String(resourceId); });
        return isVideo ? 'video' : 'physical';
    }

    // ---- Resource checklist is single-select here (per category): users expect "click a
    // room, see only that room's bookings", not the multi-resource color-overlay this
    // checkbox markup was originally built for. The markup/CSS (globalheader.tpl,
    // "checked" on every box) is shared with overview.php, which still wants real
    // multi-select — so exclusivity is enforced purely in JS on this page, without
    // touching the shared template. ----
    function $resourceCheckboxesIn(category) {
        return $('#resourceSidebar input[type="checkbox"][data-resource]').filter(function () {
            return categoryOf($(this).data('resource')) === category;
        });
    }

    function selectSingleResource($checkbox) {
        var category = categoryOf($checkbox.data('resource'));
        $resourceCheckboxesIn(category).each(function () {
            this.checked = (this === $checkbox.get(0));
        });
    }

    function initSingleSelectResources() {
        ['physical', 'video'].forEach(function (category) {
            var $inCategory = $resourceCheckboxesIn(category);
            if (!$inCategory.length) {
                return;
            }
            var $toSelect = $inCategory.filter(':checked').first();
            if (!$toSelect.length) {
                $toSelect = $inCategory.first();
            }
            selectSingleResource($toSelect);
        });
    }

    function setCalCategory(category) {
        activeCalCategory = category;
        $('#fcCategoryTabs .cal-category-tab').each(function () {
            $(this).toggleClass('active', $(this).data('category') === category);
        });
        $('#navResGroupPhysical').toggleClass('d-none', category !== 'physical');
        $('#navResGroupVideo').toggleClass('d-none', category !== 'video');
        if (typeof calendar !== 'undefined') {
            calendar.refetchEvents();
        }
    }

    $('#fcCategoryTabs .cal-category-tab').on('click', function () {
        var category = $(this).data('category');
        if (category !== activeCalCategory) {
            setCalCategory(category);
        }
    });

    // ---- Reservation scope (全體／只看我的) — a SEPARATE dimension from resource type.
    // Independent of activeCalCategory: switching scope must not touch the resource
    // category, the sidebar's selected resource, the calendar's current date/view, or its
    // scroll position — it only changes whether the fetched events are further narrowed to
    // this user's own reservations (see the events() callback below, and
    // OverviewFullCalendarEventsPresenter::GetEvents()'s $mineOnly branch on the server).
    var mineOnly = false;

    function setReservationScope(scope) {
        mineOnly = (scope === 'mine');
        $('#fcScopeTabs .cal-category-tab').each(function () {
            $(this).toggleClass('active', $(this).data('scope') === scope);
        });
        if (typeof calendar !== 'undefined') {
            calendar.refetchEvents();
        }
    }

    $('#fcScopeTabs .cal-category-tab').on('click', function () {
        var scope = $(this).data('scope');
        if ((scope === 'mine') !== mineOnly) {
            setReservationScope(scope);
        }
    });

    // ---- Default resource for quick-create actions (drag-select, sidebar "+新增預約") ----
    // Same rule as Web/scripts/overview.js's defaultResourceId(): prefers whichever checked
    // checkbox belongs to the active category; falls back to the category's first resource
    // so quick-create still works before any checkbox is touched.
    function defaultResourceId() {
        var groups = opts.resourceGroups || { physical: [], video: [] };
        var activeList = groups[activeCalCategory] || [];
        var activeIds = activeList.map(function (r) { return String(r.id); });

        var $firstChecked = $('#resourceSidebar input[type="checkbox"][data-resource]:checked').filter(function () {
            return activeIds.indexOf(String($(this).data('resource'))) !== -1;
        }).first();
        if ($firstChecked.length) {
            return $firstChecked.data('resource');
        }
        if (activeList.length) {
            return activeList[0].id;
        }
        return null;
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

    // Same three-case format as tpl/Overview/overview.tpl's .month-label: same month,
    // same year but different month, or different year — always "Y年n月" per side.
    function formatMonthLabel(start, lastDayOfWeek) {
        var sy = start.getUTCFullYear();
        var sm = start.getUTCMonth() + 1;
        var ey = lastDayOfWeek.getUTCFullYear();
        var em = lastDayOfWeek.getUTCMonth() + 1;

        if (sy === ey && sm === em) {
            return sy + '年' + sm + '月';
        }
        if (sy === ey) {
            return sy + '年' + sm + '月－' + em + '月';
        }
        return sy + '年' + sm + '月－' + ey + '年' + em + '月';
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
    var pending = null; // { resourceId, date, beginMinutes, endMinutes }

    $modalEl.find('.qc-close').on('click', function () {
        if (quickCreateModal) {
            quickCreateModal.hide();
        }
    });

    function formatDateLabel(dateStr) {
        var parts = dateStr.split('-');
        var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        return (parseInt(parts[1], 10)) + '月' + (parseInt(parts[2], 10)) + '日（週' + WEEKDAY_LABELS[d.getDay()] + '）';
    }

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
        var duration = Math.max(30, pending.endMinutes - pending.beginMinutes);
        pending.beginMinutes = minutes;
        pending.endMinutes = minutes + duration;
        refreshTimeUI();
    });
    var endPicker = TimeDropdown('#qcEndPill', '#qcEndDropdown', function (minutes) {
        pending.endMinutes = minutes;
        refreshTimeUI();
    });
    allDropdownClosers.push(startPicker.close, endPicker.close);

    function refreshTimeUI() {
        $('#qcStartPill').text(formatTimeLabel(pending.beginMinutes));
        $('#qcEndPill').text(formatTimeLabel(pending.endMinutes));

        var startOptions = [];
        for (var m = 0; m < 24 * 60; m += 30) {
            startOptions.push({ minutes: m, label: formatTimeLabel(m) });
        }
        startPicker.render(startOptions, pending.beginMinutes);

        var endOptions = [];
        var cap = Math.min(24 * 60, pending.beginMinutes + 8 * 60);
        for (var e = pending.beginMinutes + 30; e <= cap; e += 30) {
            endOptions.push({ minutes: e, label: formatTimeLabel(e) + '（' + formatDuration(e - pending.beginMinutes) + '）' });
        }
        endPicker.render(endOptions, pending.endMinutes);

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
            '&sd=' + pending.date + '+' + minutesToHHMMSS(pending.beginMinutes) +
            '&ed=' + pending.date + '+' + minutesToHHMMSS(pending.endMinutes);
    }

    function openQuickCreate(p) {
        if (!quickCreateModal) {
            window.location = opts.reservationUrl +
                '?rid=' + encodeURIComponent(p.resourceId) + '&sd=' + p.date + '+' + p.beginTime + '&ed=' + p.date + '+' + p.endTime;
            return; // no Bootstrap JS available — fall back to the full form
        }
        var beginMinutes = parseInt(p.beginTime.slice(0, 2), 10) * 60 + parseInt(p.beginTime.slice(3, 5), 10);
        var endMinutes = parseInt(p.endTime.slice(0, 2), 10) * 60 + parseInt(p.endTime.slice(3, 5), 10);
        pending = { resourceId: p.resourceId, date: p.date, beginMinutes: beginMinutes, endMinutes: endMinutes };
        activeResourceCategory = categoryOf(p.resourceId);

        $('#qcTitle').val('');
        $('#qcDescription').val('');
        $('#qcDateLabel').text(formatDateLabel(p.date));
        $('#qcError').addClass('d-none').empty();
        refreshTimeUI();
        refreshResourceUI();
        quickCreateModal.show();
        setTimeout(function () { $('#qcTitle').trigger('focus'); }, 200);
    }

    function isoDateLocal(d) {
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    }

    // ---- Sidebar "+新增預約" button: same quick-create modal, defaulted to the next half-hour slot ----
    $('.create-btn').on('click', function (e) {
        var resourceId = defaultResourceId();
        if (!quickCreateModal || !resourceId) {
            return; // no modal (not on this page) or nothing selected — let the plain link to reservation.php go through
        }
        e.preventDefault();

        var now = new Date();
        var start = new Date(now.getFullYear(), now.getMonth(), now.getDate(), now.getHours(), 0, 0);
        start.setMinutes(now.getMinutes() < 30 ? 30 : 60);
        var end = new Date(start.getTime() + 30 * 60000);

        openQuickCreate({
            resourceId: resourceId,
            date: isoDateLocal(start),
            beginTime: pad(start.getHours()) + ':' + pad(start.getMinutes()) + ':00',
            endTime: pad(end.getHours()) + ':' + pad(end.getMinutes()) + ':00'
        });
    });

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

    // Edit opens the reservation Drawer in place — never navigates to reservation.php for
    // the normal flow (openReservationDrawer is defined further down; safe to reference here
    // since this handler only runs on a later click, same pattern setCalCategory() already
    // uses for the `calendar` variable it references before its own declaration).
    $('#epEditLink').on('click', function (e) {
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
    // a new endpoint/format. Edit reuses the existing full reservation.php form entirely
    // (info.event.url); only delete needs a request built from here, since there's no form
    // on this page to submit. ----
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
        $('#rdRecurringSummary, #rdParticipantsSummary, #rdRemindersSummary').addClass('d-none');
        $('#rdLoadError').addClass('d-none').empty();
        $('#rdSaveError').addClass('d-none').empty();
        $('#rdTimeError').addClass('d-none').empty();
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

    function repeatSummaryText(repeat) {
        var labels = { daily: '每天', weekly: '每週', monthly: '每月', yearly: '每年', custom: '自訂' };
        return labels[repeat.type] || repeat.type;
    }

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

        if (data.isRecurring) {
            $('#rdRecurringSummary').removeClass('d-none');
            $('#rdRecurringSummaryText').text(repeatSummaryText(data.repeat) + '重複');
        }

        var peopleCount = (data.participants || []).length + (data.invitees || []).length;
        if (peopleCount > 0) {
            $('#rdParticipantsSummary').removeClass('d-none');
            $('#rdParticipantsSummaryText').text(peopleCount + ' 位參與者（更多選項可管理）');
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
            $('#rdStartDate').val(draft.date || '');
            $('#rdEndDate').val(draft.date || '');
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
        body.set('repeatOptions', 'none');
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
            date: pending ? pending.date : '',
            beginTime: pending ? minutesToHHMMSS(pending.beginMinutes) : '',
            endTime: pending ? minutesToHHMMSS(pending.endMinutes) : '',
            resourceId: pending ? pending.resourceId : null,
        };
        if (quickCreateModal) {
            quickCreateModal.hide();
        }
        openReservationDrawer({ mode: 'create', draft: draft });
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
        // .fc-highlight gives the drag feedback, and slotDuration (30min) already
        // provides the snapping overview.js computed by hand.
        selectable: true,
        selectMirror: true,
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
                    date: isoUTC(info.start),
                    beginTime: '09:00:00',
                    endTime: '09:30:00',
                });
                return;
            }
            openQuickCreate({
                resourceId: resourceId,
                date: isoUTC(info.start),
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
        dayCellContent: function (arg) {
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
            // side) — using those here made the label read like "2026年6月－8月" instead of
            // just "7月". info.view.currentStart/currentEnd are the unpadded logical range
            // (exactly the 1st through the last day of the target month), which is what the
            // label should reflect; week/day view still use the real visible range since
            // showing a week that spans two months IS meant to say so.
            var labelStart = info.view.type === 'dayGridMonth' ? info.view.currentStart : info.start;
            var labelEnd = info.view.type === 'dayGridMonth' ? info.view.currentEnd : info.end;
            var lastDayOfWeek = new Date(labelEnd.getTime());
            lastDayOfWeek.setUTCDate(lastDayOfWeek.getUTCDate() - 1);
            $('#fcMonthLabel').text(formatMonthLabel(labelStart, lastDayOfWeek));

            var todayStr = todayIsoInZone(opts.timezone);
            var isCurrentWeek = todayStr >= isoUTC(info.start) && todayStr < isoUTC(info.end);
            $('#fcTodayBtn').toggleClass('disabled', isCurrentWeek).attr('aria-disabled', isCurrentWeek ? 'true' : null);

            $('#fcViewTabs .cal-view-tab').each(function () {
                $(this).toggleClass('active', $(this).data('view') === info.view.type);
            });

            if (miniCal) {
                miniCal.setWeekStart(info.start);
            }
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
            $('#epViewLink').attr('href', info.event.url || '#');
            // Other people's reservations only ever get "查看詳情" — edit/delete are gated on
            // the server-computed canEdit/canDelete (ReservationAuthorization::CanEdit(), see
            // OverviewFullCalendarEventsPresenter::GetEvents()), never assumed client-side.
            $('#epEditLink')
                .attr('href', info.event.url || '#')
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
                    var checked = checkedResourceIds();
                    successCallback(allEvents.filter(function (event) {
                        var resourceId = event.extendedProps && event.extendedProps.resourceId;
                        if (resourceId === undefined) {
                            return true;
                        }
                        return categoryOf(resourceId) === activeCalCategory && checked[String(resourceId)];
                    }));
                })
                .catch(failureCallback);
        },
    });

    initSingleSelectResources();
    calendar.render();

    // FullCalendar measures its container once at render() time; if that happened before
    // the surrounding flexbox/sidebar had settled into its final size (e.g. web fonts still
    // loading), the header/body columns and the scrollbar-width compensation between them
    // can end up based on stale numbers. A follow-up measurement on the next frame is cheap
    // and fixes that class of drift without waiting for the user to resize the window.
    window.requestAnimationFrame(function () {
        calendar.updateSize();
    });

    setCalCategory(activeCalCategory);

    // Same resource checklist already in the shared sidebar (tpl/globalheader.tpl), but
    // single-select here (see initSingleSelectResources above): checking a box exclusively
    // selects it within its category; unchecking the only selected box is a no-op (a
    // category always has exactly one resource selected).
    $('#resourceSidebar input[type="checkbox"][data-resource]').on('change', function () {
        if (this.checked) {
            selectSingleResource($(this));
        } else {
            this.checked = true;
        }
        calendar.refetchEvents();
    });

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

    // ---- Week/day/month view switcher ----
    $('#fcViewTabs .cal-view-tab').on('click', function () {
        var view = $(this).data('view');
        $('#fcViewTabs .cal-view-tab').toggleClass('active', false);
        $(this).addClass('active');
        calendar.changeView(view);
    });

    $('#qcSave').on('click', function () {
        if (!pending) {
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
        body.set('beginDate', pending.date);
        body.set('beginPeriod', minutesToHHMMSS(pending.beginMinutes));
        body.set('endDate', pending.date);
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
