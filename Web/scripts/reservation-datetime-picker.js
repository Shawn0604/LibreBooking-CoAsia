// Shared date/time picker pieces — originally built for the Overview page's Quick Create
// modal (Web/scripts/overview_fullcalendar.js), now also used by the classic reservation form
// (tpl/Reservation/create.tpl) so both share the identical 30-minute-interval time picker
// instead of the classic form's old schedule-period <select> (which could be any granularity
// depending on the resource's configured layout, not necessarily 30 minutes). Declared as plain
// top-level functions (not wrapped in a $(function(){...}) closure, unlike the page scripts
// that consume them) so they're genuinely global and callable from either page's own script —
// same pattern as participant-picker.js.

// "HH:MM:SS" — matches Time::ToString() on the PHP side (lib/Common/Time.php), which is what
// the classic form's beginPeriod/endPeriod hidden fields have always expected.
function minutesToHHMMSS(minutes) {
    var h = Math.floor(minutes / 60) % 24;
    var m = minutes % 60;
    return pad2(h) + ':' + pad2(m) + ':00';
}

// 24-hour "HH:MM", zero-padded, no 上午/下午 — used everywhere a time gets displayed (Quick
// Create's pills, the event popover's time range, the classic form's pills) so it reads the
// same way across the whole app.
function formatTimeLabel(minutes) {
    var h = Math.floor(minutes / 60) % 24;
    var m = minutes % 60;
    return pad2(h) + ':' + pad2(m);
}

function formatDuration(minutes) {
    if (minutes < 60) {
        return minutes + ' 分鐘';
    }
    var hours = minutes / 60;
    return (hours % 1 === 0 ? hours : hours.toFixed(1)) + ' 小時';
}

function pad2(n) {
    return n < 10 ? '0' + n : '' + n;
}

// Flips a dropdown to grow upward (.open-upward) when there isn't enough room below the
// trigger inside the viewport for it to open downward without getting clipped by/pushing past
// its container's edge. Measured fresh on every open rather than assumed from CSS alone, since
// the trigger can be scrolled to anywhere in the viewport. $dropdown isn't display:none while
// "closed" (it's opacity/visibility-toggled), so its real height is measurable before deciding
// which way to open it.
function positionDropdownVertically($trigger, $dropdown) {
    $dropdown.removeClass('open-upward');
    var triggerRect = $trigger[0].getBoundingClientRect();
    var dropdownHeight = $dropdown[0].offsetHeight;
    var spaceBelow = window.innerHeight - triggerRect.bottom;
    var spaceAbove = triggerRect.top;
    if (spaceBelow < dropdownHeight && spaceAbove > spaceBelow) {
        $dropdown.addClass('open-upward');
    }
}

// Generic pill + dropdown control shared by every start/end time picker. onBeforeOpen (optional)
// lets the caller close any other open dropdowns on its own page first — kept as a callback
// rather than a hardcoded closeAllDropdowns() call so this factory doesn't depend on which
// page's own dropdown registry it's running alongside.
function TimeDropdown(pillId, dropdownId, onPick, onBeforeOpen) {
    var $pill = $(pillId);
    var $dropdown = $(dropdownId);

    function close() {
        $dropdown.removeClass('show');
        $pill.attr('aria-expanded', 'false');
    }

    function open() {
        if (typeof onBeforeOpen === 'function') {
            onBeforeOpen();
        }
        positionDropdownVertically($pill, $dropdown);
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

var RDT_WEEKDAY_LABELS = ['日', '一', '二', '三', '四', '五', '六'];

function weekdayLabel(date) {
    return RDT_WEEKDAY_LABELS[date.getUTCDay()];
}

// "7月30日（四）" from an ISO 'YYYY-MM-DD' string — the shared display format for the native
// <input type="date"> both pages use underneath their own formatted display span.
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

// Every 30-minute mark in a day, 00:00 through 23:30 — the start-time picker's full option
// list on both pages.
function buildStartTimeOptions() {
    var options = [];
    for (var m = 0; m < 24 * 60; m += 30) {
        options.push({ minutes: m, label: formatTimeLabel(m) });
    }
    return options;
}

// End-time options from minEndMinutes (inclusive) through 23:30, each labelled with the
// resulting duration relative to beginMinutes — same "at least 30 minutes after start" rule
// both pickers use, no per-resource minimum-length filtering (SchedulePeriodRule still
// validates server-side; this list is deliberately just the fixed 30-minute grid).
function buildEndTimeOptions(beginMinutes, minEndMinutes) {
    var options = [];
    for (var e = minEndMinutes; e <= 24 * 60 - 30; e += 30) {
        options.push({ minutes: e, label: formatTimeLabel(e) + '（' + formatDuration(e - beginMinutes) + '）' });
    }
    return options;
}
