function GroupManagement(opt) {
  var options = opt;
  var activeId = null;
  // DataTables pagination physically removes non-visible rows from the DOM,
  // so <select> elements on other pages don't exist when the form is serialized.
  // This object tracks permission state for all resources in memory, ensuring
  // permissions on non-visible pages aren't lost on save.
  // Keys are resource IDs (e.g. "1", "37"). Values use the format
  // "{resourceId}_{permissionType}" matching the <select> option values:
  //   "1_0" = Full Access (for resource ID 1)
  //   "2_1" = View Only (for resource ID 2)
  //   "37_none" = None (for resource ID 37)
  var permissionState = {};

  var elements = {
    groupList: $('#groupList'),

    autocompleteSearch: $('#groupSearch'),
    userSearch: $('#userSearch'),

    groupUserList: $('#groupUserList'),
    permissionsDialog: $('#permissionsDialog'),
    deleteDialog: $('#deleteDialog'),
    editDialog: $('#editDialog'),
    rolesDialog: $('#rolesDialog'),
    groupAdminDialog: $('#groupAdminDialog'),

    permissionsForm: $('#permissionsForm'),
    addUserForm: $('#addUserForm'),
    removeUserForm: $('#removeUserForm'),
    editGroupForm: $('#editGroupForm'),
    deleteGroupForm: $('#deleteGroupForm'),
    rolesForm: $('#rolesForm'),
    groupAdminForm: $('#groupAdminForm'),
    groupCount: $('#groupCount'),

    addForm: $('#addGroupForm'),
    addDialog: $('#addGroupDialog'),

    checkAllResourcesFull: $('#checkAllResourcesFull'),
    checkAllResourcesView: $('#checkAllResourcesView'),
    checkNoResources: $('#checkNoResources'),

    editGroupName: $('#editGroupName'),
    editGroupIsDefault: $('#editGroupIsDefault'),

    changeAdminGroupsForm: $('#groupAdminGroupsForm'),
    changeAdminResourcesForm: $('#resourceAdminForm'),
    changeAdminSchedulesForm: $('#scheduleAdminForm'),
    resourceAdminDialog: $('#resourceAdminDialog'),
    groupAdminAllDialog: $('#groupAdminAllDialog'),
    scheduleAdminDialog: $('#scheduleAdminDialog'),

    importGroupsDialog: $('#importGroupsDialog'),
    importGroupsForm: $('#importGroupsForm'),
    importGroupsTrigger: $('#import-groups'),
  };

  GroupManagement.prototype.init = function () {
    bindEventListeners();
    configureAutocomplete();
    configureAsyncForms();
  };

  var bindEventListeners = function () {
    const { addDialog } = elements;
    // Delegation root is the whole page, not just #groupList: the per-section action
    // buttons (成員 / 權限 / 角色 …) now live inside the #editDialog detail drawer, and
    // the list row only carries the single 「管理群組」 button.
    const page = $('#page-manage-groups');

    page.on('click', 'a.update, button.update', (e) => {
      e.preventDefault();
      setActiveId($(e.currentTarget));
    });

    //main interface
    $('.save').click((e) => $(e.currentTarget).closest('form').submit());
    $('.cancel').click((e) => {
      var container = $(e.currentTarget).closest('.modal, .offcanvas');
      if (container.hasClass('offcanvas')) {
        bootstrap.Offcanvas.getOrCreateInstance(container[0]).hide();
      } else {
        container.modal('hide');
      }
    });

    $('#add-group').click((e) => {
      e.preventDefault();
      bootstrap.Offcanvas.getOrCreateInstance(addDialog[0]).show();
      addDialog.find(':text').first().focus();
    });

    page.on('click', '.manageGroup', () => editGroup());
    page.on('click', '.permissions', () => changePermissions());
    page.on('click', '.groupAdmin', () => changeGroupAdmin());
    page.on('click', '.changeAdminGroups', () => changeAdminGroups());
    page.on('click', '.changeAdminResources', () => changeAdminResources());
    page.on('click', '.changeAdminSchedules', () => changeAdminSchedules());
    page.on('click', '.delete', () => deleteGroup());
    page.on('click', '.roles', () => changeRoles());

    // remove a member straight from the inline list in the detail drawer
    elements.groupUserList.on('click', '.delete', (e) => {
      e.preventDefault();
      var userId = $(e.currentTarget).siblings('.id').val();
      removeUserFromGroup(userId);
    });

    //ressource selection
    // Handle click to set all resources to "Full Access"
    elements.checkAllResourcesFull.click((e) => {
      e.preventDefault();
      for (var rid in permissionState) {
        permissionState[rid] = rid + '_0';
      }
      syncSelectsFromState();
    });

    // Handle click to set all resources to "View Only"
    elements.checkAllResourcesView.click((e) => {
      e.preventDefault();
      for (var rid in permissionState) {
        permissionState[rid] = rid + '_1';
      }
      syncSelectsFromState();
    });

    // Handle click to set all resources to "None"
    elements.checkNoResources.click((e) => {
      e.preventDefault();
      for (var rid in permissionState) {
        permissionState[rid] = rid + '_none';
      }
      syncSelectsFromState();
    });

    //group role
    $('.adminDialog').on('click', '.checkbox', (e) => {
      const $checkbox = $(e.currentTarget);
      const modal = $checkbox.closest('.modal-body');
      modal.find('.count').text(modal.find(':checked').length);
    });
  };

  var configureAutocomplete = function () {
    const { userSearch } = elements;
    // #groupSearch is now a plain client-side list filter (see the inline script in
    // manage_groups.tpl), not a jump-to-group autocomplete.
    userSearch.userAutoComplete(options.userAutocompleteUrl, (ui) => {
      addUserToGroup(ui.item.value);
      userSearch.val('');
    });
  };

  var configureAsyncForms = function () {
    var cleanupPermissionsForm = function () {
      elements.permissionsForm.find('select.resourceId').attr('name', 'resourceId[]');
      elements.permissionsForm.find('.injected-permission').remove();
    };

    var hidePermissionsDialog = function () {
      elements.permissionsDialog.modal('hide');
    };

    elements.permissionsDialog.on('hidden.bs.modal', cleanupPermissionsForm);

    elements.permissionsForm.on('change', 'select.resourceId', function () {
      var rid = $(this).attr('id').replace('permission_', '');
      permissionState[rid] = $(this).val();
    });

    $('#permissionsDialogtable').on('draw.dt', function () {
      syncSelectsFromState();
    });

    var error = function (errorText) {
      alert(errorText);
    };

    var importHandler = function (response) {
      if (!response) return;

      $('#importCount').text(response.importCount);
      $('#importSkipped').text(response.skippedRows.length || '0');
      $('#importResult').removeClass('d-none');

      const errors = $('#importErrors');
      errors.empty();
      if (response.messages && response.messages.length > 0) {
        const messages = response.messages.join('</li><li>');
        errors.html(`<div>${messages}</div>`).removeClass('d-none');
      }
    };

    ConfigureAsyncForm(elements.addUserForm, getSubmitCallback(options.actions.addUser), changeMembers, error);
    ConfigureAsyncForm(elements.removeUserForm, getSubmitCallback(options.actions.removeUser), changeMembers, error);
    // Inject hidden inputs for all resources before form serialization.
    // DataTables pagination removes non-visible <select> elements from the DOM,
    // so we must submit permissions via hidden inputs from permissionState instead.
    var permissionsBeforeSerialize = function (jqForm) {
      // Remove any previously injected inputs (e.g. from a failed submission retry)
      jqForm.find('.injected-permission').remove();
      // Add a hidden input for each resource that has a permission (skip "None")
      for (var rid in permissionState) {
        var val = permissionState[rid];
        if (val.indexOf('_none') === -1) {
          jqForm.append(
            $('<input>', { type: 'hidden', class: 'injected-permission', name: 'resourceId[]', value: val })
          );
        }
      }
      // Prevent visible <select> elements from also submitting, which would cause duplicates
      jqForm.find('select.resourceId').attr('name', '');
    };

    ConfigureAsyncForm(
      elements.permissionsForm,
      getSubmitCallback(options.actions.permissions),
      hidePermissionsDialog,
      error,
      { onBeforeSerialize: permissionsBeforeSerialize }
    );
    ConfigureAsyncForm(elements.editGroupForm, getSubmitCallback(options.actions.updateGroup), null, error);
    ConfigureAsyncForm(elements.deleteGroupForm, getSubmitCallback(options.actions.deleteGroup), null, error);
    ConfigureAsyncForm(elements.addForm, getSubmitCallback(options.actions.addGroup), null, error);
    ConfigureAsyncForm(elements.rolesForm, getSubmitCallback(options.actions.roles), null, error);
    ConfigureAsyncForm(elements.groupAdminForm, getSubmitCallback(options.actions.groupAdmin), null, error);
    ConfigureAsyncForm(
      elements.changeAdminGroupsForm,
      getSubmitCallback(options.actions.adminGroups),
      function () {
        elements.groupAdminAllDialog.modal('hide');
      },
      error
    );
    ConfigureAsyncForm(
      elements.changeAdminResourcesForm,
      getSubmitCallback(options.actions.resourceGroups),
      function () {
        elements.resourceAdminDialog.modal('hide');
      },
      error
    );
    ConfigureAsyncForm(
      elements.changeAdminSchedulesForm,
      getSubmitCallback(options.actions.scheduleGroups),
      function () {
        elements.scheduleAdminDialog.modal('hide');
      },
      error
    );
    ConfigureAsyncForm(elements.importGroupsForm, getSubmitCallback(options.actions.importGroups), importHandler);
  };

  var getSubmitCallback = function (action) {
    return () => `${options.submitUrl}?gid=${activeId}&action=${action}`;
  };

  var setActiveId = function (el) {
    var fromData = el.data('group-id');
    activeId =
      fromData !== undefined && fromData !== null && fromData !== ''
        ? fromData
        : el.parents('tr').data('group-id');
  };

  // Populate + open the consolidated 群組詳情 drawer. Reserved groups (grant a role or
  // are the default group) get name/auto-join locked and the danger zone hidden --
  // mirrors GroupDisplay::IsReserved / the presenter guards; belt and braces.
  var editGroup = function () {
    const row = elements.groupList.find(`tr[data-group-id="${activeId}"]`);
    const dlg = elements.editDialog;
    const reserved = String(row.data('group-reserved')) === '1';
    const isDefault = String(row.data('group-default')) === '1';
    const roleLabel = row.data('group-role') || '';
    const displayName = row.data('name') || row.find('.dataGroupName').text();

    dlg.find('#editDialogLabel').text(displayName);
    elements.editGroupName.val(displayName).prop('readonly', reserved);
    elements.editGroupIsDefault.prop('checked', isDefault).prop('disabled', reserved);

    dlg.find('.js-reserved-note').toggleClass('d-none', !reserved);

    // pure role groups don't use member-level booking permissions
    dlg.find('.js-perm-section').toggleClass('d-none', !!roleLabel);
    dlg.find('.js-perm-note').toggleClass('d-none', !roleLabel);

    dlg.find('.js-danger-section').toggleClass('d-none', reserved);
    dlg.find('#editGroupSaveBtn').toggleClass('d-none', reserved);

    dlg.find('#editGroupRolesSummary').text(roleLabel || '—');

    changeMembers(); // fill the inline 群組成員 list

    bootstrap.Offcanvas.getOrCreateInstance(dlg[0]).show();
  };

  var deleteGroup = function () {
    elements.deleteDialog.modal('show');
  };

  // Refill the inline 群組成員 list in the detail drawer. Also the success callback for
  // the addUser / removeUser async forms, so add/remove reflects immediately.
  var changeMembers = function () {
    const groupId = activeId;
    $.getJSON(`${options.groupsUrl}?dr=groupMembers`, { gid: groupId }, (data) => {
      const items = [];

      $('#totalUsers').text(data.Total || 0);
      if (data.Users && data.Users.length) {
        data.Users.forEach((item) => {
          items.push(
            `<div class="admin-group-member-row d-flex align-items-center gap-2">` +
              `<a href="#" class="delete link-danger" title="移除"><i class="bi bi-x-circle-fill"></i></a>` +
              `<span>${item.DisplayName}</span>` +
              `<input type="hidden" class="id" value="${item.Id}"/></div>`
          );
        });
      } else {
        items.push('<div class="admin-cell-secondary">—</div>');
      }

      elements.groupUserList.html(items.join(''));
    });
  };

  var addUserToGroup = function (userId) {
    elements.addUserForm.find('#addUserId').val(userId);
    elements.addUserForm.submit();
  };

  var removeUserFromGroup = function (userId) {
    elements.removeUserForm.find('#removeUserId').val(userId);
    elements.removeUserForm.submit();
  };

  // Update visible <select> dropdowns on the current DataTable page to match permissionState.
  // Each select has an id like "permission_37" — the resource ID is extracted by stripping
  // the "permission_" prefix (e.g. "permission_37" → "37"), then used to look up the value
  // in permissionState.
  var syncSelectsFromState = function () {
    var table = $('#permissionsDialogtable').DataTable();
    table.rows({ page: 'current' }).every(function () {
      var select = $(this.node()).find('select.resourceId');
      if (select.length) {
        var rid = select.attr('id').replace('permission_', '');
        if (permissionState[rid] !== undefined) {
          select.val(permissionState[rid]);
        }
      }
    });
  };

  //change group ressource permission
  var changePermissions = function () {
    $.get(options.permissionsUrl, { dr: options.dataRequests.permissions, gid: activeId }, (permissions) => {
      permissionState = {};

      var table = $('#permissionsDialogtable').DataTable();
      table.rows({ search: 'none' }).every(function () {
        var select = $(this.node()).find('select.resourceId');
        if (select.length) {
          var rid = select.attr('id').replace('permission_', '');
          permissionState[rid] = rid + '_none';
        }
      });

      (permissions.full || []).forEach((id) => {
        permissionState[id] = id + '_0';
      });

      (permissions.view || []).forEach((id) => {
        permissionState[id] = id + '_1';
      });

      syncSelectsFromState();
      elements.permissionsDialog.modal('show');
    });
  };

  //change group admin
  var changeGroupAdmin = function () {
    const activeRow = elements.groupList.find(`[data-group-id="${activeId}"]`);
    const currentGroupAdmin = activeRow.find('.groupAdmin').text();
    const currentGroupAdminVal = elements.groupAdminForm
      .find('select')
      .find('option')
      .filter(function () {
        return $(this).html() === currentGroupAdmin;
      })
      .val();

    elements.groupAdminForm.find('select').val(currentGroupAdminVal || '');
    elements.groupAdminDialog.modal('show');
  };

  //change group role
  var changeRoles = function () {
    const groupId = activeId;

    $.get(options.rolesUrl, { dr: options.dataRequests.roles, gid: groupId }, (roleIds) => {
      elements.rolesForm.find(':checkbox').prop('checked', false);
      roleIds.forEach((value) => {
        elements.rolesForm.find(`:checkbox[value="${value}"]`).prop('checked', true);
      });

      elements.rolesDialog.modal('show');
    });
  };

  var changeAdminGroups = function () {
    populateAdminCheckboxes(
      options.dataRequests.adminGroups,
      elements.changeAdminGroupsForm,
      elements.groupAdminAllDialog
    );
  };

  var changeAdminResources = function () {
    populateAdminCheckboxes(
      options.dataRequests.resourceGroups,
      elements.changeAdminResourcesForm,
      elements.resourceAdminDialog
    );
  };

  var changeAdminSchedules = function () {
    populateAdminCheckboxes(
      options.dataRequests.scheduleGroups,
      elements.changeAdminSchedulesForm,
      elements.scheduleAdminDialog
    );
  };

  var populateAdminCheckboxes = function (dr, form, dialog) {
    dialog.find('.count').text(dialog.find(':checked').length);

    $.get(options.submitUrl, { dr, gid: activeId }, (groupIds) => {
      form.find(':checkbox').prop('checked', false);
      groupIds.forEach((value) => {
        form.find(`:checkbox[value="${value}"]`).prop('checked', true);
      });

      dialog.find('.count').text(groupIds.length);
      dialog.modal('show');
    });
  };
}
