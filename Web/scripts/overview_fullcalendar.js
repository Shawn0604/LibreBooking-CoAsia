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

            $('#epColorBar').css('background', info.event.borderColor || info.event.backgroundColor);
            $('#epTitle').text(info.event.title);
            $('#epTime').text(timeRangeText);
            $('#epResource').text((info.event.extendedProps && info.event.extendedProps.resourceName) || '');
            $('#epViewLink').attr('href', info.event.url || '#');

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
            var url = opts.eventsUrl
                + '?start=' + encodeURIComponent(fetchInfo.startStr)
                + '&end=' + encodeURIComponent(fetchInfo.endStr);

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

    // Same resource checklist already in the shared sidebar (tpl/globalheader.tpl) —
    // re-filter by re-running the events function above rather than hiding DOM nodes.
    $('#resourceSidebar input[type="checkbox"][data-resource]').on('change', function () {
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
