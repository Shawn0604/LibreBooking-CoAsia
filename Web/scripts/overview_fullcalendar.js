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
    // as it was: Quick Create's own independent category state (activeResourceCategory,
    // further down) still needs it and must not be touched by anything in this section. ----
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

    // ---- Google-Calendar-style "更多選項"/edit: navigate to the classic full reservation
    // page (reservation.php) instead of an in-page editor, carrying the in-progress draft
    // (or "come back to the same calendar view" state) via sessionStorage rather than the
    // URL — single read-then-discard, so a stray reload of the classic page later never
    // re-applies stale data, and there's no URL-length/encoding concern for a description
    // or a longer participant list. ----
    var QUICK_CREATE_DRAFT_KEY = 'overviewFullCalendarQuickCreateDraft';
    var RETURN_STATE_KEY = 'overviewFullCalendarReturnState';

    function saveQuickCreateDraft(draft) {
        try {
            sessionStorage.setItem(QUICK_CREATE_DRAFT_KEY, JSON.stringify(draft));
        } catch (e) {
            // Private browsing / quota-exceeded — the classic page just opens blank instead
            // of prefilled, same as if "更多選項" had been clicked with nothing typed yet.
        }
    }

    function isoDateFromCalendarDate(d) {
        var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    }

    // Called right before navigating away to the classic page (create or edit) — captures
    // "what the calendar looked like" so returning from that page (Cancel, or a successful
    // save's returnUrl) can restore it. The selected resource already survives on its own via
    // localStorage (setSelectedResource() above); only the date and view type need this.
    function saveCalendarReturnState() {
        try {
            sessionStorage.setItem(RETURN_STATE_KEY, JSON.stringify({
                date: isoDateFromCalendarDate(calendar.getDate()),
                view: calendar.view.type,
            }));
        } catch (e) {}
    }

    // Single-use: read once, remove immediately, regardless of whether it turns out to hold
    // anything usable — a second, unrelated page load must never see leftover state from a
    // navigation that already consumed it.
    function consumeCalendarReturnState() {
        var raw = null;
        try {
            raw = sessionStorage.getItem(RETURN_STATE_KEY);
            sessionStorage.removeItem(RETURN_STATE_KEY);
        } catch (e) {
            return null;
        }
        if (!raw) {
            return null;
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
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
    // UI is a dropdown (same trigger/panel pattern as the view picker, wired up alongside it
    // further below via initScopePicker()) rather than the old labeled toggle switch — only
    // the control changed, not what it drives.
    var mineOnly = false;

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

    // "2026-07-30" -> "7月30日（四）" — Quick Create's date field
    // overlays this on top of a real (but text-transparent) <input type="date">, since a
    // native date input's own displayed text can't be reformatted directly (see
    // .qc-date-display/.rd-date-display in overview_fullcalendar.css).
    function formatDateLabel(isoDate) {
        if (!isoDate) {
            return '';
        }
        var parts = isoDate.split('-');
        var y = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10);
        var d = parseInt(parts[2], 10);
        var date = new Date(Date.UTC(y, m - 1, d));
        return m + '月' + d + '日（' + weekdayLabel(date) + '）';
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

    // ---- Quick-create modal (same booking UX as Web/scripts/overview.js's version, reusing
    // the identical #quickCreateModal markup/CSS and ajax/reservation_save.php endpoint —
    // the only structural difference is how a time range gets selected in the first place:
    // FullCalendar's own selectable:true + select() callback below, instead of a hand-rolled
    // mousedown/mousemove/mouseup pixel-math drag implementation. ----
    var $modalEl = $('#quickCreateModal');
    var quickCreateModal = $modalEl.length && window.bootstrap ? new bootstrap.Modal($modalEl[0]) : null;
    // { resourceId, startDate, endDate, beginMinutes, endMinutes } — startDate/endDate are
    // always equal (reservations can't cross midnight, see ResourceCrossDayRule.php); kept as
    // two keys rather than one so moreOptionsUrl()/buildQuickCreateDraft() etc don't need to
    // special-case a single-date shape.
    var pending = null;

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

    // 24-hour "HH:MM", zero-padded, no 上午/下午 — used everywhere a time gets displayed
    // (Quick Create's pills, the event popover's time range) so it reads the same way across
    // the whole page.
    function formatTimeLabel(minutes) {
        var h = Math.floor(minutes / 60) % 24;
        var m = minutes % 60;
        return pad(h) + ':' + pad(m);
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
        // Preserve the current duration when nudging the start time.
        var duration = Math.max(30, pending.endMinutes - pending.beginMinutes);
        pending.beginMinutes = minutes;
        pending.endMinutes = Math.min(24 * 60 - 30, minutes + duration);
        refreshTimeUI();
    });
    var endPicker = TimeDropdown('#qcEndPill', '#qcEndDropdown', function (minutes) {
        pending.endMinutes = minutes;
        refreshTimeUI();
    });
    allDropdownClosers.push(startPicker.close, endPicker.close);

    function refreshTimeUI() {
        // Full 00:00-23:30 range, no artificial duration cap (previously capped at +8 hours).
        // End time must always be strictly after the start time — reservations can't cross
        // midnight (ResourceCrossDayRule.php), so there's no "later day" case to special-case
        // here any more.
        var minEndMinutes = pending.beginMinutes + 30;
        // A start-time change that pushes past the old end time can leave pending.endMinutes
        // stale/invalid — snap it back into range rather than silently submitting an end time
        // that's actually before the start time.
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
            var label = formatTimeLabel(e) + '（' + formatDuration(e - pending.beginMinutes) + '）';
            endOptions.push({ minutes: e, label: label });
        }
        endPicker.render(endOptions, pending.endMinutes);

        $('#qcTimeError').addClass('d-none').empty();

        $('#qcStartDateDisplay').text(formatDateLabel(pending.startDate));
        $('#qcMoreOptions').attr('href', moreOptionsUrl());
        scheduleAvailabilityCheck();
    }

    // Used by Quick Create's #qcAvailability indicator — a "query, then only apply the
    // response if nothing's moved on since" check against the shared `pending` object.
    // requestTracker is called at resolve time (not captured up front) so it always reflects
    // whatever's actually showing right now — that's what lets a stale, late-arriving
    // response recognize itself as stale and get discarded instead of overwriting a newer one.
    function checkResourceAvailability(resourceId, startDateTime, endDateTime, requestTracker, onResult, excludeReferenceNumber) {
        if (!resourceId || !startDateTime || !endDateTime) {
            return;
        }
        var requestKey = resourceId + '|' + startDateTime + '|' + endDateTime;
        var url = opts.availabilityUrl
            + '?resourceId=' + encodeURIComponent(resourceId)
            + '&start=' + encodeURIComponent(startDateTime)
            + '&end=' + encodeURIComponent(endDateTime);
        if (excludeReferenceNumber) {
            // Editing an existing reservation would otherwise always see its own current slot
            // as a "conflict" and report itself unavailable — irrelevant for Quick Create,
            // which never passes this (it only ever creates new reservations).
            url += '&excludeReferenceNumber=' + encodeURIComponent(excludeReferenceNumber);
        }

        fetch(url, { credentials: 'same-origin' })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (json) {
                if (!json || requestTracker() !== requestKey) {
                    return;
                }
                onResult(json.available);
            })
            .catch(function () {
                // Silently leave whatever the availability line was already showing — a failed
                // check isn't worth surfacing as a hard error in a quick-create/edit popup, and
                // the save itself still re-validates server-side regardless.
            });
    }

    function renderAvailability($container, available) {
        var $icon = $container.find('i');
        $container.toggleClass('unavailable', !available);
        $icon.toggleClass('bi-check-circle-fill', available);
        $icon.toggleClass('bi-x-circle-fill', !available);
        $container.find('span').text(available ? opts.translations.resourceAvailable : opts.translations.resourceUnavailable);
    }

    // Debounced — a user stepping through the time dropdown or typing in the date field can
    // fire refreshTimeUI() several times in quick succession; only the last one within 300ms
    // actually hits the server, same principle as any type-ahead search box.
    var qcAvailabilityTimer = null;
    function scheduleAvailabilityCheck() {
        if (qcAvailabilityTimer) {
            clearTimeout(qcAvailabilityTimer);
        }
        qcAvailabilityTimer = setTimeout(checkAvailability, 300);
    }

    function checkAvailability() {
        if (!pending || !pending.resourceId) {
            return;
        }
        checkResourceAvailability(
            pending.resourceId,
            pending.startDate + ' ' + minutesToHHMMSS(pending.beginMinutes),
            pending.endDate + ' ' + minutesToHHMMSS(pending.endMinutes),
            function () {
                return pending
                    ? pending.resourceId + '|' + pending.startDate + ' ' + minutesToHHMMSS(pending.beginMinutes)
                        + '|' + pending.endDate + ' ' + minutesToHHMMSS(pending.endMinutes)
                    : null;
            },
            function (available) { renderAvailability($('#qcAvailability'), available); }
        );
    }

    function resourceNameFor(resourceId) {
        return (opts.resourceNames || {})[resourceId] || '';
    }

    var activeResourceCategory = 'physical';

    // Same searchable/grouped picker component the toolbar's own resource picker uses
    // (.resource-picker*, styled under #page-overview-fc in overview_fullcalendar.css — this
    // modal is a descendant of it) — a second, independent instance keyed off
    // pending.resourceId instead of the toolbar's selectedResourceId, since picking a resource
    // for the reservation being drafted has nothing to do with which resource the calendar
    // itself is currently showing.
    function updateQcResourcePickerTrigger() {
        var $items = $('#qcResourcePickerList .resource-picker-item');
        $items.removeClass('selected');
        $items.filter('[data-resource-id="' + pending.resourceId + '"]').addClass('selected');

        var $dot = $('#qcResourcePickerDot');
        var color = (opts.resourceColors || {})[pending.resourceId];
        $dot.toggle(!!color);
        $dot.get(0).style.setProperty('--dot-color', color || '');

        $('#qcResourcePickerLabel').text(resourceNameFor(pending.resourceId));
    }

    function initQcResourcePicker() {
        var $picker = $('#qcResourcePicker');
        var $trigger = $('#qcResourcePickerTrigger');
        var $panel = $('#qcResourcePickerPanel');
        var $search = $('#qcResourcePickerSearch');

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
            pending.resourceId = String($(this).data('resource-id'));
            activeResourceCategory = categoryOf(pending.resourceId);
            // refreshResourceUI() (not just updateQcResourcePickerTrigger() directly) — it also
            // re-points #qcMoreOptions' href at the newly picked resource, so a middle-click/
            // open-in-new-tab on "更多選項" right after switching resources doesn't land on the
            // stale one (the JS-intercepted normal click path already reads pending.resourceId
            // fresh regardless, but the raw href needs its own update for that fallback case).
            refreshResourceUI();
            close();
        });

        $search.on('input', function () {
            var term = $(this).val().trim().toLowerCase();
            var anyVisible = { physical: false, video: false };
            $('#qcResourcePickerList .resource-picker-item[data-section]').each(function () {
                var label = $(this).find('.resource-picker-item-label').text().toLowerCase();
                var match = label.indexOf(term) !== -1;
                $(this).toggleClass('d-none', !match);
                if (match) {
                    anyVisible[$(this).data('section')] = true;
                }
            });
            $('#qcResourcePickerList .resource-picker-section-label').each(function () {
                $(this).toggleClass('d-none', !anyVisible[$(this).data('section')]);
            });
            $('#qcResourcePickerEmpty').toggleClass('d-none', anyVisible.physical || anyVisible.video);
        });
    }
    initQcResourcePicker();

    function refreshResourceUI() {
        updateQcResourcePickerTrigger();
        $('#qcMoreOptions').attr('href', moreOptionsUrl());
        scheduleAvailabilityCheck();
    }

    function moreOptionsUrl() {
        return opts.reservationUrl +
            '?rid=' + encodeURIComponent(pending.resourceId) +
            '&sd=' + pending.startDate + '+' + minutesToHHMMSS(pending.beginMinutes) +
            '&ed=' + pending.endDate + '+' + minutesToHHMMSS(pending.endMinutes);
    }

    function openQuickCreate(p) {
        // endDate always mirrors startDate here regardless of what the caller passed —
        // reservations can't cross midnight (ResourceCrossDayRule.php), and there's no end-
        // date input in the modal any more for it to diverge through.
        var startDate = p.startDate || p.date;
        if (!quickCreateModal) {
            window.location = opts.reservationUrl +
                '?rid=' + encodeURIComponent(p.resourceId) + '&sd=' + startDate + '+' + p.beginTime + '&ed=' + startDate + '+' + p.endTime;
            return; // no Bootstrap JS available — fall back to the full form
        }
        var beginMinutes = parseInt(p.beginTime.slice(0, 2), 10) * 60 + parseInt(p.beginTime.slice(3, 5), 10);
        var endMinutes = parseInt(p.endTime.slice(0, 2), 10) * 60 + parseInt(p.endTime.slice(3, 5), 10);
        pending = { resourceId: p.resourceId, startDate: startDate, endDate: startDate, beginMinutes: beginMinutes, endMinutes: endMinutes };
        activeResourceCategory = categoryOf(p.resourceId);

        $('#qcTitle').val('');
        $('#qcDescription').val('');
        $('#qcStartDate').val(startDate);
        $('#qcError').addClass('d-none').empty();
        $('#qcTimeError').addClass('d-none').empty();
        refreshTimeUI();
        refreshResourceUI();
        qcParticipantPicker.reset();
        quickCreateModal.show();
        setTimeout(function () { $('#qcTitle').trigger('focus'); }, 200);
    }

    $('#qcStartDate').on('change', function () {
        pending.startDate = $(this).val();
        pending.endDate = pending.startDate;
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

    // Edit AND "查看詳情" (View Details) both navigate to the classic reservation.php?rn=...
    // page (edit.tpl) — there's no separate client-computed read-only mode any more; the
    // server decides view/edit/approve (ExistingReservationPage::GetTemplateName(), based on
    // ReservationAuthorization::CanEdit()) exactly like every other entry point into that page
    // always has. Both links already carry a real href (set alongside the other popover
    // fields further down, see the eventClick handler), so this only needs to save the
    // "come back here" calendar state first — the browser's normal navigation handles the
    // rest, including a plain middle-click/open-in-new-tab working correctly.
    $('#epEditLink, #epViewLink').on('click', function () {
        saveCalendarReturnState();
        closeEventPopover();
    });

    // ---- Delete confirmation — reuses the existing ajax/reservation_delete.php pipeline
    // (Presenters/Reservation/ReservationDeletePresenter.php, SeriesUpdateScope) rather than
    // a new endpoint/format; view/edit navigate to reservation.php (see above), only delete
    // needs a request built from here, since there's no form on this page to submit. ----
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

    // Quick Create's participant picker — factory shared with the classic reservation form
    // (Web/scripts/participant-picker.js, {jsfile}-included above) so both use the identical
    // debounced-search/dropdown/removable-chip behavior, endpoint, and visual style. Required
    // participants only — no optional-participants picker here (see the "更多選項" comment
    // further down and the removed markup this used to pair with); accepts external (non-user)
    // email invitees when guest participation is allowed, and excludes the current user.
    var qcParticipantPicker = createParticipantPicker({
        input: '#qcParticipantSearchInput',
        results: '#qcParticipantSearchResults',
        chipList: '#qcParticipantChipList',
        wrapper: '#qcParticipantSearch',
        searchUrl: opts.participantSearchUrl,
        noMatchesText: opts.translations.noMatchesFound,
        allowExternal: opts.allowGuestParticipation,
        inviteEmailLabel: opts.translations.inviteExternalEmail,
        externalBadgeText: opts.translations.externalParticipantBadge,
        excludeIds: [opts.currentUserId],
        excludeEmails: [opts.currentUserEmail],
    });

    function buildQuickCreateDraft() {
        return {
            title: $('#qcTitle').val(),
            description: $('#qcDescription').val(),
            startDate: pending ? pending.startDate : '',
            endDate: pending ? pending.endDate : '',
            beginTime: pending ? minutesToHHMMSS(pending.beginMinutes) : '',
            endTime: pending ? minutesToHHMMSS(pending.endMinutes) : '',
            resourceId: pending ? pending.resourceId : null,
            participants: qcParticipantPicker.getParticipants(),
        };
    }

    // Google-Calendar-style "更多選項": hand off to the classic full reservation page instead
    // of an in-page editor. Resource/date/time still travel via the URL (moreOptionsUrl(),
    // same as a plain middle-click on this same link already used) — NewReservationPage
    // already reads rid/sd/ed natively, no change needed there. Title/description/participants
    // have no such query-param support, so those go through sessionStorage instead (see
    // saveQuickCreateDraft() above); create.tpl reads and immediately discards it on load.
    $('#qcMoreOptions').on('click', function (e) {
        e.preventDefault();
        saveQuickCreateDraft(buildQuickCreateDraft());
        saveCalendarReturnState();
        window.location.href = $(this).attr('href');
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

    // Restores "what the calendar looked like" after navigating to the classic full
    // reservation page and back (see saveCalendarReturnState() above) — a plain page reload
    // otherwise always reset to today/week, losing whatever date or view the user was on.
    var returnState = consumeCalendarReturnState();

    var calendar = new FullCalendar.Calendar(rootEl, {
        // Free/Standard plugins only — no resourceTimeGrid/resourceTimeline/Scheduler.
        initialView: returnState ? returnState.view : 'timeGridWeek',
        initialDate: returnState ? returnState.date : undefined,
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
            // timeGridWeek lets a drag run sideways across day columns (e.g. Mon 22:00 -> Tue
            // 02:00) — reservations can't cross midnight (ResourceCrossDayRule.php), so clamp
            // the selection to the end of the start day instead of carrying info.end's later
            // date through to the modal.
            var startDate = isoUTC(info.start);
            var endDate = isoUTC(info.end);
            var endHours = info.end.getUTCHours();
            var endMinutes = info.end.getUTCMinutes();
            if (endDate !== startDate) {
                endDate = startDate;
                endHours = 23;
                endMinutes = 30;
            }
            openQuickCreate({
                resourceId: resourceId,
                startDate: startDate,
                endDate: endDate,
                beginTime: pad(info.start.getUTCHours()) + ':' + pad(info.start.getUTCMinutes()) + ':00',
                endTime: pad(endHours) + ':' + pad(endMinutes) + ':00',
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
        // Web/css/librebooking.css's global .fc-event:hover forces every event to the same
        // --hiliteReservation green (!important, shared with the legacy overview.tpl grid —
        // not touched here since other pages still rely on it). Stashing each event's own
        // color on the element itself lets the scoped #fullcalendarRoot .fc-event:hover rule
        // in overview_fullcalendar.css darken THAT color instead, so Shanghai stays green,
        // Singapore stays blue/purple, etc.
        eventDidMount: function (info) {
            info.el.style.setProperty('--event-base-bg', info.event.backgroundColor);
            info.el.style.setProperty('--event-base-border', info.event.borderColor);
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
            // Both link straight to the classic reservation.php?rn=... page (see the
            // #epEditLink/#epViewLink click handler above) — "查看詳情" is shown to everyone
            // regardless of canEdit (it's the read/view path); edit/delete stay gated on the
            // server-computed canEdit/canDelete (ReservationAuthorization::CanEdit(), see
            // OverviewFullCalendarEventsPresenter::GetEvents()), never assumed client-side.
            var eventReservationUrl = opts.reservationUrl + '?rn=' + encodeURIComponent(extendedProps.referenceNumber || '');
            $('#epViewLink').attr('href', eventReservationUrl);
            $('#epEditLink')
                .toggleClass('d-none', !canEdit)
                .attr('href', eventReservationUrl);
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

    // Exposed so tpl/globalheader.tpl's sidebar collapse/expand toggle can call updateSize()
    // on this exact instance directly once the width transition finishes, instead of only
    // dispatching a synthetic window 'resize' event and hoping FullCalendar's own debounced
    // resize listener (windowResize:true, the default) picks it up. Harmless on any other
    // page — the toggle script guards on this being present before touching it.
    window.overviewFullCalendarInstance = calendar;

    // Exposed so the sidebar's restored "+新增預約" button (tpl/globalheader.tpl, rendered
    // site-wide) can open this exact page's quick-create modal instead of only being able to
    // link to the classic reservation.php form. Defaults to today + the next half-hour slot
    // on the currently selected resource — the same inputs a blank-slot click on the calendar
    // itself would produce, just without an actual click to read a date/time/resource from.
    window.overviewFullCalendarOpenQuickCreate = function () {
        var resourceId = defaultResourceId();
        if (!resourceId) {
            return;
        }
        var today = todayIsoInZone(opts.timezone);
        var now = new Date(nowFakeUtcInZone(opts.timezone));
        var nowMinutes = now.getUTCHours() * 60 + now.getUTCMinutes();
        var beginMinutes = Math.min(Math.ceil(nowMinutes / 30) * 30, 24 * 60 - 30);
        openQuickCreate({
            resourceId: resourceId,
            startDate: today,
            endDate: today,
            beginTime: minutesToHHMMSS(beginMinutes),
            endTime: minutesToHHMMSS(beginMinutes + 30),
        });
    };

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

    // ---- Reservation scope filter (全部／只看我的, dropdown "全部 ▾") — same trigger/
    // panel pattern as the view picker above; sets the `mineOnly` flag declared earlier
    // (read by the events() callback / OverviewFullCalendarEventsPresenter's $mineOnly
    // branch) instead of changing calendar view. ----
    (function initScopePicker() {
        var $picker = $('#scopePicker');
        var $trigger = $('#scopePickerTrigger');
        var $panel = $('#scopePickerPanel');
        var $label = $('#scopePickerLabel');

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
            var $item = $(this);
            mineOnly = $item.data('scope') === 'mine';
            $panel.find('.view-picker-item').removeClass('active');
            $item.addClass('active');
            $label.text($item.text());
            close();
            if (typeof calendar !== 'undefined') {
                calendar.refetchEvents();
            }
        });
    })();

    // Cross-day-safe: compares full 'YYYY-MM-DD HH:MM:SS' strings, not just the time-of-day,
    // so a legitimate cross-midnight range (e.g. start 22:00 / end next-day 02:00) isn't
    // mistaken for an invalid one.
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
        qcParticipantPicker.getParticipants().forEach(function (p) {
            body.append(p.type === 'external' ? 'guestParticipationList[]' : 'participantList[]', p.type === 'external' ? p.email : p.id);
        });

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
