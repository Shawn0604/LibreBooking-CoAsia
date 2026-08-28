{include file='globalheader.tpl' DataTable=true}

<div id="page-manage-groups" class="admin-page">
    {capture name=headerActions}
        <div class="btn-group btn-group-sm">
            <a href="#" class="add-group btn btn-primary" id="add-group">
                <i class="bi bi-plus-circle-fill me-1"></i>
                {translate key="AdminGroupAddCustom"}
            </a>
            {if $CanExportGroups}
                <a href="{$smarty.server.SCRIPT_NAME}?dr=export" download class="btn btn-outline-secondary"
                    id="export-groups" target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-upload me-1"></i>{translate key="Export"}
                </a>
            {/if}
        </div>
    {/capture}
    {include file='Admin/admin_page_shell.tpl' TitleKey='AdminSectionUsers' DescriptionKey='AdminGroupsAdvancedDescription' HeaderActions=$smarty.capture.headerActions}

    <div class="admin-subnav">
        <a class="admin-subnav-link" href="{$Path}admin/manage_users.php">{translate key=ManageUsers}</a>
        <a class="admin-subnav-link active" href="{$Path}admin/manage_groups.php">{translate key=ManageGroups}</a>
    </div>

    <div class="admin-page-toolbar">
        <label class="admin-search" for="groupSearch">
            <i class="bi bi-search"></i>
            <input type="text" id="groupSearch" placeholder="{translate key=AdminGroupSearchPlaceholder}" />
        </label>
    </div>

    <div class="card shadow admin-clean-table-card">
        <div class="table-responsive">
            {assign var=tableId value=groupList}
            <table class="table admin-clean-table w-100" id="{$tableId}">
                <thead>
                    <tr>
                        <th>{translate key='GroupName'}</th>
                        <th>{translate key='AdminGroupPurpose'}</th>
                        <th>{translate key='GroupMembers'}</th>
                        <th>{translate key='AdminGroupPermissionSummary'}</th>
                        <th>{translate key='GroupAdmin'}</th>
                        <th>{translate key='GroupAutomaticallyAdd'}</th>
                        <th class="text-end">{translate key='Actions'}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$groups item=group}
                        {assign var=gid value=$group->Id}
                        {assign var=gd value=$GroupDisplays[$gid]|default:null}
                        {if $gd && $gd.labelKey != ''}
                            {capture assign=gLabel}{translate key=$gd.labelKey}{/capture}
                        {else}
                            {assign var=gLabel value=$group->Name}
                        {/if}
                        <tr data-group-id="{$gid}" data-group-default="{$group->IsDefault}"
                            data-group-reserved="{if $gd && $gd.isReserved}1{else}0{/if}"
                            data-group-role="{if $gd && $gd.primaryRoleLevel}{$gLabel|escape}{/if}"
                            data-group-system-name="{$group->Name|escape}"
                            data-name="{$gLabel|escape}"
                            data-search="{$gLabel|escape|strtolower} {$group->Name|escape|strtolower}">
                            <td>
                                <div class="fw-bold dataGroupName">{$gLabel|escape}</div>
                            </td>
                            <td class="admin-cell-secondary">
                                {if $gd && $gd.purposeKey != ''}{translate key=$gd.purposeKey}{else}&mdash;{/if}
                            </td>
                            <td>{$group->MemberCount()}</td>
                            <td class="admin-cell-secondary">
                                {if $gd && $gd.permissionSummaryKey != ''}
                                    {translate key=$gd.permissionSummaryKey}
                                {elseif $group->ResourcePermissionCount() > 0}
                                    {translate key=AdminGroupPermSummaryBookable args=$group->ResourcePermissionCount()}
                                {else}
                                    &mdash;
                                {/if}
                            </td>
                            <td>{$group->AdminGroupName|default:"&mdash;"}</td>
                            <td>
                                {if $group->IsDefault}
                                    <span class="admin-badge admin-badge-inprogress">{translate key='Yes'}</span>
                                {else}
                                    <span class="admin-cell-secondary">{translate key='No'}</span>
                                {/if}
                            </td>
                            <td class="text-end">
                                <button type="button" class="admin-row-edit-btn update manageGroup" data-group-id="{$gid}">
                                    <i class="bi bi-sliders"></i>{translate key='AdminGroupManage'}
                                </button>
                            </td>
                        </tr>
                    {foreachelse}
                        <tr><td colspan="7" class="admin-empty">{translate key=AdminGroupNoResults}</td></tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
        <div class="admin-pager" id="groupPager"></div>
    </div>

    <div class="mt-2">
        {if $ShowingAllGroups}
            <a href="{$smarty.server.SCRIPT_NAME}" class="link-secondary small">{translate key=AdminGroupShowActiveOnly}</a>
        {elseif $HiddenGroupCount > 0}
            <a href="{$smarty.server.SCRIPT_NAME}?showAll=1" class="link-secondary small">
                {translate key=AdminGroupShowAll args=$HiddenGroupCount}
            </a>
        {/if}
    </div>

    <input type="hidden" id="activeId" />

    {* 成員清單直接內嵌在 #editDialog 抽屜的「群組成員」分區(#groupUserList / #userSearch),
       不再是彈窗。addUser/removeUser 的 async form 成功後回呼 changeMembers() 重新載入清單。 *}

    <div class="modal fade" id="permissionsDialog" tabindex="-1" role="dialog" aria-labelledby="permissionsDialogLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="permissionsDialogLabel">{translate key=Permissions}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
                </div>
                <div class="modal-body">
                    <form id="permissionsForm" method="post">
                        <div class="d-flex align-items-center gap-1">
                            <a href="#" id="checkNoResources" class="link-primary">{translate key=None}</a>
                            <div class="vr"></div>
                            <a href="#" id="checkAllResourcesFull" class="link-primary">{translate key=FullAccess}</a>
                            <div class="vr"></div>
                            <a href="#" id="checkAllResourcesView" class="link-primary">{translate key=ViewOnly}</a>
                        </div>

                        {assign var=tableIdFilter value="permissionsDialogtable"}
                        <table class="table table-striped table-hover overflow-auto w-100" id="{$tableIdFilter}">
                            <thead class="visually-hidden">
                                <tr>
                                    <th>{translate key="Resource"}</th>
                                </tr>
                            </thead>
                            {foreach from=$resources item=resource}
                                {assign var=rid value=$resource->GetResourceId()}
                                <tr>
                                    <td>
                                        <div class="permissionRow form-group clearfix">
                                            <label for="permission_{$rid}"
                                                class="float-start">{$resource->GetName()}</label>
                                            <select class="form-select form-select-sm w-auto resourceId float-end"
                                                {formname key=RESOURCE_ID multi=true}id="permission_{$rid}">
                                                <option value="{$rid}_none" class="none">{translate key=None}</option>
                                                <option value="{$rid}_0" class="full">{translate key=FullAccess}</option>
                                                <option value="{$rid}_1" class="view">{translate key=ViewOnly}</option>
                                            </select>
                                        </div>
                                    </td>
                                </tr>
                            {/foreach}
                        </table>
                    </form>
                </div>
                <div class="modal-footer">
                    {cancel_button}
                    {update_button form='permissionsForm' submit="true"}
                    {indicator}
                </div>
            </div>
        </div>
    </div>

    <form id="removeUserForm" method="post">
        <input type="hidden" id="removeUserId" {formname key=USER_ID} />
    </form>

    <form id="addUserForm" method="post">
        <input type="hidden" id="addUserId" {formname key=USER_ID} />
    </form>

    <div class="offcanvas offcanvas-end admin-drawer" id="addGroupDialog" tabindex="-1" aria-labelledby="addDialogLabel">
        <form id="addGroupForm" method="post" class="h-100 d-flex flex-column">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="addDialogLabel">{translate key=AdminGroupAddCustom}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body flex-grow-1">
                <div id="addGroupResults" class="error" style="display:none;"></div>
                <div class="form-group">
                    <label class="fw-bold" for="addGroupName">{translate key=AdminGroupDisplayName}<i
                            class="bi bi-asterisk text-danger align-top" style="font-size: 0.5rem;"></i></label>
                    <input {formname key=GROUP_NAME} type="text" id="addGroupName" required
                        class="form-control required has-feedback" />
                    <div class="form-text">{translate key=AdminGroupAddCustomHint}</div>
                </div>
            </div>
            <div class="admin-drawer-footer">
                {cancel_button}
                {add_button}
                {indicator}
            </div>
        </form>
    </div>

    <div class="modal fade" id="deleteDialog" tabindex="-1" role="dialog" aria-labelledby="deleteDialogLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <form id="deleteGroupForm" method="post">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteDialogLabel">{translate key=Delete}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <div>{translate key=DeleteWarning}</div>
                            <div>{translate key=DeleteGroupWarning}</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        {cancel_button}
                        {delete_button}
                        {indicator}
                    </div>
                </div>
            </form>
        </div>
    </div>

    {* Consolidated 群組詳情 drawer. Opened by the row's 「管理群組」 button. group.js
       (editGroup) fills the fields from the row's data-* + dr= fetches, and toggles the
       reserved-only / custom-only bits by data-group-reserved. Each section keeps its own
       existing async form/modal + action -- this drawer only relocates and relabels the
       entry points, no combined backend action. *}
    <div class="offcanvas offcanvas-end admin-drawer admin-drawer-wide" id="editDialog" tabindex="-1"
        aria-labelledby="editDialogLabel">
        <form id="editGroupForm" method="post" class="h-100 d-flex flex-column">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="editDialogLabel">{translate key=AdminGroupDetailTitle}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body flex-grow-1">

                <div class="admin-drawer-section-title">{translate key=BasicInformation}</div>
                <div class="js-reserved-note alert alert-secondary py-2 px-3 d-none">
                    <i class="bi bi-shield-lock me-1"></i>{translate key=AdminGroupReservedNote}
                </div>
                <div class="form-group">
                    <label class="fw-bold" for="editGroupName">{translate key=AdminGroupDisplayName}</label>
                    <input type="text" id="editGroupName" class="form-control has-feedback"
                        {formname key=GROUP_NAME} />
                </div>

                <div class="admin-drawer-section-title mt-4">{translate key=GroupMembers}</div>
                <div class="form-group mb-2">
                    <label class="fw-bold form-label" for="userSearch">{translate key=AdminGroupAddMember}</label>
                    <input type="text" id="userSearch" class="form-control form-control-sm" autocomplete="off"
                        placeholder="{translate key=AdminGroupAddMemberPlaceholder}" />
                </div>
                <div class="mb-1"><span class="fw-bold"><span id="totalUsers">0</span> {translate key=UsersInGroup}</span></div>
                <div id="groupUserList" class="admin-group-member-list border rounded p-2"></div>

                <div class="js-perm-section">
                    <div class="admin-drawer-section-title mt-4">{translate key=AdminGroupPermissionSummary}</div>
                    <p class="admin-cell-secondary mb-2">{translate key=AdminGroupPermissionsHint}</p>
                    <button type="button" class="btn btn-outline-primary btn-sm permissions">
                        <i class="bi bi-key me-1"></i>{translate key=AdminGroupSetPermissions}
                    </button>
                </div>
                <div class="js-perm-note alert alert-secondary py-2 px-3 mt-4 d-none">
                    {translate key=AdminGroupPermissionsNotApplicable}
                </div>

                {if $CanChangeRoles}
                    <div class="admin-drawer-section-title mt-4">{translate key=GroupAdmin}</div>
                    <p class="admin-cell-secondary mb-2">{translate key=AdminGroupAdminHint}</p>
                    <button type="button" class="btn btn-outline-primary btn-sm groupAdmin">
                        <i class="bi bi-person-badge me-1"></i>{translate key=AdminGroupAssignGroupAdmin}
                    </button>
                {/if}

                <div class="admin-drawer-section-title mt-4">{translate key=GroupAutomaticallyAdd}</div>
                <p class="admin-cell-secondary mb-2">{translate key=AdminGroupAutoJoinHint}</p>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="editGroupIsDefault"
                        {formname key=IS_DEFAULT} />
                    <label class="form-check-label" for="editGroupIsDefault">{translate key=AdminGroupAutoJoinToggle}</label>
                </div>

                <details class="admin-drawer-advanced mt-4 js-danger-section">
                    <summary class="admin-drawer-advanced-summary text-danger">{translate key=AdminGroupDangerZone}</summary>
                    <div class="mt-2">
                        <p class="admin-cell-secondary">{translate key=AdminGroupDeleteHint}</p>
                        <button type="button" class="btn btn-outline-danger btn-sm delete">
                            <i class="bi bi-trash3 me-1"></i>{translate key=AdminGroupDelete}
                        </button>
                    </div>
                </details>
            </div>
            <div class="admin-drawer-footer admin-drawer-footer-sticky">
                {cancel_button}
                <button type="button" class="btn btn-primary btn-sm save" id="editGroupSaveBtn">
                    <i class="bi bi-check2-circle me-1"></i>{translate key=AdminGroupSaveBasic}
                </button>
                {indicator}
            </div>
        </form>
    </div>

    {if $CanChangeRoles}
    <div class="modal fade" id="groupAdminDialog" tabindex="-1" role="dialog" aria-labelledby="groupAdminDialogLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <form id="groupAdminForm" method="post">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="groupAdminDialogLabel">{translate key=WhoCanManageThisGroup}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group has-feedback">
                            <label for="groupAdmin"
                                class="off-screen fw-bold">{translate key=WhoCanManageThisGroup}</label>
                            <select {formname key=GROUP_ADMIN} class="form-select" id="groupAdmin">
                                <option value="">-- {translate key=None} --</option>
                                {foreach from=$AdminGroups item=adminGroup}
                                <option value="{$adminGroup->Id}">{$adminGroup->Name}</option>
                                {/foreach}
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        {cancel_button}
                        {update_button}
                        {indicator}
                    </div>
                </div>
            </form>
        </div>
    </div>
    {/if}

    {if $CanImportGroups}
    <div id="importGroupsDialog" class="modal" tabindex="-1" role="dialog" aria-labelledby="importGroupsModalLabel"
        aria-hidden="true">
        <form id="importGroupsForm" class="form" role="form" method="post" enctype="multipart/form-data"
            ajaxAction="{ManageGroupsActions::Import}">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="importGroupsModalLabel">{translate key=Import}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
                    </div>
                    <div class="modal-body">
                        <div id="importInstructions" class="alert alert-info">
                            <div class="note fst-italic">{translate key=GroupsImportInstructions}</div>
                            <a href="{$smarty.server.SCRIPT_NAME}?dr=template" class="alert-link"
                                download="{$smarty.server.SCRIPT_NAME}?dr=template"
                                target="_blank">{translate key=GetTemplate} <span class="bi bi-download"></span></a>
                        </div>
                        <div id="importGroupsResults" class="validationSummary alert alert-danger d-none">
                            <ul>
                                {async_validator id="fileExtensionValidator" key=""}
                            </ul>
                        </div>
                        <div id="importErrors" class="alert alert-danger d-none"></div>
                        <div id="importResult" class="alert alert-success d-none">
                            <span>{translate key=RowsImported}</span>

                            <span id="importCount" class="inline fw-bold">0</span>
                            <span>{translate key=RowsSkipped}</span>

                            <span id="importSkipped" class="inline fw-bold">0</span>
                            <a class="alert-link" href="{$smarty.server.SCRIPT_NAME}">{translate key=Done}</a>
                        </div>
                        <div class="">
                            <label class="visually-hidden" for="groupsImportFile">{translate key=File}</label>
                            <input type="file" class="form-control" {formname key=GROUP_IMPORT_FILE}
                                id="groupsImportFile" />
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" id="updateOnImport"
                                    {formname key=UPDATE_ON_IMPORT} />
                                <label class="form-check-label"
                                    for="updateOnImport">{translate key=UpdateGroupsOnImport}</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        {cancel_button}
                        {add_button key=Import}
                        {indicator}
                    </div>
                </div>
            </div>
        </form>
    </div>
    {/if}

    {csrf_token}

    {include file="javascript-includes.tpl" DataTable=true}
    {* The group list no longer uses DataTables' own chrome (pagination/info/export
       buttons) -- it's the client-side admin-table-paginate.js pattern like 使用者管理.
       {datatablefilter} stays: it still drives the permissions picker (#permissionsDialogtable). *}
    {datatablefilter tableId=$tableIdFilter}
    {jsfile src="ajax-helpers.js"}
    {jsfile src="autocomplete.js"}
    {jsfile src="admin/admin-table-paginate.js"}
    {jsfile src="admin/group.js"}
    {vendor_js src="jquery-form/3.09/jquery.form-3.09.min.js"}

    <script>
        (function () {
            var pager = AdminTablePaginate({ tableId: 'groupList', containerId: 'groupPager', pageSize: 20 });
            var searchInput = document.getElementById('groupSearch');
            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    var query = searchInput.value.trim().toLowerCase();
                    $('#groupList > tbody > tr[data-group-id]').each(function () {
                        var haystack = $(this).attr('data-search') || '';
                        $(this).toggleClass('admin-search-hidden', query !== '' && haystack.indexOf(query) === -1);
                    });
                    pager.refresh();
                });
            }
        })();
    </script>


    <script type="text/javascript">
        $(document).ready(function() {

            var actions = {
                activate: '{ManageGroupsActions::Activate}',
                deactivate: '{ManageGroupsActions::Deactivate}',
                permissions: '{ManageGroupsActions::Permissions}',
                password: '{ManageGroupsActions::Password}',
                removeUser: '{ManageGroupsActions::RemoveUser}',
                addUser: '{ManageGroupsActions::AddUser}',
                addGroup: '{ManageGroupsActions::AddGroup}',
                updateGroup: '{ManageGroupsActions::UpdateGroup}',
                deleteGroup: '{ManageGroupsActions::DeleteGroup}',
                roles: '{ManageGroupsActions::Roles}',
                groupAdmin: '{ManageGroupsActions::GroupAdmin}',
                adminGroups: '{ManageGroupsActions::AdminGroups}',
                resourceGroups: '{ManageGroupsActions::ResourceGroups}',
                scheduleGroups: '{ManageGroupsActions::ScheduleGroups}',
                importGroups: '{ManageGroupsActions::Import}'
            };

            var dataRequests = {
                permissions: 'permissions',
                roles: 'roles',
                groupMembers: 'groupMembers',
                adminGroups: '{ManageGroupsActions::AdminGroups}',
                resourceGroups: '{ManageGroupsActions::ResourceGroups}',
                scheduleGroups: '{ManageGroupsActions::ScheduleGroups}'
            };

            var groupOptions = {
                userAutocompleteUrl: "../ajax/autocomplete.php?type={AutoCompleteType::User}",
                groupAutocompleteUrl: "../ajax/autocomplete.php?type={AutoCompleteType::Group}",
                groupsUrl: "{$smarty.server.SCRIPT_NAME}",
                        permissionsUrl: '{$smarty.server.SCRIPT_NAME}',
                        rolesUrl: '{$smarty.server.SCRIPT_NAME}',
                        submitUrl: '{$smarty.server.SCRIPT_NAME}',
                        saveRedirect: '{$smarty.server.SCRIPT_NAME}',
                        selectGroupUrl: '{$smarty.server.SCRIPT_NAME}?gid=',
                        actions: actions,
                        dataRequests: dataRequests
                    };

                    var groupManagement = new GroupManagement(groupOptions);
                    groupManagement.init();

                    //$('#add-group-panel').showHidePanel();
                });
            </script>
        </div>
        {include file='globalfooter.tpl'}
