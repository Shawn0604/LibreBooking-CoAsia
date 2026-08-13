{include file='globalheader.tpl' InlineEdit=true DataTable=true}

<div id="page-manage-users" class="admin-page">

    {capture name=headerActions}
        <div class="btn-group btn-group-sm">
            {if $CanCreateUsers}
                <a href="#" id="add-user" class="add-link add-user add-group btn btn-primary">
                    <i class="bi bi-plus-circle-fill me-1"></i>
                    {translate key="AddUser"}
                </a>
            {/if}

            {if $CanCreateUsers or $CanExportUsers}
            <button class="btn btn-primary btn-sm dropdown-toggle" type="button" id="moreUserActions"
                data-bs-toggle="dropdown" aria-expanded="false" aria-label="{translate key='More'}">
                <i class="bi bi-three-dots"></i>
            </button>

            <ul class="dropdown-menu" aria-labelledby="moreUserActions">
                {if $CanCreateUsers}
                    <li>
                        <a href="#" id="invite-users" class="dropdown-item">
                            <i class="bi bi-send me-1"></i>
                            {translate key="InviteUsers"}
                        </a>
                    </li>
                    <li>
                        <a href="#" id="import-users" class="dropdown-item">
                            <i class="bi bi-download me-1"></i>
                            {translate key="Import"}
                        </a>
                    </li>
                {/if}
                {if $CanExportUsers}
                    <li>
                        <a href="{$ExportUrl}" download id="export-users" class="dropdown-item" target="_blank"
                            rel="noopener noreferrer">
                            <i class="bi bi-upload me-1"></i>
                            {translate key="Export"}
                        </a>
                    </li>
                {/if}
            </ul>
            {/if}
        </div>
    {/capture}
    {include file='Admin/admin_page_shell.tpl' TitleKey='AdminSectionUsers' DescriptionKey='AdminUsersDescription' HeaderActions=$smarty.capture.headerActions}

    <div class="admin-page-toolbar">
        <form id="filterForm" class="admin-toolbar-row" role="form">
            <label class="admin-search" for="userSearch">
                <i class="bi bi-search"></i>
                <input type="text" id="userSearch" placeholder="{translate key=AdminUserSearchPlaceholder}" />
            </label>
            <select id="filterStatusId" class="form-select form-select-sm admin-toolbar-select" aria-label="{translate key=Status}">
                {html_options selected=$FilterStatusId options=$statusDescriptions}
            </select>
            <select id="userSortOrder" class="form-select form-select-sm admin-toolbar-select" aria-label="{translate key=AdminUserSortBy}">
                <option value="name-asc" selected>{translate key=AdminUserSortNameAsc}</option>
                <option value="name-desc">{translate key=AdminUserSortNameDesc}</option>
                <option value="lastlogin-desc">{translate key=AdminUserSortLastLoginDesc}</option>
                <option value="lastlogin-asc">{translate key=AdminUserSortLastLoginAsc}</option>
            </select>
        </form>
    </div>

    {* AD-managed flag is a custom attribute rather than a dedicated schema column -- looked up
       here by label once instead of per-row, since it has no stable/hardcoded id (it's created by
       database_schema/upgrades/4.2/data.sql). *}
    {assign var=adManagedAttrId value=0}
    {foreach from=$AttributeList item=attr}
        {if $attr->Label() == 'AD Managed Account'}{assign var=adManagedAttrId value=$attr->Id()}{/if}
    {/foreach}

    <div class="card shadow admin-clean-table-card">
        <div class="table-responsive">
            {assign var=tableId value='userList'}
            <table class="table admin-clean-table w-100" id="{$tableId}">
                <thead>
                    <tr>
                        <th>{translate key=AdminUserColumnNameAvatar}</th>
                        <th>{translate key=AdminUserColumnAccount}</th>
                        <th>{translate key=AdminUserRole}</th>
                        <th>{translate key=AdminUserColumnEmailNotify}</th>
                        <th>{translate key=AdminUserColumnLineNotify}</th>
                        <th>{translate key=Status}</th>
                        <th>{translate key=AdminUserColumnLastLogin}</th>
                        <th class="text-end">{translate key=Actions}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$users item=user}
                        {assign var=id value=$user->Id}
                        {assign var=isAdManaged value=false}
                        {if $adManagedAttrId > 0 && $user->GetAttributeValue($adManagedAttrId) == '1'}{assign var=isAdManaged value=true}{/if}
                        {assign var=lineUserId value=$user->Preferences->Get('LineUserId')}
                        {* CoAsia's simplified two-role model -- a user is 系統管理員 if any group they
                           belong to carries the APPLICATION_ADMIN role (GroupItemView::IsAdmin()),
                           same check AuthorizationService uses to decide $CanViewAdmin. $Groups is
                           every group site-wide, keyed by id (Pages/Admin/ManageUsersPage.php
                           BindGroups()); user_groups membership already comes down per-row as
                           $user->GroupIds, so no extra query per row. *}
                        {assign var=isUserAdminRole value=false}
                        {foreach from=$user->GroupIds item=gid}
                            {if isset($Groups[$gid]) && $Groups[$gid]->IsAdmin()}{assign var=isUserAdminRole value=true}{/if}
                        {/foreach}
                        <tr data-userId="{$id}"
                            data-name="{fullname first=$user->First|default:''|unescape:'html' last=$user->Last|default:''|unescape:'html' ignorePrivacy="true"}"
                            data-search="{$user->First|default:''|unescape:'html'|lower} {$user->Last|default:''|unescape:'html'|lower} {$user->Username|lower} {$user->Email|lower}">
                            <td>
                                <div class="admin-user-cell">
                                    <span class="admin-avatar">{$user->First|default:$user->Username|truncate:1:''|upper}</span>
                                    <span class="admin-user-name">{fullname first=$user->First|default:''|unescape:'html' last=$user->Last|default:''|unescape:'html' ignorePrivacy="true"}</span>
                                </div>
                            </td>
                            <td>
                                <div class="admin-user-account">
                                    <span class="admin-user-username">{$user->Username}</span>
                                    <span class="admin-user-email">{$user->Email}</span>
                                </div>
                            </td>
                            <td>
                                {if $isUserAdminRole}
                                    <span class="admin-badge admin-badge-inprogress">{translate key=AdminUserRoleAdmin}</span>
                                {else}
                                    <span class="admin-badge admin-badge-ended">{translate key=AdminUserRoleUser}</span>
                                {/if}
                            </td>
                            <td>
                                {if $user->EmailNotificationsEnabled}
                                    <span class="admin-badge admin-badge-inprogress">{translate key=AdminUserNotifyOn}</span>
                                {else}
                                    <span class="admin-badge admin-badge-ended">{translate key=AdminUserNotifyOff}</span>
                                {/if}
                            </td>
                            <td>
                                {if empty($lineUserId)}
                                    <span class="admin-badge admin-badge-ended">{translate key=AdminUserLineNotBound}</span>
                                {elseif $user->Preferences->Get('LineNotificationsEnabled') !== '0'}
                                    <span class="admin-badge admin-badge-inprogress">{translate key=AdminUserNotifyOn}</span>
                                {else}
                                    <span class="admin-badge admin-badge-ended">{translate key=AdminUserNotifyOff}</span>
                                {/if}
                            </td>
                            <td>
                                <span class="admin-badge {if $user->StatusId == 1}admin-badge-inprogress{else}admin-badge-ended{/if}">{$statusDescriptions[$user->StatusId]}</span>
                                {indicator id="userStatusIndicator"}
                            </td>
                            <td data-order="{format_date date=$user->LastLogin format='Y-m-d H:i' timezone=$Timezone}">
                                {format_date date=$user->LastLogin key=short_datetime timezone=$Timezone}</td>
                            <td class="text-end">
                                <div class="admin-row-actions">
                                    {if $CanEditUsers}
                                        <button type="button" class="admin-row-edit-btn update edit">
                                            <i class="bi bi-pencil-square"></i>{translate key=AdminUserViewEdit}
                                        </button>
                                    {/if}
                                    <div class="dropdown">
                                        <button type="button" class="admin-row-menu-btn" data-bs-toggle="dropdown"
                                            aria-label="{translate key=Actions}">
                                            <i class="bi bi-three-dots"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end" role="menu">
                                        {if $CanChangePermissions}
                                            <li role="presentation"><a role="menuitem" href="#"
                                                    class="dropdown-item update changePermissions">{translate key="Permissions"}</a>
                                            </li>
                                        {/if}
                                        <li role="presentation"><a role="menuitem" href="#"
                                                class="dropdown-item update viewReservations">{translate key="Reservations"}</a>
                                        </li>
                                        {if $CanChangePasswords}
                                            <li role="presentation"><a role="menuitem" href="#"
                                                    class="dropdown-item update resetPassword">{translate key="ChangePassword"}</a>
                                            </li>
                                        {/if}
                                        {if $CreditsEnabled}
                                            <li role="presentation"><a role="menuitem"
                                                    href="credit_log.php?{QueryStringKeys::USER_ID}={$id}"
                                                    class="dropdown-item">{translate key="CreditHistory"}</a>
                                            </li>
                                        {/if}
                                        {if $PerUserColors && $CanChangeColors}
                                            <li role="presentation"><a role="menuitem" href="#"
                                                    class="dropdown-item update changeColor">{translate key="Color"}</a>
                                            </li>
                                        {/if}
                                        {if $CanChangeUserStatus}
                                            <li role="presentation">
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li role="presentation"><a role="menuitem" href="#"
                                                    class="dropdown-item update changeStatus">{if $user->StatusId == 1}{translate key="Deactivate"}{else}{translate key="Activate"}{/if}</a>
                                            </li>
                                        {/if}
                                        {if $CanDeleteUsers}
                                            <li role="presentation">
                                                <hr class="dropdown-divider">
                                            </li>
                                            {if $isAdManaged}
                                                <li role="presentation"><span
                                                        class="dropdown-item admin-row-menu-disabled"
                                                        title="{translate key=AdminUserCannotDeleteAD}">{translate key="Delete"}</span>
                                                </li>
                                            {else}
                                                <li role="presentation"><a role="menuitem" href="#"
                                                        class="dropdown-item update delete text-danger">{translate key="Delete"}</a>
                                                </li>
                                            {/if}
                                        {/if}
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    {foreachelse}
                        <tr>
                            <td colspan="8" class="admin-empty">{translate key=AdminUserNoResults}</td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
        <div class="admin-pager" id="userPager"></div>
    </div>

    {if $CanCreateUsers}
    <div id="addUserDialog" class="offcanvas offcanvas-end admin-drawer admin-drawer-wide" tabindex="-1"
        aria-labelledby="addUserModalLabel">
        <form id="addUserForm" class="form h-100 d-flex flex-column" role="form" method="post" ajaxAction="{ManageUsersActions::AddUser}">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="addUserModalLabel">{translate key=AddUser}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body flex-grow-1 row gy-2">
                        <div class="col-12">
                            <div id="addUserResults" class="validationSummary alert alert-danger d-none">
                                <ul>
                                    {async_validator id="addUserEmailformat" key="ValidEmailRequired"}
                                    {async_validator id="addUserUniqueemail" key="UniqueEmailRequired"}
                                    {async_validator id="addUserUsername" key="UniqueUsernameRequired"}
                                    {async_validator id="addAttributeValidator" key=""}
                                </ul>
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-6">
                            <div class="form-group">
                                <label class="fw-bold" for="addUsername">{translate key="Username"}<i
                                        class="bi bi-asterisk text-danger align-top"
                                        style="font-size: 0.5rem;"></i></label>
                                <input type="text" {formname key="USERNAME"} class="required form-control has-feedback"
                                    required id="addUsername" />
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-6">
                            <div class="form-group">
                                <label class="fw-bold" for="addEmail">{translate key="Email"}<i
                                        class="bi bi-asterisk text-danger align-top"
                                        style="font-size: 0.5rem;"></i></label>
                                <input type="text" {formname key="EMAIL"} class="required form-control has-feedback"
                                    required id="addEmail" />
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-6">
                            <div class="form-group has-feedback">
                                <label class="fw-bold" for="addFname">{translate key="FirstName"}<i
                                        class="bi bi-asterisk text-danger align-top"
                                        style="font-size: 0.5rem;"></i></label>
                                <input type="text" {formname key="FIRST_NAME"}
                                    class="required form-control has-feedback" required id="addFname" />
                                <i class="glyphicon glyphicon-asterisk form-control-feedback"
                                    data-bv-icon-for="addFname"></i>
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-6">
                            <div class="form-group has-feedback">
                                <label class="fw-bold" for="addLname">{translate key="LastName"}<i
                                        class="bi bi-asterisk text-danger align-top"
                                        style="font-size: 0.5rem;"></i></label>
                                <input type="text" {formname key="LAST_NAME"} class="required form-control has-feedback"
                                    required id="addLname" />
                                <i class="glyphicon glyphicon-asterisk form-control-feedback"
                                    data-bv-icon-for="addLname"></i>
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-6">
                            <div class="form-group has-feedback">
                                <label class="fw-bold" for="addPassword">{translate key="Password"}<i
                                        class="bi bi-asterisk text-danger align-top"
                                        style="font-size: 0.5rem;"></i></label>
                                <input type="text" {formname key="PASSWORD"} class="required form-control has-feedback"
                                    required id="addPassword" />
                                <i class="glyphicon glyphicon-asterisk form-control-feedback"
                                    data-bv-icon-for="addPassword"></i>
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-6">
                            <div class="form-group has-feedback">
                                <label class="fw-bold" for="addTimezone">{translate key="Timezone"}</label>
                                <select id="addTimezone" {formname key='TIMEZONE'} class="form-select">
                                    {html_options values=$Timezones output=$TimezoneLabels selected=$Timezone}
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-6">
                            <div class="form-group">
                                <label class="fw-bold" for="addPhone">{translate key="Phone"}</label>
                                <input type="text" {formname key="PHONE"} class="form-control" id="addPhone" />
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-6">
                            <div class="form-group">
                                <label class="fw-bold" for="addOrganization">{translate key="Organization"}</label>
                                <input type="text" {formname key="ORGANIZATION"} class="form-control"
                                    id="addOrganization" />
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-6">
                            <div class="form-group">
                                <label class="fw-bold" for="addPosition">{translate key="Position"}</label>
                                <input type="text" {formname key="POSITION"} class="form-control" id="addPosition" />
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-6">
                            <div class="form-group has-feedback">
                                <label class="fw-bold" for="addRole">{translate key="AdminUserRole"}</label>
                                <select id="addRole" {formname key='ROLE'} class="form-select">
                                    <option value="user" selected>{translate key="AdminUserRoleUser"}</option>
                                    <option value="admin">{translate key="AdminUserRoleAdmin"}</option>
                                </select>
                            </div>
                        </div>
                        {if $AttributeList|default:array()|count > 0}
                            <div class="col-12 col-sm-6">
                                {control type="AttributeControl" attribute=$AttributeList[0]}
                            </div>
                        {else}
                            <div class="col-sm-12 col-md-6">&nbsp;</div>
                        {/if}

                        {if $AttributeList|default:array()|count > 1}
                            {for $i=1 to $AttributeList|default:array()|count-1}
                                <div class="col-12 col-sm-6">
                                    {control type="AttributeControl" attribute=$AttributeList[$i]}
                                </div>
                            {/for}
                        {/if}
                        <div class="clearfix"></div>
            </div>
            <div class="admin-drawer-footer">
                <div class="form-check me-auto">
                    <input class="form-check-input" type="checkbox" id="sendAddEmail" checked="checked"
                        {formname key=SEND_AS_EMAIL} />
                    <label class="form-check-label" for="sendAddEmail">{translate key=NotifyUser}</label>
                </div>
                {cancel_button}
                {add_button}
                {indicator}
            </div>
        </form>
    </div>

    <div id="importUsersDialog" class="modal" tabindex="-1" role="dialog" aria-labelledby="importUsersModalLabel"
        aria-hidden="true">
        <form id="importUsersForm" class="form" role="form" method="post" enctype="multipart/form-data"
            ajaxAction="{ManageUsersActions::ImportUsers}">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="importUsersModalLabel">{translate key=Import}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
                    </div>
                    <div class="modal-body gap-3">
                        <div id="importUserResults" class="validationSummary alert alert-danger d-none">
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
                        <div id="importInstructions" class="alert alert-info">
                            <div class="note fst-italic">{translate key=UserImportInstructions}</div>
                            <a href="{$smarty.server.SCRIPT_NAME}?dr=template"
                                download="{$smarty.server.SCRIPT_NAME}?dr=template" class="alert-link"
                                target="_blank">{translate key=GetTemplate}<span class="bi bi-download ms-1"></span></a>
                        </div>
                        <div>
                            <input class="form-control" type="file" {formname key=USER_IMPORT_FILE} accept=".csv"
                                id="userImportFile" />
                            <label for="userImportFile" class="visually-hidden">User Import File</label>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="updateOnImport"
                                    {formname key=UPDATE_ON_IMPORT} />
                                <label class="form-check-label"
                                    for="updateOnImport">{translate key=UpdateUsersOnImport}</label>
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

    <input type="hidden" id="activeId" />

    {if $CanChangePermissions}
    <div id="permissionsDialog" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="permissionsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="permissionsModalLabel">{translate key=Permissions}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
                </div>
                <div class="modal-body">
                    <form id="permissionsForm" method="post" ajaxAction="{ManageUsersActions::Permissions}">
                        <div class="alert alert-warning">{translate key=UserPermissionInfo}</div>
                        <a href="#" class="link-primary" id="checkNoResources">{translate key=None}</a> |
                        <a href="#" class="link-primary" id="checkAllResourcesFull">{translate key=FullAccess}</a> |
                        <a href="#" class="link-primary" id="checkAllResourcesView">{translate key=ViewOnly}</a>
                        {assign var=tableIdFilter value="permissionsFormFilter"}
                        <table class="table table-striped table-hover w-100" id="{$tableIdFilter}">
                            <thead>
                                <tr>
                                    <th>{translate key="Resource"}</th>
                                </tr>
                            </thead>
                            {foreach from=$resources item=resource}
                                <tr>
                                    <td>
                                        {assign var=rid value=$resource->GetResourceId()}
                                        <div class="permissionRow form-group clearfix">
                                            <label for="permission_{$rid}"
                                                class="float-start">{$resource->GetName()}</label>
                                            <select class="float-end form-select form-select-sm resourceId inline-block"
                                                style="width:auto;" {formname key=RESOURCE_ID multi=true}
                                                id="permission_{$rid}">
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
                    {update_button submit=true form="permissionsForm"}
                    {indicator}
                </div>
            </div>
        </div>
    </div>
    {/if}

    {if $CanChangePasswords}
    <div id="passwordDialog" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="passwordModalLabel"
        aria-hidden="true">
        <form id="passwordForm" method="post" ajaxAction="{ManageUsersActions::Password}" class="was-validated">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="passwordModalLabel">{translate key=Password}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group has-feedback">
                            <label for="password">{translate key=Password}</label>
                            {textbox type="password" name="PASSWORD" class="required" required="required" value=""}
                        </div>
                    </div>
                    <div class="modal-footer">
                        {cancel_button}
                        {update_button}
                        {indicator}
                    </div>
                </div>
            </div>
        </form>
    </div>
    {/if}

    {if $CanCreateUsers}
    <div id="invitationDialog" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="invitationModalLabel"
        aria-hidden="true">
        <form id="invitationForm" method="post" ajaxAction="{ManageUsersActions::InviteUsers}">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="invitationModalLabel">{translate key=InviteUsers}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group has-feedback">
                            <label for="inviteEmails">{translate key=InviteUsersLabel}</label>
                            <textarea id="inviteEmails" class="form-control" rows="5"
                                {formname key=INVITED_EMAILS}></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        {cancel_button}
                        <button type="button" class="btn btn-primary save"><span class="bi bi-send-fill"></span>
                            {translate key=InviteUsers}</button>
                        {indicator}
                    </div>
                </div>
            </div>
        </form>
    </div>
    {/if}

    {if $CanEditUsers}
    <div id="userDialog" class="offcanvas offcanvas-end admin-drawer admin-drawer-wide" tabindex="-1"
        aria-labelledby="userModalLabel">
        <form id="userForm" method="post" class="h-100 d-flex flex-column" ajaxAction="{ManageUsersActions::UpdateUser}">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="userModalLabel">{translate key=Edit}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body flex-grow-1">
                <div id="update-user-placeholder"></div>
            </div>
            <div class="admin-drawer-footer">
                {cancel_button}
                {update_button}
                {indicator}
            </div>
        </form>
    </div>
    {/if}

    {if $CanDeleteUsers}
    <div id="deleteDialog" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel"
        aria-hidden="true">
        <form id="deleteUserForm" method="post" ajaxAction="{ManageUsersActions::DeleteUser}">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteModalLabel">{translate key=Delete}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <div>{translate key=DeleteWarning}</div>

                            <div>{translate key=DeleteUserWarning}</div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        {cancel_button}
                        {delete_button}
                        {indicator}
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div id="deleteMultipleDialog" class="modal fade" tabindex="-1" role="dialog"
        aria-labelledby="deleteMultipleModalLabel" aria-hidden="true">
        <form id="deleteMultipleUserForm" method="post" ajaxAction="{ManageUsersActions::DeleteMultipleUsers}">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteMultipleModalLabel">{translate key=Delete} (<span
                                id="deleteMultipleCount"></span>)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <div>{translate key=DeleteWarning}</div>

                            <div>{translate key=DeleteMultipleUserWarning}</div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        {cancel_button}
                        {delete_button}
                        {indicator}
                    </div>
                    <div id="deleteMultiplePlaceHolder" class="d-none"></div>
                </div>
            </div>
        </form>
    </div>
    {/if}

    <div id="groupsDialog" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="groupsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="groupsModalLabel">{translate key=Groups}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
                </div>
                <div class="modal-body scrollable-modal-content">

                    <div id="groupList" class="d-none">
                        {foreach from=$Groups item=group}
                            <div class="group-item d-flex align-items-center" groupId="{$group->Id}">
                                <a href="#"><span class="visually-hidden">{$group->Name}</span>&nbsp;</a>
                                <span>{$group->Name}</span>
                            </div>
                        {/foreach}
                    </div>

                    <h6>{translate key=GroupMembership} <span class="badge bg-primary" id="groupCount">0</span></h6>
                    <div id="addedGroups">
                    </div>

                    <h5>{translate key=AvailableGroups}</h5>
                    <div id="removedGroups">
                    </div>

                    <form id="addGroupForm" method="post" ajaxAction="addUser">
                        <input type="hidden" id="addGroupId" {formname key=GROUP_ID} />
                        <input type="hidden" id="addGroupUserId" {formname key=USER_ID} />
                    </form>

                    <form id="removeGroupForm" method="post" ajaxAction="removeUser">
                        <input type="hidden" id="removeGroupId" {formname key=GROUP_ID} />
                        <input type="hidden" id="removeGroupUserId" {formname key=USER_ID} />
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="colorDialog" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="colorModalLabel"
        aria-hidden="true">
        <form id="colorForm" method="post" ajaxAction="{ManageUsersActions::ChangeColor}">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="colorModalLabel">{translate key=Color}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
                    </div>
                    <div class="modal-body">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-paint-bucket"></i></span>
                            <label for="reservationColor" class="visually-hidden">Reservation Color</label>
                            <input type="color" {formname key=RESERVATION_COLOR} id="reservationColor"
                                class="form-control form-control-color">
                        </div>
                    </div>
                    <div class="modal-footer">
                        {cancel_button}
                        {update_button}
                        {indicator}
                    </div>
                </div>
            </div>
        </form>
    </div>

    {csrf_token}
    {include file="javascript-includes.tpl" InlineEdit=true DataTable=true}
    {* DataTables itself stays loaded -- {datatablefilter tableId=$tableIdFilter} below still drives
       the permissions dialog's own resource-picker table -- but the main #userList no longer calls
       {datatable}: it's the same client-side admin-table-paginate.js pattern as
       預約管理/會議室管理/開放時間管理 now (see the sort/search script below), not DataTables' own
       pagination/info/length chrome. *}
    {datatablefilter tableId=$tableIdFilter}
    {jsfile src="ajax-helpers.js"}
    {jsfile src="autocomplete.js"}
    {jsfile src="admin/admin-table-paginate.js"}
    {jsfile src="admin/user.js"}
    {vendor_js src="jquery-form/3.09/jquery.form-3.09.min.js"}

    <script>
        (function () {
            var pager = AdminTablePaginate({ tableId: 'userList', containerId: 'userPager', pageSize: 20 });

            var sortSelect = document.getElementById('userSortOrder');
            var sortComparators = {
                'name-asc': function (a, b) {
                    return $(a).attr('data-name').localeCompare($(b).attr('data-name'));
                },
                'name-desc': function (a, b) {
                    return $(b).attr('data-name').localeCompare($(a).attr('data-name'));
                },
                'lastlogin-desc': function (a, b) {
                    return $(b).find('[data-order]').attr('data-order').localeCompare($(a).find('[data-order]').attr('data-order'));
                },
                'lastlogin-asc': function (a, b) {
                    return $(a).find('[data-order]').attr('data-order').localeCompare($(b).find('[data-order]').attr('data-order'));
                },
            };

            function sortRows() {
                var comparator = sortSelect && sortComparators[sortSelect.value];
                if (!comparator) {
                    return;
                }
                var $tbody = $('#userList > tbody');
                var rows = $tbody.find('> tr[data-userId]').get();
                rows.sort(comparator);
                $.each(rows, function (i, row) {
                    $tbody.append(row);
                });
                pager.refresh();
            }

            if (sortSelect) {
                sortSelect.addEventListener('change', sortRows);
            }

            // #userSearch also drives the jump-to-user autocomplete (see UserManagement.init in
            // user.js) -- this just adds live filtering of the visible list on the same input.
            var searchInput = document.getElementById('userSearch');
            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    var query = searchInput.value.trim().toLowerCase();
                    $('#userList > tbody > tr[data-userId]').each(function () {
                        var haystack = $(this).attr('data-search') || '';
                        $(this).toggleClass('admin-search-hidden', query !== '' && haystack.indexOf(query) === -1);
                    });
                    pager.refresh();
                });
            }
        })();
    </script>

    <script type="text/javascript">
        function setUpEditables() {
            $.fn.editable.defaults.mode = 'popup';
            $.fn.editable.defaults.toggle = 'manual';
            $.fn.editable.defaults.emptyclass = '';
            $.fn.editable.defaults.params = function(params) {
                params.CSRF_TOKEN = $('#csrf_token').val();
                return params;
            };

            var updateUrl = '{$smarty.server.SCRIPT_NAME}?action=';
            $('.inlineAttribute').editable({
                url: updateUrl + '{ManageUsersActions::ChangeAttribute}', emptytext: '-'
            });

            $('.changeCredits').editable({
                url: updateUrl + '{ManageUsersActions::ChangeCredits}', emptytext: '-'
            });
        }

        $(document).ready(function() {
            var actions = {
                activate: '{ManageUsersActions::Activate}', deactivate: '{ManageUsersActions::Deactivate}'
            };

            var userOptions = {
                userAutocompleteUrl: "../ajax/autocomplete.php?type={AutoCompleteType::MyUsers}",
                orgAutoCompleteUrl: "../ajax/autocomplete.php?type={AutoCompleteType::Organization}",
                groupsUrl: '{$smarty.server.SCRIPT_NAME}',
                groupManagementUrl: '{$ManageGroupsUrl}',
                permissionsUrl: '{$smarty.server.SCRIPT_NAME}',
                submitUrl: '{$smarty.server.SCRIPT_NAME}',
                saveRedirect: '{$smarty.server.SCRIPT_NAME}',
                selectUserUrl: '{$smarty.server.SCRIPT_NAME}?uid=',
                filterUrl: '{$smarty.server.SCRIPT_NAME}?{QueryStringKeys::ACCOUNT_STATUS}=',
                actions: actions,
                manageReservationsUrl: '{$ManageReservationsUrl}',
                confirmDeactivateText: '{translate key=AdminUserConfirmDeactivateTitle}',
            };

            var userManagement = new UserManagement(userOptions);
            userManagement.init();

            {foreach from=$users item=user}
                var user = {
                    id: {$user->Id},
                    first: '{$user->First|escape:"javascript"}',
                    last: '{$user->Last|escape:"javascript"}',
                    isActive: '{$user->IsActive()}',
                    username: '{$user->Username|escape:"javascript"}',
                    email: '{$user->Email|escape:"javascript"}',
                    timezone: '{$user->Timezone}',
                    phone: '{$user->Phone|escape:"javascript"}',
                    organization: '{$user->Organization|escape:"javascript"}',
                    position: '{$user->Position|escape:"javascript"}',
                    reservationColor: '{$user->ReservationColor|escape:"javascript"}'
                };
                userManagement.addUser(user);
            {/foreach}


            setUpEditables();
        });
    </script>
</div>
{include file='globalfooter.tpl'}
