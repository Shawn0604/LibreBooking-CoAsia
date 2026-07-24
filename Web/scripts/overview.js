$(function () {
    // ---- Physical/video calendar tabs: switch which resource category the grid + sidebar
    // checklist reflect (only one category's events/checkboxes are shown at a time). ----
    var activeCalCategory = 'physical';

    function categoryOf(resourceId) {
        var groups = window.overviewOpts.resourceGroups || { physical: [], video: [] };
        var isVideo = groups.video.some(function (r) { return String(r.id) === String(resourceId); });
        return isVideo ? 'video' : 'physical';
    }

    function applyCategoryFilter() {
        $('#page-overview .event').each(function () {
            var $event = $(this);
            var resourceId = String($event.data('resource'));
            var inCategory = categoryOf(resourceId) === activeCalCategory;
            var $checkbox = $('#resourceSidebar input[data-resource="' + resourceId + '"]');
            var checked = $checkbox.length ? $checkbox.prop('checked') : true;
            $event.toggleClass('chip-hidden', !inCategory || !checked);
        });
    }

    function setCalCategory(category) {
        activeCalCategory = category;
        $('#calCategoryTabs .cal-category-tab').each(function () {
            $(this).toggleClass('active', $(this).data('category') === category);
        });
        $('#navResGroupPhysical').toggleClass('d-none', category !== 'physical');
        $('#navResGroupVideo').toggleClass('d-none', category !== 'video');
        applyCategoryFilter();
    }

    $('#calCategoryTabs .cal-category-tab').on('click', function () {
        var category = $(this).data('category');
        if (category !== activeCalCategory) {
            setCalCategory(category);
        }
    });

    setCalCategory(activeCalCategory);

    // ---- Resource checklist: multi-select show/hide (Google Calendar's "my calendars" model) ----
    $('#resourceSidebar input[type="checkbox"][data-resource]').on('change', function () {
        var $checkbox = $(this);
        var resourceId = String($checkbox.data('resource'));
        var $events = $('#page-overview .event[data-resource="' + resourceId + '"]');
        $events.toggleClass('chip-hidden', !$checkbox.prop('checked'));
    });

    // ---- Default resource for quick-create actions (drag-select, sidebar "+新增預約") ----
    // Prefers whichever checked checkbox belongs to the active category; falls back to the
    // category's first resource so quick-create still works before any checkbox is touched.
    function defaultResourceId() {
        var groups = window.overviewOpts.resourceGroups || { physical: [], video: [] };
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

    var $modalEl = $('#quickCreateModal');
    var quickCreateModal = $modalEl.length && window.bootstrap ? new bootstrap.Modal($modalEl[0]) : null;
    var pending = null; // { resourceId, date, beginMinutes, endMinutes }

    // Explicit close handler in addition to data-bs-dismiss — belt and suspenders, since the
    // close button sits inside the same flex row as controls that stop click propagation.
    $modalEl.find('.qc-close').on('click', function () {
        if (quickCreateModal) {
            quickCreateModal.hide();
        }
    });

    var WEEKDAY_LABELS = ['日', '一', '二', '三', '四', '五', '六'];

    function formatDateLabel(dateStr) {
        var parts = dateStr.split('-');
        var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        return (parseInt(parts[1], 10)) + '月' + (parseInt(parts[2], 10)) + '日（週' + WEEKDAY_LABELS[d.getDay()] + '）';
    }

    // ---- Time-of-day formatting (matches Google Calendar's own "上午/下午 H:MM" labels) ----
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

    // ---- Generic pill + dropdown control shared by the start/end time pickers ----
    function TimeDropdown(pillId, dropdownId, onPick) {
        var $pill = $(pillId);
        var $dropdown = $(dropdownId);

        function close() {
            $dropdown.removeClass('show');
            $pill.attr('aria-expanded', 'false');
        }

        function open() {
            closeAllDropdowns();
            // Scroll to the selected option before the fade-in starts so nothing jumps mid-transition.
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

    // ---- Resource category tabs + picker ----
    function resourceCategoryFor(resourceId) {
        var groups = window.overviewOpts.resourceGroups || { physical: [], video: [] };
        var isVideo = groups.video.some(function (r) { return String(r.id) === String(resourceId); });
        return isVideo ? 'video' : 'physical';
    }

    function resourceNameFor(resourceId) {
        return (window.overviewOpts.resourceNames || {})[resourceId] || '';
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

        var groups = window.overviewOpts.resourceGroups || { physical: [], video: [] };
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
        var list = (window.overviewOpts.resourceGroups || {})[category] || [];
        if (list.length) {
            pending.resourceId = list[0].id;
        }
        refreshResourceUI();
    });

    function moreOptionsUrl() {
        return window.overviewOpts.reservationUrl +
            '?rid=' + encodeURIComponent(pending.resourceId) +
            '&sd=' + pending.date + '+' + minutesToHHMMSS(pending.beginMinutes) +
            '&ed=' + pending.date + '+' + minutesToHHMMSS(pending.endMinutes);
    }

    function openQuickCreate(p) {
        if (!quickCreateModal) {
            window.location = window.overviewOpts.reservationUrl +
                '?rid=' + encodeURIComponent(p.resourceId) + '&sd=' + p.date + '+' + p.beginTime + '&ed=' + p.date + '+' + p.endTime;
            return; // no Bootstrap JS available — fall back to the full form
        }
        var beginMinutes = parseInt(p.beginTime.slice(0, 2), 10) * 60 + parseInt(p.beginTime.slice(3, 5), 10);
        var endMinutes = parseInt(p.endTime.slice(0, 2), 10) * 60 + parseInt(p.endTime.slice(3, 5), 10);
        pending = { resourceId: p.resourceId, date: p.date, beginMinutes: beginMinutes, endMinutes: endMinutes };
        activeResourceCategory = resourceCategoryFor(p.resourceId);

        $('#qcTitle').val('');
        $('#qcDescription').val('');
        $('#qcDateLabel').text(formatDateLabel(p.date));
        $('#qcError').addClass('d-none').empty();
        refreshTimeUI();
        refreshResourceUI();
        quickCreateModal.show();
        setTimeout(function () { $('#qcTitle').trigger('focus'); }, 200);
    }

    function isoDate(d) {
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
            date: isoDate(start),
            beginTime: pad(start.getHours()) + ':' + pad(start.getMinutes()) + ':00',
            endTime: pad(end.getHours()) + ':' + pad(end.getMinutes()) + ':00'
        });
    });

    // ---- Empty-cell hover preview + drag-to-select a time range (Google lets you drag out
    // a range instead of only ever getting a fixed 30-minute block from a single click) ----
    var $slotPreview = $('<div class="slot-preview d-none"><span class="slot-preview-label"></span></div>').appendTo('#page-overview .week-grid');
    var drag = null; // { $col, startMinutes }

    function slotMinutesFromEvent(e, $col) {
        var dayStart = parseInt($col.data('day-start'), 10);
        var dayEnd = parseInt($col.data('day-end'), 10);
        var windowMinutes = (dayEnd - dayStart) * 60;
        var offsetY = e.pageY - $col.offset().top;
        var ratio = Math.min(1, Math.max(0, offsetY / $col.outerHeight()));
        var minutesFromTop = Math.round((ratio * windowMinutes) / 30) * 30;
        return dayStart * 60 + minutesFromTop;
    }

    function showSlotPreview($col, startTotalMinutes, endTotalMinutes) {
        var dayStart = parseInt($col.data('day-start'), 10);
        var dayEnd = parseInt($col.data('day-end'), 10);
        var windowMinutes = (dayEnd - dayStart) * 60;
        var top = ((startTotalMinutes - dayStart * 60) / windowMinutes) * 100;
        var height = ((endTotalMinutes - startTotalMinutes) / windowMinutes) * 100;

        $slotPreview
            .css({
                top: top + '%',
                height: height + '%',
                left: $col.position().left + 3,
                width: $col.outerWidth() - 6
            })
            .removeClass('d-none');
        $slotPreview.find('.slot-preview-label').text(formatTimeLabel(startTotalMinutes) + ' – ' + formatTimeLabel(endTotalMinutes));
    }

    function hideSlotPreview() {
        $slotPreview.addClass('d-none');
    }

    // Google only shows the range box once you're actually dragging — it doesn't track
    // the cursor on plain hover — so the preview only ever appears between mousedown and
    // mouseup below.
    $('#page-overview .day-col').on('mousedown', function (e) {
        if (e.button !== 0 || $(e.target).closest('.event').length) {
            return;
        }
        var $col = $(this);
        drag = { $col: $col, startMinutes: slotMinutesFromEvent(e, $col) };
        showSlotPreview($col, drag.startMinutes, drag.startMinutes + 30);
        e.preventDefault();
    });

    $(document).on('mousemove', function (e) {
        if (!drag) {
            return;
        }
        var current = slotMinutesFromEvent(e, drag.$col);
        var lo = Math.min(drag.startMinutes, current);
        var hi = Math.max(drag.startMinutes, current + 30);
        showSlotPreview(drag.$col, lo, hi);
    });

    $(document).on('mouseup', function (e) {
        if (!drag) {
            return;
        }
        var $col = drag.$col;
        var current = slotMinutesFromEvent(e, $col);
        var startTotalMinutes = Math.min(drag.startMinutes, current);
        var endTotalMinutes = Math.max(drag.startMinutes + 30, current + 30);
        drag = null;
        hideSlotPreview();

        var resourceId = defaultResourceId();
        if (!resourceId) {
            return;
        }

        openQuickCreate({
            resourceId: resourceId,
            date: $col.data('date'),
            beginTime: minutesToHHMMSS(startTotalMinutes),
            endTime: minutesToHHMMSS(endTotalMinutes)
        });
    });

    // ---- Existing event: quick-view popover instead of an immediate full-page jump ----
    var $eventPopover = $('#eventPopover');

    function closeEventPopover() {
        $eventPopover.removeClass('show');
    }

    $('#page-overview .event').on('click', function (e) {
        // Preserve normal browser behavior for modified clicks (open in new tab, etc.) and
        // non-primary buttons — only intercept a plain left click.
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
            return;
        }
        e.preventDefault();
        var $event = $(this);

        $('#epColorBar').css('background', $event.css('border-left-color'));
        $('#epTitle').text($event.data('title'));
        $('#epTime').text($event.data('time-range'));
        $('#epResource').text($event.data('resource-name'));
        $('#epViewLink').attr('href', $event.attr('href'));

        // Positioned relative to #page-overview (position:relative) — not $event's own
        // offsetParent (.day-col), which would misplace the popover since #eventPopover
        // isn't nested inside .day-col.
        var containerOffset = $('#page-overview').offset();
        var offset = $event.offset();
        var top = offset.top - containerOffset.top;
        var left = offset.left - containerOffset.left + $event.outerWidth() + 8;
        var maxLeft = $('#page-overview').outerWidth() - 300;
        $eventPopover.css({
            top: top + 'px',
            left: Math.min(left, Math.max(0, maxLeft)) + 'px'
        }).addClass('show');
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('#eventPopover, .event').length) {
            closeEventPopover();
        }
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
        body.set('CSRF_TOKEN', window.overviewOpts.csrfToken);
        body.set('userId', window.overviewOpts.currentUserId);
        body.set('resourceId', pending.resourceId);
        body.set('reservationTitle', title);
        body.set('reservationDescription', description);
        body.set('beginDate', pending.date);
        body.set('beginPeriod', minutesToHHMMSS(pending.beginMinutes));
        body.set('endDate', pending.date);
        body.set('endPeriod', minutesToHHMMSS(pending.endMinutes));

        fetch(window.overviewOpts.saveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin'
        })
            .then(function (res) { return res.text(); })
            .then(function (html) {
                var $result = $('<div></div>').html(html);
                if ($result.find('#reservation-created').length) {
                    window.location.reload();
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

    // ---- Current-time indicator line ----
    (function positionNowLine() {
        var $line = $('#nowLine');
        if (!$line.length) {
            return;
        }
        var dayStart = window.overviewOpts.dayStartHour;
        var dayEnd = window.overviewOpts.dayEndHour;
        var windowMinutes = (dayEnd - dayStart) * 60;
        var now = new Date();
        var minutesFromStart = (now.getHours() * 60 + now.getMinutes()) - (dayStart * 60);
        if (minutesFromStart < 0 || minutesFromStart > windowMinutes) {
            $line.hide();
            return;
        }
        $line.css('top', ((minutesFromStart / windowMinutes) * 100) + '%');
    })();

    // ---- Auto-scroll to business hours on load (grid covers 0-24h; landing on midnight
    // like a plain top-of-page scroll would hide every normal-hours booking) ----
    (function scrollToBusinessHours() {
        var $col = $('#page-overview .day-col').first();
        if (!$col.length) {
            return;
        }
        var dayStart = window.overviewOpts.dayStartHour;
        var dayEnd = window.overviewOpts.dayEndHour;
        var windowMinutes = (dayEnd - dayStart) * 60;
        var targetHour = 7; // scroll to just before typical 08:00 business hours open
        var ratio = Math.max(0, Math.min(1, (targetHour * 60 - dayStart * 60) / windowMinutes));
        var targetY = $col.offset().top + $col.outerHeight() * ratio;
        window.scrollTo({ top: Math.max(0, targetY - 80), behavior: 'auto' });
    })();

    // ---- Mini month calendar ----
    (function miniCalendar() {
        var $label = $('#miniCalLabel');
        var $grid = $('#miniCalGrid');
        if (!$grid.length) {
            return;
        }

        function parseISODate(s) {
            var parts = s.split('-');
            return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
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

        var weekStart = parseISODate(window.overviewOpts.weekStart);
        var weekDates = [];
        for (var i = 0; i < 7; i++) {
            weekDates.push(addDays(weekStart, i).getTime());
        }

        var today = new Date();
        today.setHours(0, 0, 0, 0);

        var viewYear = weekStart.getFullYear();
        var viewMonth = weekStart.getMonth();

        function render() {
            $label.text(viewYear + '年' + (viewMonth + 1) + '月');
            $grid.empty();

            var firstOfMonth = new Date(viewYear, viewMonth, 1);
            var gridStart = mondayOfWeek(firstOfMonth);

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
                        var monday = mondayOfWeek(targetDate);
                        window.location = window.overviewOpts.overviewUrl + '?sd=' + isoDate(monday);
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

        render();
    })();
});
