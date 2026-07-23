$(function () {
    // ---- Resource checklist: multi-select show/hide (Google Calendar's "my calendars" model) ----
    $('#resourceSidebar input[type="checkbox"][data-resource]').on('change', function () {
        var $checkbox = $(this);
        var resourceId = String($checkbox.data('resource'));
        var $events = $('#page-overview .event[data-resource="' + resourceId + '"]');
        $events.toggleClass('chip-hidden', !$checkbox.prop('checked'));
    });

    // ---- Click empty grid cell to create a reservation ----
    function firstCheckedResourceId() {
        var $first = $('#resourceSidebar input[type="checkbox"][data-resource]:checked').first();
        return $first.length ? $first.data('resource') : null;
    }

    function pad(n) {
        return n < 10 ? '0' + n : '' + n;
    }

    var $modalEl = $('#quickCreateModal');
    var quickCreateModal = $modalEl.length && window.bootstrap ? new bootstrap.Modal($modalEl[0]) : null;
    var pending = null; // { resourceId, date, beginTime, endTime }

    function resourceName(resourceId) {
        return (window.overviewOpts.resourceNames || {})[resourceId] || '';
    }

    function formatWhen(date, beginTime, endTime) {
        return date + '  ' + beginTime.slice(0, 5) + ' – ' + endTime.slice(0, 5);
    }

    function moreOptionsUrl(p) {
        return window.overviewOpts.reservationUrl +
            '?rid=' + encodeURIComponent(p.resourceId) +
            '&sd=' + p.date + '+' + p.beginTime +
            '&ed=' + p.date + '+' + p.endTime;
    }

    function openQuickCreate(p) {
        if (!quickCreateModal) {
            window.location = moreOptionsUrl(p); // no Bootstrap JS available — fall back to the full form
            return;
        }
        pending = p;
        $('#qcTitle').val('');
        $('#qcWhen').text(formatWhen(p.date, p.beginTime, p.endTime));
        $('#qcResource').text(resourceName(p.resourceId));
        $('#qcMoreOptions').attr('href', moreOptionsUrl(p));
        $('#qcError').addClass('d-none').empty();
        quickCreateModal.show();
        setTimeout(function () { $('#qcTitle').trigger('focus'); }, 200);
    }

    function isoDate(d) {
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    }

    // ---- Sidebar "+新增預約" button: same quick-create modal, defaulted to the next half-hour slot ----
    $('.create-btn').on('click', function (e) {
        var resourceId = firstCheckedResourceId();
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

    $('#page-overview .day-col').on('click', function (e) {
        if ($(e.target).closest('.event').length) {
            return; // let existing reservation links navigate normally
        }

        var $col = $(this);
        var date = $col.data('date');
        var dayStart = parseInt($col.data('day-start'), 10);
        var dayEnd = parseInt($col.data('day-end'), 10);
        var windowMinutes = (dayEnd - dayStart) * 60;

        var offsetY = e.pageY - $col.offset().top;
        var ratio = Math.min(1, Math.max(0, offsetY / $col.outerHeight()));
        var minutesFromStart = Math.round((ratio * windowMinutes) / 30) * 30;

        var totalMinutes = dayStart * 60 + minutesFromStart;
        var startH = Math.floor(totalMinutes / 60) % 24;
        var startM = totalMinutes % 60;

        var endTotal = totalMinutes + 30;
        var endH = Math.floor(endTotal / 60) % 24;
        var endM = endTotal % 60;

        var resourceId = firstCheckedResourceId();
        if (!resourceId) {
            return;
        }

        openQuickCreate({
            resourceId: resourceId,
            date: date,
            beginTime: pad(startH) + ':' + pad(startM) + ':00',
            endTime: pad(endH) + ':' + pad(endM) + ':00'
        });
    });

    $('#qcSave').on('click', function () {
        if (!pending) {
            return;
        }
        var title = $('#qcTitle').val();
        var $btn = $(this).prop('disabled', true);
        var $error = $('#qcError').addClass('d-none').empty();

        var body = new URLSearchParams();
        body.set('CSRF_TOKEN', window.overviewOpts.csrfToken);
        body.set('userId', window.overviewOpts.currentUserId);
        body.set('resourceId', pending.resourceId);
        body.set('reservationTitle', title);
        body.set('beginDate', pending.date);
        body.set('beginPeriod', pending.beginTime);
        body.set('endDate', pending.date);
        body.set('endPeriod', pending.endTime);

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
