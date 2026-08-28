<div>
	<div id="updateUserResults" class="alert alert-danger no-show validationSummary">
		<ul>
			{async_validator id="emailformat" key="ValidEmailRequired"}
			{async_validator id="uniqueemail" key="UniqueEmailRequired"}
			{async_validator id="uniqueusername" key="UniqueUsernameRequired"}
			{async_validator id="updateAttributeValidator" key=""}
		</ul>
	</div>
	<div class="row gy-2">
		<div class="col-sm-12 col-md-6">

			<div class="form-group">
				<label class="fw-bold" for="username">{translate key="Username"}<i
						class="bi bi-asterisk text-danger align-top" style="font-size: 0.5rem;"></i></label>
				<input type="text" {formname key="USERNAME"} class="required form-control has-feedback" required
					id="username" value="{$User->Username()}" />
			</div>
		</div>

		<div class="col-sm-12 col-md-6">
			<div class="form-group">
				<label class="fw-bold" for="email">{translate key="Email"}<i
						class="bi bi-asterisk text-danger align-top" style="font-size: 0.5rem;"></i></label>
				<input type="text" {formname key="EMAIL"} class="required form-control has-feedback" required id="email"
					value="{$User->EmailAddress()}" />
			</div>
		</div>

		<div class="col-sm-12 col-md-6">
			<div class="form-group">
				<label class="fw-bold" for="fname">{translate key="FirstName"}<i
						class="bi bi-asterisk text-danger align-top" style="font-size: 0.5rem;"></i></label>
				<input type="text" {formname key="FIRST_NAME"} class="required form-control has-feedback" required
					id="fname" value="{$User->FirstName()}" />
			</div>
		</div>

		<div class="col-sm-12 col-md-6">
			<div class="form-group">
				<label class="fw-bold" for="lname">{translate key="LastName"}<i
						class="bi bi-asterisk text-danger align-top" style="font-size: 0.5rem;"></i></label>
				<input type="text" {formname key="LAST_NAME"} class="required form-control has-feedback" required
					id="lname" value="{$User->LastName()}" />
			</div>
		</div>

		<div class="col-sm-12 col-md-6">
			<div class="form-group">
				<label class="fw-bold">{translate key="AdminUserRole"}</label>
				<div>
					{if $IsUserAdmin}
						<span class="badge bg-primary">{translate key="AdminUserRoleAdmin"}</span>
					{else}
						<span class="text-muted">{translate key="AdminUserRoleUser"}</span>
					{/if}
				</div>
				<div class="form-text">{translate key="AdminUserRoleDerivedFromGroups"}</div>
			</div>
		</div>

		<div class="col-sm-12 col-md-6">
			<div class="form-group">
				<label class="fw-bold" for="timezone">{translate key="Timezone"}</label>
				<select {formname key='TIMEZONE'} id='timezone' class="form-select">
					{html_options values=$Timezones output=$TimezoneLabels selected="{$User->Timezone()}"}
				</select>
			</div>
		</div>

		<div class="col-sm-12 col-md-6">
			<div class="form-group">
				<label class="fw-bold" for="phone">{translate key="Phone"}</label>
				<input type="text" {formname key="PHONE"} class="form-control" id="phone"
					value="{$User->GetAttribute(UserAttribute::Phone)}" />
			</div>
		</div>

		<div class="col-sm-12 col-md-6">
			<div class="form-group">
				<label class="fw-bold" for="organization">{translate key="Organization"}</label>
				<input type="text" {formname key="ORGANIZATION"} class="form-control" id="organization"
					value="{$User->GetAttribute(UserAttribute::Organization)}" />
			</div>
		</div>

		<div class="col-sm-12 col-md-6">
			<div class="form-group">
				<label class="fw-bold" for="position">{translate key="Position"}</label>
				<input type="text" {formname key="POSITION"} class="form-control" id="position"
					value="{$User->GetAttribute(UserAttribute::Position)}" />
			</div>
		</div>

		<div class="col-sm-12 col-md-6">
			<div class="form-group">
				<label class="fw-bold" for="department">{translate key="Department"}</label>
				<input type="text" {formname key="DEPARTMENT"} class="form-control" id="department"
					value="{$User->GetAttribute(UserAttribute::Department)}" />
			</div>
		</div>

		<div class="col-sm-12 col-md-6">
			<div class="form-group">
				<label class="fw-bold" for="wecomUserId">{translate key="WeComUserId"}</label>
				<input type="text" {formname key="WECOM_USER_ID"} class="form-control" id="wecomUserId"
					value="{$WeComUserId}" />
				<div class="form-text">{translate key="WeComUserIdHint"}</div>
			</div>
		</div>

		{foreach from=$Attributes item=attribute}
			<div class="col-sm-12 col-md-6">
				{control type="AttributeControl" attribute=$attribute value={$User->GetAttributeValue($attribute->Id())} }
			</div>
		{/foreach}
	</div>

	{* 群組與權限 -- 直接在使用者這邊勾選「一般群組」,跟其他欄位共用同一顆「更新」送出
	   (見 FormKeys::USER_GROUP_IDS / ManageUsersPresenter::ApplyUserGroups)。預設群組與
	   會授予管理員角色的群組一律唯讀,只能在「群組管理」調整。 *}
	<div class="admin-drawer-section-title mt-4">{translate key="AdminUserGroupsSection"}</div>
	<div class="row gy-2">
		<div class="col-12">
			<label class="fw-bold d-block mb-1">{translate key="AdminUserGroupMembership"}</label>
			<div class="admin-user-group-list border rounded p-2">
				{foreach from=$Groups item=g}
					{assign var=gid value=$g->Id()}
					{assign var=gProtected value=$g->IsDefault() || $g->IsAdmin() || $g->IsExtendedAdmin()}
					<div class="form-check">
						<input class="form-check-input" type="checkbox"
							name="{FormKeys::USER_GROUP_IDS}[]" value="{$gid}" id="userGroup_{$gid}"
							{if $UserGroupIds[$gid]|default:false}checked{/if}
							{if $gProtected}disabled{/if} />
						<label class="form-check-label" for="userGroup_{$gid}">
							{$g->Name()|escape}
							{if $g->IsDefault()}
								<span class="badge bg-secondary ms-1">{translate key="AdminUserGroupDefaultBadge"}</span>
							{/if}
							{if $g->IsAdmin() || $g->IsExtendedAdmin()}
								<span class="badge bg-warning text-dark ms-1">{translate key="AdminUserGroupRoleBadge"}</span>
							{/if}
						</label>
					</div>
				{/foreach}
			</div>
			<div class="form-text">
				{translate key="AdminUserGroupProtectedHint"}
				<a href="{$ManageGroupsUrl}" class="link-primary">{translate key="GoToGroupManagement"}</a>
			</div>
		</div>
	</div>
</div>
