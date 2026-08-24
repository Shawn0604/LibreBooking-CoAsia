function ScheduleManagement(opts) {
  var options = opts;

  var elements = {
    activeId: $('#activeId'),
    scheduleList: $('#scheduleList'),
    scheduleTable: $('#schedulesTable'),

    layoutDialog: $('#changeLayoutDialog'),
    deleteDialog: $('#deleteDialog'),
    addDialog: $('#addDialog'),

    changeLayoutForm: $('#changeLayoutForm'),
    placeholderForm: $('#placeholderForm'),
    deleteForm: $('#deleteForm'),

    addForm: $('#addScheduleForm'),
    addName: $('#addName'),

    reservableEdit: $('#reservableEdit'),
    blockedEdit: $('#blockedEdit'),
    layoutTimezone: $('#layoutTimezone'),
    quickLayoutConfig: $('#quickLayoutConfig'),
    quickLayoutStart: $('#quickLayoutStart'),
    quickLayoutEnd: $('#quickLayoutEnd'),
    createQuickLayout: $('#createQuickLayout'),

    daysVisible: $('#daysVisible'),
    dayOfWeek: $('#dayOfWeek'),
    deleteDestinationScheduleId: $('#targetScheduleId'),
    usesSingleLayout: $('#usesSingleLayout'),

    addScheduleButton: $('#add-schedule'),

    peakTimesDialog: $('#peakTimesDialog'),
    peakTimesForm: $('#peakTimesForm'),
    peakEveryDay: $('#peakEveryDay'),
    peakDayList: $('#peakDayList'),
    peakAllYear: $('#peakAllYear'),
    peakDateRange: $('#peakDateRange'),
    peakAllDay: $('#peakAllDay'),
    peakTimes: $('#peakTimes'),
    deletePeakTimesButton: $('#deletePeakBtn'),
    deletePeakTimes: $('#deletePeakTimes'),

    concurrentForm: $('#concurrentForm'),

    switchLayoutButton: $('.switchLayout'),
    switchLayoutForm: $('#switchLayoutForm'),
    switchLayoutDialog: $('#switchLayoutDialog'),

    layoutSlotForm: $('#layoutSlotForm'),
    slotStartDate: $('#slotStartDate'),
    slotEndDate: $('#slotEndDate'),
    slotId: $('#slotId'),
    deleteCustomLayoutDialog: $('#deleteCustomLayoutDialog'),
    deleteSlotStartDate: $('#deleteSlotStartDate'),
    deleteSlotEndDate: $('#deleteSlotEndDate'),
    cancelDeleteSlot: $('#cancelDeleteSlot'),
    deleteCustomTimeSlotForm: $('#deleteCustomTimeSlotForm'),
    deleteSlot: $('#deleteSlot'),
    confirmCreateSlotDialog: $('#confirmCreateSlotDialog'),
    cancelCreateSlot: $('#cancelCreateSlot'),
  };

  ScheduleManagement.prototype.init = function () {
    elements.scheduleTable.on('click', '.admin-row-edit-btn', function (e) {
      openScheduleDrawer($(this).closest('tr').attr('data-scheduleId'));
    });

    elements.scheduleTable.on('click', '.admin-row-delete-action', function (e) {
      e.preventDefault();
      setActiveScheduleId($(this).closest('tr').attr('data-scheduleId'));
      showDeleteDialog(e);
    });

    elements.scheduleList.on('click', '.saveScheduleHours', function (e) {
      e.preventDefault();
      saveScheduleHours($(this).data('schedule-id'));
    });

    elements.scheduleList.on('change', '.scheduleDayOpen', function (e) {
      var row = $(this).closest('.admin-schedule-day-row');
      row.find('.scheduleDayStart, .scheduleDayEnd').prop('disabled', !$(this).is(':checked'));
    });

    elements.scheduleList.on('click', '.update', function (e) {
      e.preventDefault();
      var id = $(this).closest('.scheduleDetails').attr('data-schedule-id');
      setActiveScheduleId(id);
    });

    elements.scheduleList.on('click', '.changeLayoutButton', function (e) {
      var id = getActiveScheduleId();
      var reservable = $(this).closest('.scheduleDetails').find('.reservableSlots');
      var blocked = $(this).closest('.scheduleDetails').find('.blockedSlots');
      var timezone = $(this).closest('.scheduleDetails').find('.timezone');
      var usesDailyLayouts = $(this).closest('.scheduleDetails').find('.usesDailyLayouts');

      if ($(e.target).data('layout-type') == 0) {
        showChangeLayout(e, reservable, blocked, timezone, usesDailyLayouts.val() == 'false');
      } else {
        showChangeCustomLayout(id);
      }
      return false;
    });

    elements.scheduleList.on('click', '.showAllDailyLayouts', function (e) {
      e.preventDefault();
      $(this).next('.allDailyLayouts').toggle();
    });

    elements.scheduleList.on('click', '.changePeakTimes', function (e) {
      e.preventDefault();
      document.getElementById('peakStartTime').classList.remove('is-invalid');
      document.getElementById('peakEndTime').classList.remove('is-invalid');
      showPeakTimesDialog(getActiveScheduleId());
    });

    elements.scheduleList.on('click', '.toggleConcurrent', function (e) {
      e.preventDefault();
      var toggle = $(e.target);
      var container = toggle.parent('.concurrentContainer');
      toggleConcurrentReservations(getActiveScheduleId(), toggle, container);
    });

    elements.scheduleList.on('click', '.switchLayout', function (e) {
      e.preventDefault();
      $('#switchLayoutTypeId').val($(e.target).data('switch-to'));
      elements.switchLayoutDialog.modal('show');
    });

    elements.deletePeakTimesButton.click(function (e) {
      e.preventDefault();
      elements.deletePeakTimes.val('1');
    });

    $('.save').click(function (e) {
      e.preventDefault();
      e.stopPropagation();
      $(this).closest('form').submit();
    });

    $('.cancel').click(function () {
      $(this).closest('.dialog').dialog('close');
    });

    elements.quickLayoutConfig.change(function () {
      createQuickLayout();
    });

    elements.quickLayoutStart.change(function () {
      createQuickLayout();
    });

    elements.quickLayoutEnd.change(function () {
      createQuickLayout();
    });

    elements.createQuickLayout.click(function (e) {
      e.preventDefault();
      createQuickLayout();
    });

    elements.usesSingleLayout.change(function () {
      toggleLayoutChange($(this).is(':checked'));
    });

    elements.addScheduleButton.click(function (e) {
      e.preventDefault();
      bootstrap.Offcanvas.getOrCreateInstance(elements.addDialog[0]).show();
    });

    elements.addDialog.on('shown.bs.offcanvas', function () {
      elements.addName.focus();
    });

    elements.cancelDeleteSlot.click(function (e) {
      elements.deleteCustomLayoutDialog.hide();
    });

    elements.cancelCreateSlot.click(function (e) {
      elements.confirmCreateSlotDialog.hide();
    });

    $('.autofillBlocked').click(function (e) {
      e.preventDefault();
      autoFillBlocked();
    });

    wireUpPeakTimeToggles();

    ConfigureAsyncForm(
      elements.changeLayoutForm,
      getSubmitCallback(options.changeLayoutAction),
      function () {
        window.location.reload();
      },
      function (result) {
        var errorEl = $('#scheduleHoursError' + getActiveScheduleId());
        errorEl.removeClass('d-none').html(result);
      }
    );
    ConfigureAsyncForm(elements.addForm, getSubmitCallback(options.addAction), null, handleAddError);
    ConfigureAsyncForm(elements.deleteForm, getSubmitCallback(options.deleteAction));
    ConfigureAsyncForm(elements.peakTimesForm, getSubmitCallback(options.peakTimesAction), refreshPeakTimes, null, {
      onBeforeSubmit: validateTimes,
    });
    ConfigureAsyncForm(elements.switchLayoutForm, getSubmitCallback(options.switchLayout));
    ConfigureAsyncForm(elements.deleteCustomTimeSlotForm, getSubmitCallback(options.deleteLayoutSlot), afterDeleteSlot);
  };

  var getSubmitCallback = function (action) {
    return function () {
      return options.submitUrl + '?sid=' + elements.activeId.val() + '&action=' + action;
    };
  };

  var createQuickLayout = function () {
    var intervalMinutes = elements.quickLayoutConfig.val();
    var startTime = elements.quickLayoutStart.val();
    var endTime = elements.quickLayoutEnd.val();

    if (intervalMinutes != '' && startTime != '' && endTime != '') {
      var layout = '';
      var blocked = '';

      if (startTime != '00:00') {
        blocked += '00:00 - ' + startTime + '\n';
      }

      if (endTime != '00:00') {
        blocked += endTime + ' - 00:00';
      }

      var startTimes = startTime.split(':');
      var endTimes = endTime.split(':');

      var currentTime = new Date();
      currentTime.setHours(startTimes[0]);
      currentTime.setMinutes(startTimes[1]);

      var endDateTime = new Date();
      endDateTime.setHours(endTimes[0]);
      endDateTime.setMinutes(endTimes[1]);

      var nextTime = new Date(currentTime);

      var intervalMilliseconds = 60 * 1000 * intervalMinutes;
      while (currentTime.getTime() < endDateTime.getTime()) {
        nextTime.setTime(nextTime.getTime() + intervalMilliseconds);

        layout += getFormattedTime(currentTime) + ' - ';
        layout += getFormattedTime(nextTime) + '\n';

        currentTime.setTime(currentTime.getTime() + intervalMilliseconds);
      }

      $('.reservableEdit:visible', elements.layoutDialog).val(layout);
      $('.blockedEdit:visible', elements.layoutDialog).val(blocked);
    }
  };

  var getFormattedTime = function (date) {
    var hour = date.getHours() < 10 ? '0' + date.getHours() : date.getHours();
    var minute = date.getMinutes() < 10 ? '0' + date.getMinutes() : date.getMinutes();
    return hour + ':' + minute;
  };

  var autoFillBlocked = function () {
    function splitAndTrim(line) {
      return _.map(_.split(line, '-'), _.trim);
    }

    var blocked = '';

    var reservableText = _.trim($('.reservableEdit:visible', elements.layoutDialog).val());
    var reservable = _.split(reservableText, '\n');
    if (reservable.length === 0) {
      $('.blockedEdit:visible', elements.layoutDialog).val('00:00 - 00:00');
      return;
    }

    var startIndex = 0;
    if (!_.startsWith(reservable[0], '00:00') && !_.startsWith(reservable[0], '0:00')) {
      blocked += '00:00 - ' + splitAndTrim(reservable)[0] + '\n';
      startIndex = 1;
    }

    for (var i = startIndex; i < reservable.length; i++) {
      var firstIteration = i === 0;
      var lastIteration = i + 1 === reservable.length;

      if (_.isEmpty(_.trim(reservable[i]))) {
        continue;
      }

      var current = splitAndTrim(reservable[i]);
      var previous = null;
      if (!firstIteration) {
        previous = splitAndTrim(reservable[i - 1]);
      }

      if (!firstIteration && !lastIteration && current[0] != previous[1]) {
        blocked += previous[1] + ' - ' + current[0] + '\n';
      }

      if (lastIteration && current[1] != '00:00') {
        blocked += current[1] + ' - 00:00' + '\n';
      }
    }

    $('.blockedEdit:visible', elements.layoutDialog).val(blocked);
  };

  var handleAddError = function (responseText) {
    $('#addScheduleResults').text(responseText);
    $('#addScheduleResults').show();
  };

  var setActiveScheduleId = function (scheduleId) {
    elements.activeId.val(scheduleId);
  };

  var getActiveScheduleId = function () {
    return elements.activeId.val();
  };

  var showChangeLayout = function (e, reservableDiv, blockedDiv, timezone, usesSingleLayout) {
    elements.changeLayoutForm.find('.validationSummary ').addClass('no-show');
    $.each(reservableDiv, function (index, val) {
      var slots = reformatTimeSlots($(val));
      $('#' + $(val).attr('ref')).val(slots);
    });

    $.each(blockedDiv, function (index, val) {
      var slots = reformatTimeSlots($(val));
      $('#' + $(val).attr('ref')).val(slots);
    });

    elements.layoutTimezone.val(timezone.val());
    elements.usesSingleLayout.prop('checked', false);

    if (usesSingleLayout) {
      elements.usesSingleLayout.prop('checked', true);
    }
    elements.usesSingleLayout.trigger('change');

    elements.layoutDialog.modal('show');
  };

  var toggleLayoutChange = function (useSingleLayout) {
    if (useSingleLayout) {
      $('#dailySlots').hide();
      $('#staticSlots').show();
    } else {
      $('#staticSlots').hide();
      $('#dailySlots').show();
    }
  };

  var openScheduleDrawer = function (id) {
    setActiveScheduleId(id);
    var drawerEl = document.getElementById('scheduleDrawer' + id);
    if (drawerEl) {
      bootstrap.Offcanvas.getOrCreateInstance(drawerEl).show();
    }
  };

  var saveScheduleHours = function (scheduleId) {
    var errorEl = $('#scheduleHoursError' + scheduleId);
    errorEl.addClass('d-none').empty();

    for (var day = 0; day <= 6; day++) {
      var isOpen = $('#scheduleDayOpen' + scheduleId + '_' + day).is(':checked');
      var start = $('#scheduleDayStart' + scheduleId + '_' + day).val();
      var end = $('#scheduleDayEnd' + scheduleId + '_' + day).val();

      if (isOpen && start && end) {
        $('#reservableEdit_' + day).val(start + ' - ' + end);

        // LayoutValidator requires every day's periods to cover the full 24
        // hours with no gaps (first period must start at 00:00, last must
        // end at 00:00) -- same rule createQuickLayout() already fills in
        // for above. A bare reservable range without these leaves a gap
        // whenever it isn't the full day, and the save silently fails.
        var blocked = '';
        if (start !== '00:00') {
          blocked += '00:00 - ' + start + '\n';
        }
        if (end !== '00:00') {
          blocked += end + ' - 00:00';
        }
        $('#blockedEdit_' + day).val(blocked);
      } else {
        $('#reservableEdit_' + day).val('');
        $('#blockedEdit_' + day).val('00:00 - 00:00');
      }
    }

    elements.usesSingleLayout.prop('checked', false);
    elements.layoutTimezone.val($('#scheduleDrawer' + scheduleId + ' .timezone').val());
    setActiveScheduleId(scheduleId);

    elements.changeLayoutForm.submit();
  };

  var showDeleteDialog = function (e) {
    var scheduleId = getActiveScheduleId();
    elements.deleteDestinationScheduleId.children().removeAttr('disabled');
    elements.deleteDestinationScheduleId.children('option[value="' + scheduleId + '"]').attr('disabled', 'disabled');
    elements.deleteDestinationScheduleId.val('');

    elements.deleteDialog.modal('show');
  };

  var reformatTimeSlots = function (div) {
    var text = $.trim(div.text());
    text = text.replace(/\s\s+/g, ' ');
    text = text.replace(/\s*,\s*/g, '\n');
    return text;
  };

  var showPeakTimesDialog = function (scheduleId) {
    var peakPlaceHolder = $('[data-schedule-id=' + scheduleId + ']').find('.peakPlaceHolder');

    var times = peakPlaceHolder.find('.peakTimes');
    var days = peakPlaceHolder.find('.peakDays');
    var months = peakPlaceHolder.find('.peakMonths');

    if (times.length > 0) {
      var allDay = times.data('all-day');
      var startTime = times.data('start-time');
      var endTime = times.data('end-time');

      var everyday = days.data('everyday');
      var days = days.data('weekdays').split(',');

      var allYear = months.data('all-year');
      var beginMonth = months.data('begin-month');
      var beginDay = months.data('begin-day');
      var endMonth = months.data('end-month');
      var endDay = months.data('end-day');

      if (allDay == 1) {
        elements.peakAllDay.prop('checked', true);
      } else {
        elements.peakAllDay.prop('checked', false);
        $('#peakStartTime').val(startTime);
        $('#peakEndTime').val(endTime);
      }

      elements.peakEveryDay.attr('checked', everyday == 1);

      _.each($('#peakDayList').find(':checked'), function (e) {
        $(e).closest('label').button('toggle');
      });

      _.each(days, function (day) {
        $('#peakDay' + day)
          .closest('label')
          .button('toggle');
      });

      if (allYear == 1) {
        elements.peakAllYear.prop('checked', true);
      } else {
        elements.peakAllYear.prop('checked', false);
        $('#peakBeginMonth').val(beginMonth);
        $('#peakBeginDay').val(beginDay);
        $('#peakEndMonth').val(endMonth);
        $('#peakEndDay').val(endDay);
      }

      peakOnAllDayChanged();
      peakOnEveryDayChanged();
      peakOnAllYearChanged();
    }

    wireUpTimePickers(startTime, endTime);

    elements.deletePeakTimes.val('');
    elements.peakTimesDialog.modal('show');
  };

  var peakOnEveryDayChanged = function () {
    if (elements.peakEveryDay.is(':checked')) {
      elements.peakDayList.addClass('no-show');
    } else {
      elements.peakDayList.removeClass('no-show');
    }
  };

  var peakOnAllYearChanged = function () {
    if (elements.peakAllYear.is(':checked')) {
      elements.peakDateRange.addClass('no-show');
    } else {
      elements.peakDateRange.removeClass('no-show');
    }
  };

  var peakOnAllDayChanged = function () {
    if (elements.peakAllDay.is(':checked')) {
      elements.peakTimes.addClass('no-show');
    } else {
      elements.peakTimes.removeClass('no-show');
    }
  };

  var refreshPeakTimes = function (resultHtml) {
    $('[data-schedule-id=' + getActiveScheduleId() + ']')
      .find('.peakPlaceHolder')
      .html(resultHtml);
    elements.peakTimesDialog.modal('hide');
  };

  var wireUpPeakTimeToggles = function () {
    elements.peakEveryDay.on('click', function (e) {
      peakOnEveryDayChanged();
    });

    elements.peakAllYear.on('click', function (e) {
      peakOnAllYearChanged();
    });

    elements.peakAllDay.on('click', function (e) {
      peakOnAllDayChanged();
    });
  };

  var toggleConcurrentReservations = function (scheduleId, toggle, container) {
    var allow = toggle.data('allow') == 1;
    if (allow) {
      container.find('.allowConcurrentYes').addClass('no-show');
      container.find('.allowConcurrentNo').removeClass('no-show');
    } else {
      container.find('.allowConcurrentYes').removeClass('no-show');
      container.find('.allowConcurrentNo').addClass('no-show');
    }
    elements.concurrentForm.submit();

    toggle.data('allow', allow ? '0' : '1');
  };

  var _fullCalendar = null;

  var showChangeCustomLayout = function (scheduleId) {
    $('#customLayoutDialog').unbind();

    function updateEvent(event) {
      elements.slotStartDate.val(dateHelper.formatDate(event.start, true));
      elements.slotEndDate.val(dateHelper.formatDate(event.end, true));
      elements.slotId.val(event.id);
      ajaxPost(
        elements.layoutSlotForm,
        options.submitUrl + '?action=' + options.updateLayoutSlot + '&sid=' + getActiveScheduleId(),
        null,
        function (data) {
          _fullCalendar.refetchEvents();
        }
      );
    }

    $('#customLayoutDialog').unbind('shown.bs.modal');
    $('#customLayoutDialog').on('shown.bs.modal', function () {
      if (_fullCalendar != null) {
        _fullCalendar.destroy();
      }
      var calendarElement = document.getElementById('calendar');
      _fullCalendar = new FullCalendar.Calendar(calendarElement, {
        themeSystem: 'standard',
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
        },
        buttonText: {
          today: opts.calendarOptions.buttonText.today,
        },
        allDaySlot: false,
        initialDate: opts.calendarOptions.defaultDate,
        initialView: 'dayGridMonth',
        locale: (document.documentElement.lang || 'en').replace('_', '-'),
        views: {
          dayGridMonth: { buttonText: opts.calendarOptions.buttonText.month },
          timeGridWeek: { buttonText: opts.calendarOptions.buttonText.week },
          timeGridDay: { buttonText: opts.calendarOptions.buttonText.day },
          listWeek: { buttonText: opts.calendarOptions.buttonText.list || 'List' },
        },
        events: {
          url: opts.calendarOptions.eventsUrl,
          method: 'GET',
          extraParams: {
            dr: 'events',
            sid: scheduleId,
          },
        },
        dateClick: function (info) {
          if (info.view.type == 'dayGridMonth') {
            _fullCalendar.changeView('timeGridDay');
            _fullCalendar.gotoDate(info.date);
          }
        },
        selectable: true,
        selectMirror: true,
        editable: true,
        droppable: true,
        eventOverlap: false,
        select: function (info) {
          if (info.view.type != 'dayGridMonth') {
            elements.confirmCreateSlotDialog.show();
            elements.confirmCreateSlotDialog.position({
              my: 'left bottom',
              at: 'left top',
              of: info.jsEvent,
            });
            $('#confirmCreateOK').unbind('click');
            $('#confirmCreateOK').click(function (e) {
              elements.slotStartDate.val(dateHelper.formatDate(info.start, true));
              elements.slotEndDate.val(dateHelper.formatDate(info.end, true));
              ajaxPost(
                elements.layoutSlotForm,
                options.submitUrl + '?action=' + options.addLayoutSlot + '&sid=' + getActiveScheduleId(),
                null,
                function () {
                  _fullCalendar.refetchEvents();
                  elements.confirmCreateSlotDialog.hide();
                }
              );
            });
          }
        },
        eventClick: function (info) {
          elements.deleteSlotStartDate.val(dateHelper.formatDate(info.event.start, true));
          elements.deleteSlotEndDate.val(dateHelper.formatDate(info.event.end, true));
          elements.deleteCustomLayoutDialog.show();
          elements.deleteCustomLayoutDialog.position({
            my: 'left bottom',
            at: 'left top',
            of: info.jsEvent,
          });
        },
        eventDrop: function (info) {
          updateEvent(info.event);
        },
        eventResize: function (info) {
          updateEvent(info.event);
        },
      });

      _fullCalendar.render();
    });

    $('#customLayoutDialog').modal('show');
  };

  function afterDeleteSlot() {
    elements.deleteCustomLayoutDialog.hide();
    _fullCalendar.refetchEvents();
  }

  function wireUpTimePickers(startTime, endTime) {
    document.querySelectorAll('.timepicker').forEach((el) => {
      if (el.id === 'peakStartTime') {
        dateHelper.initTimePicker(el, startTime);
      } else if (el.id === 'peakEndTime') {
        dateHelper.initTimePicker(el, endTime);
      } else {
        dateHelper.initTimePicker(el);
      }
    });
  }

  var validateTimes = function () {
    if (document.getElementById('peakAllDay').checked) {
      return true;
    }

    return dateHelper.ValidateTimeRangeElements(
      document.getElementById('peakStartTime'),
      document.getElementById('peakEndTime')
    );
  };
}
