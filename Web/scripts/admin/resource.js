function ResourceManagement(opts) {
  var options = opts;

  var elements = {
    activeId: $('#activeId'),
    resourcesList: $('#resourceList'),
    resourceTable: $('#resourcesTable'),

    imageDialog: $('#imageDialog'),
    statusDialog: $('#statusDialog'),
    durationDialog: $('#durationDialog'),
    capacityDialog: $('#capacityDialog'),
    accessDialog: $('#accessDialog'),

    imageForm: $('#imageForm'),
    removeImageForm: $('#removeImageForm'),
    defaultImageForm: $('#defaultImageForm'),
    deleteForm: $('#deleteForm'),
    durationForm: $('#durationForm'),
    capacityForm: $('#capacityForm'),
    accessForm: $('#accessForm'),
    statusForm: $('#statusForm'),

    statusReasons: '.reasonId',
    statusOptions: '.statusId',
    addStatusReason: '.addStatusReason',
    newStatusReason: '.newStatusReason',
    existingStatusReason: '.existingStatusReason',
    resourceStatusReason: '.resourceStatusReason',
    addStatusIcon: '.addStatusIcon',

    addForm: $('#addResourceForm'),
    statusOptionsFilter: $('#resourceStatusIdFilter'),
    statusReasonsFilter: $('#resourceReasonIdFilter'),
    filterTable: $('#filterForm'),
    filterButton: $('#filter'),
    clearFilterButton: $('#clearFilter'),

    bulkUpdatePromptButton: $('#bulkUpdatePromptButton'),
    bulkUpdateDialog: $('#bulkUpdateDialog'),
    bulkUpdateList: $('#bulkUpdateList'),
    bulkUpdateForm: $('#bulkUpdateForm'),
    bulkEditStatusOptions: $('#bulkEditStatusId'),
    bulkEditStatusReasons: $('#bulkEditStatusReasonId'),

    addResourceButtons: $('.add-resource'),
    addResourceDialog: $('#add-resource-dialog'),

    userSearch: $('#userSearch'),
    userDialog: $('#userDialog'),
    changeUserForm: $('#changeUserForm'),
    browseUserDialog: $('#allUsers'),
    browseUsersButton: $('#browseUsers'),
    resourceUserList: $('#resourceUserList'),
    allUsersList: $('#allUsersList'),

    groupSearch: $('#groupSearch'),
    groupDialog: $('#groupDialog'),
    browseGroupDialog: $('#allGroups'),
    browseGroupsButton: $('#browseGroups'),
    resourceGroupList: $('#resourceGroupList'),
    allGroupsList: $('#allGroupsList'),

    resourceGroupDialog: $('#resourceGroupDialog'),
    resourceGroupForm: $('#resourceGroupForm'),
    changeGroupForm: $('#changeGroupForm'),
    groupDiv: $('#resourceGroups'),
    autoAssign: $('#autoAssign'),
    removeAllPermissions: $('#autoAssignRemoveAllPermissions'),

    enableCheckIn: $('#enableCheckIn'),
    autoReleaseMinutes: $('#autoReleaseMinutes'),
    autoReleaseMinutesDiv: $('#autoReleaseMinutesDiv'),

    colorForm: $('#colorForm'),
    reservationColor: $('#reservationColor'),

    creditsDialog: $('#creditsDialog'),
    creditsForm: $('#creditsForm'),
    creditsPerSlot: $('#creditsPerSlot'),
    peakCreditsPerSlot: $('#peakCreditsPerSlot'),

    checkAllResources: $('#checkAllResources'),
    checkNoResources: $('#checkNoResources'),

    copyDialog: $('#copyDialog'),
    copyName: $('#copyResourceName'),
    copyForm: $('#copyForm'),

    importDialog: $('#importDialog'),
    importForm: $('#importForm'),
    importTrigger: $('#import-resources'),
    removeImageName: $('#removeImageName'),
    defaultImageName: $('#defaultImageName'),

    bulkDeletePromptButton: $('#bulkDeletePromptButton'),
    bulkDeleteDialog: $('#bulkDeleteDialog'),
    bulkDeleteList: $('#bulkDeleteList'),
    bulkDeleteForm: $('#bulkDeleteForm'),
    checkAllDeleteResources: $('#checkAllDeleteResources'),
    checkNoDeleteResources: $('#checkNoDeleteResources'),

    checkAllowConcurrent: $('#allowConcurrentChk'),
    maxConcurrent: $('#maxConcurrentReservations'),
    allowConcurrentDiv: $('#allowConcurrentDiv'),

    toggleStatusChangeMessage: $('#toggleStatusChangeMessage'),
    sendStatusChangeMessageContent: $('#sendStatusChangeMessageContent'),
    statusMessageContent: $('#statusMessageContent'),
  };

  var resources = {};
  var reasons = [];

  ResourceManagement.prototype.init = function () {
    this.bindEventListeners();
    this.configureAsyncForms();
    bindStatusReasonBehavior();
    initializeResourceFormReasons();
  };

  ResourceManagement.prototype.bindEventListeners = function () {
    elements.resourceTable.on('click', '.admin-row-edit-btn', function (e) {
      openResourceDrawer($(this).closest('tr').attr('data-resourceId'));
    });

    elements.resourceTable.on('click', '.admin-row-delete-action', function (e) {
      setActiveResourceId($(this).closest('tr').attr('data-resourceId'));
      showDeletePrompt(e);
    });

    elements.resourcesList.on('click', '.update', function (e) {
      e.preventDefault();
      var id = $(this).closest('.resourceDetails').attr('data-resourceId');
      setActiveResourceId(id);
    });

    elements.resourcesList.on('click', '.copyButton', function (e) {
      e.stopPropagation();
      elements.copyName.val(getActiveResource().name + ' ' + options.copyText);
      elements.copyDialog.modal('show');
      elements.copyName.select().focus();
    });

    //edit image
    elements.resourcesList.on('click', '.imageButton', function (e) {
      showChangeImage(e);
    });

    elements.imageDialog.on('click', '.defaultImage', function (e) {
      e.preventDefault();
      var image = $(e.target).closest('.resource-image').attr('id');
      elements.defaultImageName.val(image);
      elements.defaultImageForm.submit();
    });

    elements.imageDialog.on('click', '.deleteImage', function (e) {
      e.preventDefault();
      var image = $(e.target).closest('.resource-image').attr('id');
      elements.removeImageName.val(image);
      elements.removeImageForm.submit();
    });

    elements.imageDialog.on('hidden.bs.modal', function () {
      window.location.reload();
    });

    //edit resource color
    elements.resourcesList.on('change', '.resourceColorPicker', function (e) {
      setActiveResourceId($(this).closest('.resourceDetails').attr('data-resourceId'));
      var color = $(this).val();
      elements.reservationColor.val(color);
      elements.colorForm.submit();
    });

    elements.resourcesList.on('click', '.clearColor', function (e) {
      $(this).siblings('.resourceColorPicker').val('#ffffff');
      elements.reservationColor.val('');
      elements.colorForm.submit();
    });

    this.BindXEditableListeners();

    //modals
    elements.resourcesList.on('click', '.adminButton', function (e) {
      showResourceAdmin(e);
    });

    elements.resourcesList.on('click', '.deleteButton', function (e) {
      showDeletePrompt(e);
    });

    elements.resourcesList.on('click', '.changeStatus', function (e) {
      showStatusPrompt(e);
    });

    elements.resourcesList.on('click', '.changeDuration', function (e) {
      showDurationPrompt(e);
    });

    elements.resourcesList.on('click', '.changeCapacity', function (e) {
      showCapacityPrompt(e);
    });

    elements.resourcesList.on('click', '.changeAccess', function (e) {
      showAccessPrompt(e);
    });

    elements.resourcesList.on('click', '.changeUserPermission', function (e) {
      changeUserPermissions();
      elements.userDialog.modal('show');
    });

    elements.resourcesList.on('click', '.changeGroupPermissions', function (e) {
      changeGroupPermissions();
      elements.groupDialog.modal('show');
    });

    elements.resourcesList.on('click', '.changeResourceGroups', function (e) {
      changeResourceGroups();
      elements.resourceGroupDialog.modal('show');
    });

    elements.resourcesList.on('click', '.changeCredits', function (e) {
      var resource = getActiveResource();
      elements.creditsPerSlot.val(resource.credits);
      elements.peakCreditsPerSlot.val(resource.peakCredits);
      elements.creditsDialog.modal('show');
    });

    elements.resourcesList.on('click', '.enableSubscription, .disableSubscription', function (e) {
      e.preventDefault();
      const details = $(this).closest('.resourceDetails');
      const action = $(this).hasClass('enableSubscription')
        ? options.actions.enableSubscription
        : options.actions.disableSubscription; // Determine the action
      const subscriptionCallback = function (data) {
        details.find('.publicSettingsPlaceHolder').html(data || '<p>No data received</p>');
      };
      PerformAsyncAction(details, getSubmitCallback(action), $('#subscriptionIndicator'), subscriptionCallback);
    });

    elements.checkAllResources.click(function (e) {
      e.preventDefault();
      elements.bulkUpdateList.find('input:checkbox').prop('checked', true);
    });

    elements.checkNoResources.click(function (e) {
      e.preventDefault();
      elements.bulkUpdateList.find('input:checkbox').prop('checked', false);
    });

    elements.checkAllDeleteResources.click(function (e) {
      e.preventDefault();
      elements.bulkDeleteList.find('input:checkbox').prop('checked', true);
    });

    elements.checkNoDeleteResources.click(function (e) {
      e.preventDefault();
      elements.bulkDeleteList.find('input:checkbox').prop('checked', false);
    });

    // Physical-room drawers have a collapsed weekly-hours editor but no own save button --
    // the one "更新" button saves both. Bound before the generic $('.save') handler below so
    // stopImmediatePropagation() can pre-empt it: persist the hours first (reusing the
    // proven changeLayout endpoint), and only submit the basic-info form once that succeeds.
    $('form.resourceUpdateForm .save').on('click', function (e) {
      var form = $(this).closest('form');
      var editor = form.closest('.resourceDetails').find('.resourceHoursEditor');
      if (editor.length === 0) {
        return;
      }
      e.stopImmediatePropagation();
      e.preventDefault();
      saveResourceHours(editor, function (ok) {
        if (ok) {
          form.trigger('submit');
        }
      });
    });

    $('.save').click(function () {
      $(this).closest('form').submit();
    });

    $('.cancel').click(function () {
      var container = $(this).closest('.modal, .offcanvas');
      if (container.hasClass('offcanvas')) {
        bootstrap.Offcanvas.getOrCreateInstance(container[0]).hide();
      } else {
        container.modal('hide');
      }
    });

    elements.addResourceButtons.click(function (e) {
      e.preventDefault();
      bootstrap.Offcanvas.getOrCreateInstance(elements.addResourceDialog[0]).show();
      $('#resourceName').focus();
    });

    elements.bulkEditStatusOptions.change(function (e) {
      populateReasonOptions(elements.bulkEditStatusOptions.val(), elements.bulkEditStatusReasons);
    });

    elements.statusOptionsFilter.change(function (e) {
      populateReasonOptions(elements.statusOptionsFilter.val(), elements.statusReasonsFilter);
    });

    elements.clearFilterButton.click(function (e) {
      e.preventDefault();
      elements.filterTable.find('input,select,textarea').val('');
    });

    wireUpCheckboxToggle(elements.bulkUpdateDialog);

    $('#bulkEditEnableCheckIn').change(function () {
      if ($(this).val() == '1') {
        $('#bulkUpdateAutoReleaseMinutesDiv').removeClass('d-none');
      }

      if ($(this).val() == '0') {
        $('#bulkUpdateAutoReleaseMinutesDiv').addClass('d-none');
      }
    });

    $('#bulkEditConcurrent').change(function () {
      if ($(this).val() == '1') {
        $('#bulkEditAllowConcurrentDiv').removeClass('d-none');
      }

      if ($(this).val() == '0') {
        $('#bulkEditAllowConcurrentDiv').addClass('d-none');
      }
    });

    elements.bulkUpdatePromptButton.click(function (e) {
      e.preventDefault();

      var items = [];
      elements.bulkUpdateList.empty();
      $.each(resources, function (i, r) {
        var checkId = 'bulk' + r.id;
        items.push(
          '<div class="form-check">' +
            '<input class="form-check-input" type="checkbox" id="' +
            checkId +
            '" name="resourceId[]" checked="checked" value="' +
            r.id +
            '" />' +
            '<label class="form-check-label" for="' +
            checkId +
            '">' +
            r.name +
            '</label>' +
            '</div>'
        );
      });
      $('<div/>', { html: items.join('') }).appendTo(elements.bulkUpdateList);

      $('#bulkUpdateDialog').modal('show');
    });

    elements.bulkDeletePromptButton.click(function (e) {
      e.preventDefault();

      var items = [];
      elements.bulkDeleteList.empty();
      $.each(resources, function (i, r) {
        var checkId = 'bulk' + r.id;
        items.push(
          '<div class="form-check">' +
            '<input class="form-check-input" type="checkbox" id="' +
            checkId +
            '" name="resourceId[]" checked="checked" value="' +
            r.id +
            '" />' +
            '<label class="form-check-label" for="' +
            checkId +
            '">' +
            r.name +
            '</label>' +
            '</div>'
        );
      });
      $('<div/>', { html: items.join('') }).appendTo(elements.bulkDeleteList);

      $('#bulkDeleteDialog').modal('show');
    });

    elements.userSearch.userAutoComplete(options.userAutocompleteUrl, function (ui) {
      changeUserPermission(ui.item.value, '1');
      elements.userSearch.val('');
    });

    elements.groupSearch.groupAutoComplete(options.groupAutocompleteUrl, function (ui) {
      changeGroupPermission(ui.item.value, '1');
      elements.groupSearch.val('');
    });

    elements.browseUsersButton.click(function (e) {
      e.preventDefault();
      showAllUsersToAdd();
    });

    elements.resourceUserList.on('change', '.change-permission-type', function (e) {
      e.preventDefault();
      var userId = $(this).data('user-id');
      var type = $(this).val();
      changeUserPermission(userId, type);
    });

    elements.allUsersList.on('change', '.change-permission-type', function (e) {
      e.preventDefault();
      var userId = $(this).data('user-id');
      var type = $(this).val();
      changeUserPermission(userId, type);
    });

    elements.browseGroupsButton.click(function (e) {
      e.preventDefault();
      showAllGroupsToAdd();
    });

    elements.resourceGroupList.on('change', '.change-permission-type', function (e) {
      e.preventDefault();
      var groupId = $(this).data('group-id');
      var type = $(this).val();
      changeGroupPermission(groupId, type);
    });

    elements.allGroupsList.on('change', '.change-permission-type', function (e) {
      e.preventDefault();
      var groupId = $(this).data('group-id');
      var type = $(this).val();
      changeGroupPermission(groupId, type);
    });

    elements.autoAssign.on('click', function () {
      elements.removeAllPermissions.find('input').prop('checked', false);
      if (!elements.autoAssign.is(':checked')) {
        elements.removeAllPermissions.collapse('show');
      } else {
        elements.removeAllPermissions.collapse('hide');
      }
    });

    elements.enableCheckIn.on('click', function () {
      showHideAutoRelease();
    });

    elements.checkAllowConcurrent.on('click', function () {
      showHideConcurrent();
    });

    wireUpCheckboxToggle(elements.durationForm);
    wireUpCheckboxToggle(elements.capacityForm);
    wireUpCheckboxToggle(elements.accessForm);
    wireUpCheckboxToggle(elements.bulkUpdateForm);

    elements.importTrigger.click(function (e) {
      e.preventDefault();
      $('#importErrors').empty().addClass('d-none');
      $('#importResults').addClass('d-none');
      elements.importDialog.modal('show');
    });

    // 開放使用時間管理併進會議室抽屜:每間實體會議室的每週開放時段就地編輯,
    // 儲存時走時間表頁那條已驗證的 changeLayout 端點(見 .resourceHoursForm)。
    elements.resourcesList.on('change', '.resourceDayOpen', function () {
      var row = $(this).closest('.admin-schedule-day-row');
      row.find('.resourceDayStart, .resourceDayEnd').prop('disabled', !$(this).is(':checked'));
    });

    // 全天開放: fills the grid (Mon–Sun on, every day 00:00–00:00 = the full-day
    // representation LayoutValidator wants). Front-end only — user still hits save.
    elements.resourcesList.on('click', '.setAllDayHours', function (e) {
      e.preventDefault();
      var resourceId = $(this).data('resource-id');
      for (var day = 0; day <= 6; day++) {
        $('#resourceDayOpen' + resourceId + '_' + day).prop('checked', true);
        $('#resourceDayStart' + resourceId + '_' + day)
          .val('00:00')
          .prop('disabled', false);
        $('#resourceDayEnd' + resourceId + '_' + day)
          .val('00:00')
          .prop('disabled', false);
      }
    });

  };

  ResourceManagement.prototype.BindXEditableListeners = function () {
    elements.resourcesList.on('click', '.renameButton', function (e) {
      e.stopPropagation();
      $(this).closest('.resourceDetails').find('.resourceNameField').editable('toggle');
    });

    elements.resourcesList.on('click', '.changeScheduleButton', function (e) {
      e.stopPropagation();
      $(this).closest('.resourceDetails').find('.scheduleName').editable('toggle');
    });

    elements.resourcesList.on('click', '.changeResourceType', function (e) {
      e.stopPropagation();
      $(this).closest('.resourceDetails').find('.resourceTypeName').editable('toggle');
    });

    elements.resourcesList.on('click', '.changeSortOrder', function (e) {
      e.stopPropagation();
      $(this).closest('.resourceDetails').find('.sortOrderValue').editable('toggle');
    });

    elements.resourcesList.on('click', '.changeLocation', function (e) {
      e.stopPropagation();
      $(this).closest('.resourceDetails').find('.locationValue').editable('toggle');
    });

    elements.resourcesList.on('click', '.changeContact', function (e) {
      e.stopPropagation();
      $(this).closest('.resourceDetails').find('.contactValue').editable('toggle');
    });

    elements.resourcesList.on('click', '.changeDescription', function (e) {
      e.stopPropagation();
      $(this).closest('.resourceDetails').find('.descriptionValue').editable('toggle');
    });

    elements.resourcesList.on('click', '.changeNotes', function (e) {
      e.stopPropagation();
      $(this).closest('.resourceDetails').find('.notesValue').editable('toggle');
    });

    elements.resourcesList.on('click', '.changeResourceAdmin', function (e) {
      e.stopPropagation();
      $(this).closest('.resourceDetails').find('.resourceAdminValue').editable('toggle');
    });

    elements.resourcesList.on('click', '.changeAttribute', function (e) {
      e.stopPropagation();
      $(e.target).closest('.updateCustomAttribute').find('.inlineAttribute').editable('toggle');
    });
  };

  ResourceManagement.prototype.configureAsyncForms = function () {
    var imageSaveErrorHandler = function (result) {
      alert(result);
    };

    var combineIntervals = function (jqForm, opts) {
      $(jqForm)
        .find('.interval')
        .each(function (i, v) {
          var id = $(v).attr('id');
          var d = $('#' + id + 'Days').val();
          var h = $('#' + id + 'Hours').val();
          var m = $('#' + id + 'Minutes').val();
          $(v).val(d + 'd' + h + 'h' + m + 'm');
        });
    };

    var bulkUpdateErrorHandler = function (result) {
      $('#bulkUpdateErrors').html(result).show();
    };

    var errorHandler = function (result) {
      $('#globalError').html(result).show();
    };

    var importHandler = function (responseText, form) {
      if (!responseText) {
        return;
      }

      $('#importCount').text(responseText.importCount);
      $('#importSkipped').text(responseText.skippedRows.length > 0 ? responseText.skippedRows.join(',') : '0');
      $('#importResult').removeClass('d-none');

      var errors = $('#importErrors');
      errors.empty();
      if (responseText.messages && responseText.messages.length > 0) {
        var messages = responseText.messages.join('</li><li>');
        errors.html('<div>' + messages + '</div>').removeClass('d-none');
      }
    };

    ConfigureAsyncForm(
      elements.imageForm,
      defaultSubmitCallback(elements.imageForm),
      function (data) {
        onImagesUpdated(data);
      },
      imageSaveErrorHandler
    );

    ConfigureAsyncForm(elements.removeImageForm, defaultSubmitCallback(elements.removeImageForm), function (data) {
      onImagesUpdated(data);
    });

    ConfigureAsyncForm(elements.defaultImageForm, defaultSubmitCallback(elements.defaultImageForm), function (data) {
      onImagesUpdated(data);
    });

    ConfigureAsyncForm(elements.addForm, defaultSubmitCallback(elements.addForm), null, handleAddError);
    ConfigureAsyncForm(elements.deleteForm, defaultSubmitCallback(elements.deleteForm), function (result) {
      var id = getActiveResourceId();
      elements.resourceTable.find('tr[data-resourceid="' + id + '"]').remove();
      var drawerEl = document.getElementById('resourceDrawer' + id);
      if (drawerEl) {
        bootstrap.Offcanvas.getOrCreateInstance(drawerEl).hide();
      }
    });
    ConfigureAsyncForm(elements.durationForm, defaultSubmitCallback(elements.durationForm), onDurationSaved, null, {
      onBeforeSerialize: combineIntervals,
    });
    ConfigureAsyncForm(elements.capacityForm, defaultSubmitCallback(elements.capacityForm), onCapacitySaved);
    ConfigureAsyncForm(elements.accessForm, defaultSubmitCallback(elements.accessForm), onAccessSaved, null, {
      onBeforeSerialize: combineIntervals,
    });
    ConfigureAsyncForm(
      elements.bulkUpdateForm,
      defaultSubmitCallback(elements.bulkUpdateForm),
      null,
      bulkUpdateErrorHandler,
      { onBeforeSerialize: combineIntervals }
    );
    ConfigureAsyncForm(
      elements.changeUserForm,
      defaultSubmitCallback(elements.changeUserForm),
      changeUserPermissions,
      errorHandler
    );
    ConfigureAsyncForm(
      elements.changeGroupForm,
      defaultSubmitCallback(elements.changeGroupForm),
      changeGroupPermissions,
      errorHandler
    );
    ConfigureAsyncForm(
      elements.resourceGroupForm,
      defaultSubmitCallback(elements.resourceGroupForm),
      onResourceGroupsSaved
    );
    ConfigureAsyncForm(elements.colorForm, defaultSubmitCallback(elements.colorForm), function () {});
    ConfigureAsyncForm(
      elements.creditsForm,
      defaultSubmitCallback(elements.creditsForm),
      onCreditsSaved,
      null,
      errorHandler
    );
    ConfigureAsyncForm(elements.copyForm, defaultSubmitCallback(elements.copyForm));
    ConfigureAsyncForm(elements.importForm, defaultSubmitCallback(elements.importForm), importHandler);
    ConfigureAsyncForm(elements.bulkDeleteForm, defaultSubmitCallback(elements.bulkDeleteForm));
    ConfigureAsyncForm(elements.statusForm, defaultSubmitCallback(elements.statusForm));

    // One resourceForm per typed resource (see manage_resource_drawer_content.tpl) -- each
    // saves name/status/video-conference-info together in one request, then the page reloads
    // so the table row and drawer both reflect the change (same reload-on-save convention as
    // Web/scripts/admin/user.js's userForm).
    elements.resourcesList.find('form.resourceUpdateForm').each(function () {
      var form = $(this);
      ConfigureAsyncForm(form, defaultSubmitCallback(form), function () {
        window.location.reload();
      });
    });
  };

  ResourceManagement.prototype.add = function (resource) {
    resources[resource.id] = resource;
  };

  ResourceManagement.prototype.addStatusReason = function (id, statusId, description) {
    if (!(statusId in reasons)) {
      reasons[statusId] = [];
    }

    reasons[statusId].push({ id: id, description: description });
  };

  ResourceManagement.prototype.initializeStatusFilter = function (statusId, reasonId) {
    elements.statusOptionsFilter.val(statusId);
    elements.statusOptionsFilter.trigger('change');
    elements.statusReasonsFilter.val(reasonId);
  };

  ResourceManagement.prototype.addResourceGroups = function (resourceGroups) {
    elements.groupDiv.tree({
      data: resourceGroups,
      saveState: false,
      dragAndDrop: false,
      selectable: false,
      autoOpen: true,

      onCreateLi: function (node, $li) {
        var span = $li.find('span');
        var itemName = span.text();
        var id = 'group_id' + node.id;

        var label = $(
          '<div class="form-check"><input group-id="' +
            node.id +
            '" name="group_id[]" class="form-check-input" type="checkbox" id="' +
            id +
            '" value="' +
            node.id +
            '"/><label class="form-check-label" for="' +
            id +
            '">' +
            itemName +
            '</label></div>'
        );

        $li.find('span').html(label);
      },
    });
  };

  var getSubmitCallback = function (action) {
    return function () {
      return options.submitUrl + '?rid=' + getActiveResourceId() + '&action=' + action;
    };
  };

  var defaultSubmitCallback = function (form) {
    return function () {
      return options.submitUrl + '?action=' + form.attr('ajaxAction') + '&rid=' + getActiveResourceId();
    };
  };

  var setActiveResourceId = function (resourceId) {
    elements.activeId.val(resourceId);
  };

  var getActiveResourceId = function () {
    return elements.activeId.val();
  };

  var getResource = function (id) {
    return resources[id];
  };

  var getActiveResource = function () {
    return getResource(getActiveResourceId());
  };

  var refreshResourceImages = function (resource) {
    var imageContainer = $('#resource-images');
    imageContainer.empty();

    var imageContents = function (image, isDefault) {
      imageContainer.append(
        '<div class="resource-image col-4" id="' +
          image +
          '"><img class="w-100" src="' +
          image +
          '"/><div class="text-center">' +
          (isDefault ? 'Default' : '<a href="#" class="defaultImage link-primary">Default</a>') +
          ' | <a href="#" class="deleteImage link-danger"><i class="bi bi-trash3-fill me-1"></i>Remove</a></div></div>'
      );
    };

    if (resource.image) {
      imageContents(resource.image, true);
    }

    for (var i = 0; i < resource.images.length; i++) {
      imageContents(resource.images[i]);
    }
  };

  var showChangeImage = function (e) {
    e.preventDefault();
    var resource = getActiveResource();
    refreshResourceImages(resource);
    elements.imageDialog.modal('show');
  };

  var onImagesUpdated = function (resource) {
    refreshResourceImages(resource);
    getActiveResource().image = resource.image;
    getActiveResource().images = resource.images;
  };

  var showResourceAdmin = function (e) {
    $('#adminGroupId').val(getActiveResource().adminGroupId);
    elements.groupAdminDialog.modal('show');
  };

  var showDeletePrompt = function (e) {
    e.preventDefault();
    AdminConfirm.show({
      title: options.confirmDeleteTitle,
      message: options.confirmDeleteMessage,
      confirmLabel: options.confirmDeleteButton,
      onConfirm: function () {
        elements.deleteForm.submit();
      },
    });
  };

  var openResourceDrawer = function (id) {
    setActiveResourceId(id);
    var drawerEl = document.getElementById('resourceDrawer' + id);
    if (drawerEl) {
      bootstrap.Offcanvas.getOrCreateInstance(drawerEl).show();
    }
  };

  // Fills the hidden .resourceHoursForm from the 7 day toggle/start/end inputs and POSTs it
  // to manage_schedules.php?action=changeLayout. Slot-string format + the full-day blocked
  // coverage rule are copied verbatim from schedule.js saveScheduleHours() so the server
  // sees an identical request to the (proven) schedules-page save.
  // done(ok) fires after the changeLayout POST — the drawer's single save button chains the
  // basic-info submit onto ok === true.
  var saveResourceHours = function (editor, done) {
    var resourceId = editor.data('resource-id');
    var form = editor.find('.resourceHoursForm');
    var errorEl = editor.find('.resourceHoursError');
    errorEl.addClass('d-none').empty();

    for (var day = 0; day <= 6; day++) {
      var isOpen = $('#resourceDayOpen' + resourceId + '_' + day).is(':checked');
      var start = $('#resourceDayStart' + resourceId + '_' + day).val();
      var end = $('#resourceDayEnd' + resourceId + '_' + day).val();
      var reservable = '';
      var blocked = '00:00 - 00:00';

      if (isOpen && start && end) {
        reservable = start + ' - ' + end;
        // LayoutValidator wants every day's periods to cover the full 24h with no gaps.
        blocked = '';
        if (start !== '00:00') {
          blocked += '00:00 - ' + start + '\n';
        }
        if (end !== '00:00') {
          blocked += end + ' - 00:00';
        }
      }
      form.find('.rhReservable[data-day="' + day + '"]').val(reservable);
      form.find('.rhBlocked[data-day="' + day + '"]').val(blocked);
    }
    form.find('.rhCsrf').val($('#csrf_token').val());

    $.ajax({
      url: form.attr('action'),
      type: 'POST',
      data: form.serialize(),
      dataType: 'text',
      success: function (responseText) {
        if (responseText && $.trim(responseText).length > 0) {
          // Non-empty body = validation errors (ActionPresenter renders them and returns 200).
          errorEl.removeClass('d-none').html(responseText);
          if (done) {
            done(false);
          }
        } else if (done) {
          done(true);
        }
      },
      error: function (xhr) {
        errorEl.removeClass('d-none').text(xhr.responseText || 'Error');
        if (done) {
          done(false);
        }
      },
    });
  };

  var showDurationPrompt = function (e) {
    var resource = getActiveResource();

    setDaysHoursMinutes('#minDuration', resource.minLength, $('#noMinimumDuration'));
    setDaysHoursMinutes('#maxDuration', resource.maxLength, $('#noMaximumDuration'));
    setDaysHoursMinutes('#bufferTime', resource.bufferTime, $('#noBufferTime'));
    $('#allowMultiDay').prop('checked', resource.allowMultiday && resource.allowMultiday == '1');

    elements.durationDialog.modal('show');
  };

  var showCapacityPrompt = function (e) {
    var resource = getActiveResource();

    showHideConfiguration(resource.maxParticipants, $('#maxCapacity'), $('#unlimitedCapacity'));
    elements.capacityDialog.modal('show');
  };

  var showAccessPrompt = function (e) {
    var resource = getActiveResource();

    setDaysHoursMinutes('#startNoticeAdd', resource.startNoticeAdd, $('#noStartNoticeAdd'));
    setDaysHoursMinutes('#startNoticeUpdate', resource.startNoticeUpdate, $('#noStartNoticeUpdate'));
    setDaysHoursMinutes('#startNoticeDelete', resource.startNoticeDelete, $('#noStartNoticeDelete'));
    setDaysHoursMinutes('#endNotice', resource.endNotice, $('#noEndNotice'));

    $('#requiresApproval').prop('checked', resource.requiresApproval && resource.requiresApproval == '1');

    elements.autoAssign.prop('checked', resource.autoAssign && resource.autoAssign == '1');

    elements.removeAllPermissions.addClass('collapse');

    elements.enableCheckIn.prop('checked', resource.enableCheckin && resource.enableCheckin == '1');
    elements.autoReleaseMinutes.val(resource.autoReleaseMinutes);
    showHideAutoRelease();

    elements.checkAllowConcurrent.prop('checked', resource.allowConcurrent && resource.allowConcurrent == '1');
    elements.maxConcurrent.val(Math.max(2, Number.parseInt(resource.maxConcurrent)));
    showHideConcurrent();

    elements.accessDialog.modal('show');
  };

  var showHideAutoRelease = function () {
    if (!elements.enableCheckIn.is(':checked')) {
      elements.autoReleaseMinutesDiv.collapse('hide');
    } else {
      elements.autoReleaseMinutesDiv.collapse('show');
    }
  };

  var showHideConcurrent = function () {
    if (elements.checkAllowConcurrent.is(':checked')) {
      elements.allowConcurrentDiv.collapse('show');
    } else {
      elements.allowConcurrentDiv.collapse('hide');
    }
  };

  var setDuration = function (container, resourceDuration) {
    var emptyIfZero = function (val) {
      if (val == 0) {
        return '';
      }
      return val;
    };
    resourceDuration.value = container.attr('data-value');
    resourceDuration.days = emptyIfZero(container.attr('data-days'));
    resourceDuration.hours = emptyIfZero(container.attr('data-hours'));
    resourceDuration.minutes = emptyIfZero(container.attr('data-minutes'));
  };

  var onDurationSaved = function (resultHtml) {
    var resource = getActiveResource();
    var resourceDiv = $('div[data-resourceId=' + resource.id + ']');
    resourceDiv.find('.durationPlaceHolder').html(resultHtml);

    var result = resourceDiv.find('.durationPlaceHolder');
    var minDuration = result.find('.minDuration');
    var maxDuration = result.find('.maxDuration');
    var bufferTime = result.find('.bufferTime');
    var allowMultiDay = result.find('.allowMultiDay');

    setDuration(minDuration, resource.minLength);
    setDuration(maxDuration, resource.maxLength);
    setDuration(bufferTime, resource.bufferTime);
    resource.allowMultiday = allowMultiDay.attr('data-value');

    elements.durationDialog.modal('hide');
  };

  var onCapacitySaved = function (resultHtml) {
    var resource = getActiveResource();
    var resourceDiv = $('div[data-resourceId=' + resource.id + ']');
    resourceDiv.find('.capacityPlaceHolder').html(resultHtml);

    var result = resourceDiv.find('.capacityPlaceHolder');
    var maxParticipants = result.find('.maxParticipants');
    resource.maxParticipants = maxParticipants.attr('data-value');

    elements.capacityDialog.modal('hide');
  };

  var onAccessSaved = function (resultHtml) {
    var resource = getActiveResource();
    var resourceDiv = $('div[data-resourceId=' + resource.id + ']');
    resourceDiv.find('.accessPlaceHolder').html(resultHtml);

    var result = resourceDiv.find('.accessPlaceHolder');
    var startNoticeAdd = result.find('.startNoticeAdd');
    var startNoticeUpdate = result.find('.startNoticeUpdate');
    var startNoticeDelete = result.find('.startNoticeDelete');
    var endNotice = result.find('.endNotice');
    var requiresApproval = result.find('.requiresApproval');
    var autoAssign = result.find('.autoAssign');
    var enableCheckin = result.find('.enableCheckin');
    var autoRelease = result.find('.autoRelease');
    var maxConcurrent = result.find('.allowConcurrent');

    setDuration(startNoticeAdd, resource.startNoticeAdd);
    setDuration(startNoticeUpdate, resource.startNoticeUpdate);
    setDuration(startNoticeDelete, resource.startNoticeDelete);
    setDuration(endNotice, resource.endNotice);
    resource.requiresApproval = requiresApproval.attr('data-value');
    resource.autoAssign = autoAssign.attr('data-value');

    resource.enableCheckin = enableCheckin.attr('data-value');
    resource.autoReleaseMinutes = autoRelease.attr('data-value');

    resource.maxConcurrent = maxConcurrent.data('max-concurrent');
    resource.allowConcurrent = maxConcurrent.data('allow-concurrent');

    elements.accessDialog.modal('hide');
  };

  var onResourceGroupsSaved = function (resultHtml) {
    var resource = getActiveResource();
    var resourceDiv = $('div[data-resourceId=' + resource.id + ']');
    resourceDiv.find('.resourceGroupsPlaceHolder').html(resultHtml);

    var result = resourceDiv.find('.resourceGroupsPlaceHolder');
    var groupIdElements = result.find('.resourceGroupId');

    var groupIds = [];
    $.each(groupIdElements, function (i, group) {
      groupIds.push($(group).attr('data-value'));
    });
    resource.resourceGroupIds = groupIds;

    elements.resourceGroupDialog.modal('hide');
  };

  var onCreditsSaved = function (resultHtml) {
    var resource = getActiveResource();
    var resourceDiv = $('div[data-resourceId=' + resource.id + ']');
    resourceDiv.find('.creditsPlaceHolder').html(resultHtml);

    var credits = resourceDiv.find('.creditsPerSlot');
    var peak = resourceDiv.find('.peakCreditsPerSlot');

    resource.credits = credits.attr('data-value');
    resource.peakCredits = peak.attr('data-value');

    elements.creditsDialog.modal('hide');
  };

  // Populates and shows the shared statusDialog (untyped-resource fallback only -- typed
  // resources edit status inline in their own resourceForm, see bindStatusReasonBehavior()
  // below, which this shares its cascading/add-reason/notify-toggle behavior with via
  // document-level delegation instead of each caller rebinding its own handlers).
  var showStatusPrompt = function (e) {
    var resource = getActiveResource();
    var statusForm = elements.statusForm;
    var statusOptions = statusForm.find(elements.statusOptions);
    var statusReasons = statusForm.find(elements.statusReasons);

    statusOptions.val(resource.statusId);
    populateReasonOptions(statusOptions.val(), statusReasons);
    statusReasons.val(resource.reasonId);

    elements.toggleStatusChangeMessage.prop('checked', false);
    elements.sendStatusChangeMessageContent.addClass('d-none');
    elements.statusMessageContent.val('');

    elements.statusDialog.modal('show');
    statusOptions.focus();
  };

  // Shared by statusDialog (untyped resources) and every typed resource's own resourceForm:
  // both render the same .statusId/.reasonId/.addStatusReason/.notifyUsersToggle markup, so
  // one set of document-level delegated handlers (bound once, scoped per-event via
  // .closest('form')) covers all of them instead of each form/dialog rebinding its own.
  var bindStatusReasonBehavior = function () {
    $(document).on('change', '.statusId', function () {
      populateReasonOptions($(this).val(), $(this).closest('form').find(elements.statusReasons));
    });

    $(document).on('click', '.addStatusReason', function (e) {
      e.preventDefault();
      var form = $(this).closest('form');
      var reasonSelect = form.find(elements.statusReasons);

      form.find(elements.newStatusReason).toggleClass('d-none');
      form.find(elements.existingStatusReason).toggleClass('d-none');

      if (form.find(elements.newStatusReason).hasClass('d-none')) {
        reasonSelect.data('prev', reasonSelect.val());
        reasonSelect.val('');
        form.find(elements.resourceStatusReason).focus();
      } else {
        reasonSelect.val(reasonSelect.data('prev'));
        reasonSelect.focus();
      }
    });

    $(document).on('change', '.notifyUsersToggle', function () {
      $(this)
        .closest('form')
        .find('.notifyUsersContent')
        .toggleClass('d-none', !$(this).is(':checked'));
    });
  };

  // Every typed resource's resourceForm renders its .reasonId select empty (the options
  // depend on the current .statusId, same as statusDialog's), so populate each one once at
  // init time from the resource's own cached statusId/reasonId (see resourceManagement.add()).
  var initializeResourceFormReasons = function () {
    elements.resourcesList.find('form.resourceUpdateForm').each(function () {
      var form = $(this);
      var id = form.closest('.resourceDetails').attr('data-resourceId');
      var resource = getResource(id);
      var reasonSelect = form.find(elements.statusReasons);

      populateReasonOptions(form.find(elements.statusOptions).val(), reasonSelect);
      reasonSelect.val(resource.reasonId);
    });
  };

  function populateReasonOptions(statusId, reasonsElement) {
    reasonsElement.empty().append($('<option>', { value: '', text: '-' }));

    if (statusId in reasons) {
      $.each(reasons[statusId], function (i, v) {
        reasonsElement.append(
          $('<option>', {
            value: v.id,
            text: v.description,
          })
        );
      });
    }
  }

  function setDaysHoursMinutes(elementPrefix, interval, attributeCheckbox) {
    if (!interval) {
      interval = { days: null, hours: null, minutes: null, value: null };
    }
    $(elementPrefix + 'Days').val(interval.days);
    $(elementPrefix + 'Hours').val(interval.hours);
    $(elementPrefix + 'Minutes').val(interval.minutes);

    showHideConfiguration(interval.value, $(elementPrefix), attributeCheckbox);
  }

  function showHideConfiguration(attributeValue, attributeDisplayElement, attributeCheckbox) {
    attributeDisplayElement.val(attributeValue);
    var selector = attributeCheckbox.attr('data-related-inputs');
    var container = attributeCheckbox.closest('form');
    var span = container.find(selector);

    if (attributeValue == '' || attributeValue == undefined) {
      attributeCheckbox.prop('checked', true);
      span.collapse('hide');
    } else {
      attributeCheckbox.prop('checked', false);
      span.collapse('show');
    }
  }

  function wireUpCheckboxToggle(container) {
    container.find(':checkbox').change(function () {
      var selector = $(this).attr('data-related-inputs');
      var span = container.find(selector);

      if ($(this).is(':checked')) {
        span.find('input[type=text],input[type=number]').val('');
        span.collapse('hide');
      } else {
        span.collapse('show');
      }
    });
  }

  var handleAddError = function (result) {
    $('#addResourceResults').text(result).show();
  };

  var changeUserPermissions = function () {
    var resourceId = getActiveResourceId();
    $.get(opts.permissionsUrl + '?dr=users', { rid: resourceId }, function (data) {
      elements.resourceUserList.html(data);
      $('.user-permission-spinner').addClass('d-none');
    });
  };

  var changeUserPermission = function (userId, type) {
    $('.user-permission-spinner').removeClass('d-none');
    $('#changeUserId').val(userId);
    $('#changeUserType').val(type);
    elements.changeUserForm.submit();
  };

  var showAllUsersToAdd = function () {
    elements.userDialog.modal('hide');
    elements.allUsersList.empty();

    var resourceId = getActiveResourceId();
    $.get(opts.permissionsUrl + '?dr=usersAll', { rid: resourceId }, function (data) {
      elements.allUsersList.html(data);
    });

    elements.browseUserDialog.modal('show');
  };

  var changeGroupPermissions = function () {
    var resourceId = getActiveResourceId();
    $.get(opts.permissionsUrl + '?dr=groups', { rid: resourceId }, function (data) {
      elements.resourceGroupList.html(data);
      $('.group-permission-spinner').addClass('d-none');
    });
  };

  var changeGroupPermission = function (groupId, type) {
    $('.group-permission-spinner').removeClass('d-none');
    $('#changeGroupId').val(groupId);
    $('#changeGroupType').val(type);
    elements.changeGroupForm.submit();
  };

  var showAllGroupsToAdd = function () {
    elements.groupDialog.modal('hide');
    elements.allGroupsList.empty();

    var resourceId = getActiveResourceId();
    $.get(opts.permissionsUrl + '?dr=groupsAll', { rid: resourceId }, function (data) {
      elements.allGroupsList.html(data);
    });

    elements.browseGroupDialog.modal('show');
  };

  function changeResourceGroups() {
    var resource = getActiveResource();

    elements.groupDiv.find(':checked').prop('checked', false);

    $.each(resource.resourceGroupIds, function (i, id) {
      elements.groupDiv.find('[group-id=' + id + ']').prop('checked', true);
    });
  }
}
