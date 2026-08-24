{* Extracted verbatim from the old per-resource accordion body -- every field/x-editable widget/
   sub-dialog trigger here is unchanged, only the OUTER wrapper changed (was an accordion-collapse
   item, now an offcanvas drawer body, see manage_resources.tpl). $resource and $id must already
   be assigned by the caller before including this. *}

{* Physical rooms (ResourceType != 線上會議) only need a Name to edit; video-conference
   resources (ResourceType::IsVideoConference()) get a dedicated "info sent to the booker"
   section instead of the full generic field set below. Resources with no ResourceType
   assigned yet fall back to the full legacy field set, so a brand-new resource stays
   configurable before an admin has picked its type. See Domain/ResourceType.php and
   database_schema/upgrades/4.4/schema.sql. *}
{assign var=isTypedResource value=$resource->HasResourceType()}
{assign var=isVideoConferenceResource value=false}
{if $isTypedResource}
	{assign var=isVideoConferenceResource value=$ResourceTypes[$resource->GetResourceTypeId()]->IsVideoConference()}
{/if}

<input type="hidden" class="id" value="{$id}" />
<div class="row">
	{if !$isTypedResource}
	<div class="col-12 col-sm-2 p-2">
		<div class="resourceImage">
			{if $resource->HasImage()}
				<div id="resourceImageCarousel{$id}" class="carousel slide">
					<div
						class="carousel-inner h-100 ratio ratio-1x1 border rounded-3 shadow-sm">
						<div class="carousel-item active">
							<img src="{resource_image image=$resource->GetImage()}"
								alt="{$resource->GetName()}"
								class="rounded d-block w-100 h-100 object-fit-cover" />
						</div>
						{foreach from=$resource->GetImages() item=image}
							<div class="carousel-item">
								<img src="{resource_image image=$image}"
									alt="{$resource->GetName()}"
									class="rounded d-block w-100 h-100 object-fit-cover" />
							</div>
						{/foreach}
					</div>
					<div class="carousel-indicators mb-0">
						{if $resource->GetImages()|count > 0}
							{assign var=slide value=1}
							<button type="button"
								data-bs-target="#resourceImageCarousel{$id}"
								data-bs-slide-to="0" class="active"></button>
							{foreach from=$resource->GetImages() item=image}
								<button type="button"
									data-bs-target="#resourceImageCarousel{$id}"
									data-bs-slide-to="{$slide}"></button>
								{assign var=slide value=$slide+1}
							{/foreach}
						{/if}

					</div>
				</div>
			{else}
				<div class="noImageContainer text-center">
					<div
						class="noImage d-flex align-items-center justify-content-center w-100 bg-light border rounded-3 mx-auto">
						<span class="bi bi-image fs-1"></span>
					</div>
				</div>
			{/if}
			<div class="edit-image d-inline-block rounded p-2 bg-body"
				style="position: relative; top: -36px; left:1px; width: 35px; height: 35px;">
				<a href="#" class="update imageButton link-primary "
					title="{translate key='Change'}"><span
						class="bi bi-pencil-square"></span></a>
			</div>
		</div>
		<div class="text-center">
			<div>{translate key=ResourceColor}</div>
			<input type="color"
				class="resourceColorPicker w-50 mx-auto border form-control form-control-color"
				value='{if $resource->HasColor()}{$resource->GetColor()}{else}#ffffff{/if}'
				alt="{translate key=ResourceColor}"
				title="{translate key=ResourceColor}" />
			<div>
				<a href="#" class="update clearColor link-danger"
					title="{translate key=Remove}"><i
						class="bi bi-trash3-fill icon delete"></i></a>
			</div>
		</div>
	</div>
	{/if}
	<div class="col-12 {if !$isTypedResource}col-sm-10{/if}">
		<div class="row row-cols-auto row-cols-sm-3 ">
			<div class="col d-block">
				<div>
					<div class="resourceNameField title fs-5 fw-bold me-1"
						data-type="text" data-pk="{$id}"
						data-name="{FormKeys::RESOURCE_NAME}">
						{$resource->GetName()}</div>
					<div>
						<a class="update renameButton link-primary me-1"
							href="#" title="{translate key='Rename'}">
							<span
								class="visually-hidden">{translate key=Rename}</span>
							<i class="bi bi-pencil-square me-1"></i></a>
						<div class="vr me-1"></div>
						<a class="update copyButton link-primary me-1" href="#"
							title="{translate key='Copy'}">
							<span
								class="visually-hidden">{translate key=Copy}</span>
							<i class="bi bi-copy"></i></a>
						<div class="vr me-1"></div>
						<a class="update deleteButton link-danger me-1" href="#"
							title="{translate key='Delete'}">
							<span
								class="visually-hidden">{translate key=Delete}</span>
							<i class="bi bi-trash3-fill icon delete"></i>
						</a>
					</div>
				</div>
				<div>
					<label class="inline fw-bold">ResourceId:</label>
					<span>{$id}</span>
				</div>
				<div>
					<label
						class="inline fw-bold">{translate key='Status'}</label>
					{if $resource->IsAvailable()}
						<a class="update changeStatus link-primary" href="#"
							data-popover-content="#statusDialog">{translate key='Available'}</a>
						<i class="bi bi-check-circle-fill text-success"></i>
					{elseif $resource->IsUnavailable()}
						<a class="update changeStatus link-primary" href="#"
							data-popover-content="#statusDialog">{translate key='Unavailable'}</a>
						<i class="bi bi-exclamation-circle-fill text-warning"></i>
					{else}
						<a class="update changeStatus link-primary" href="#"
							data-popover-content="#statusDialog">{translate key='Hidden'}</a>
						<i class="bi bi-x-circle-fill text-danger"></i>
					{/if}
					{if array_key_exists($resource->GetStatusReasonId(),$StatusReasons)}
						<span
							class="statusReason">{$StatusReasons[$resource->GetStatusReasonId()]->Description()}</span>
					{/if}
				</div>
				{if !$isTypedResource}
				<div>
					<label
						class="inline fw-bold">{translate key='Schedule'}</label>
					<span class="propertyValue scheduleName" data-type="select"
						data-pk="{$id}"
						data-value="{$resource->GetScheduleId()}"
						data-name="{FormKeys::SCHEDULE_ID}">{$Schedules[$resource->GetScheduleId()]}</span>
					<a class="update changeScheduleButton link-primary"
						title="{translate key='Edit'}" href="#"><span
							class="bi bi-pencil-square"></span></a>
				</div>
				<div>
					<label
						class="inline fw-bold">{translate key='ResourceType'}</label>
					<span class="propertyValue resourceTypeName"
						data-type="select" data-pk="{$id}"
						data-value="{$resource->GetResourceTypeId()}"
						data-name="{FormKeys::RESOURCE_TYPE_ID}">
						{if $resource->HasResourceType()}
							{$ResourceTypes[$resource->GetResourceTypeId()]->Name()}
						{else}
							{translate key='NoResourceTypeLabel'}
						{/if}
					</span>
					<a class="update changeResourceType link-primary"
						title="{translate key='Edit'}" href="#">
						<span
							class="visually-hidden">{translate key=ResourceType}</span>
						<span class="bi bi-pencil-square"></span>
					</a>
				</div>
				<div>
					<label
						class="inline fw-bold">{translate key=SortOrder}</label>
					<span class="propertyValue sortOrderValue"
						data-type="number" data-pk="{$id}"
						data-name="{FormKeys::RESOURCE_SORT_ORDER}">
						{$resource->GetSortOrder()|default:"0"}
					</span>
					<a class="update changeSortOrder link-primary"
						title="{translate key='Edit'}" href="#">
						<span
							class="visually-hidden">{translate key=SortOrder}</span>
						<span class="bi bi-pencil-square"></span>
					</a>
				</div>
				<div class="mt-2">
					<label
						class="inline fw-bold">{translate key='Location'}</label>
					<span class="propertyValue locationValue" data-type="select"
						data-pk="{$id}" data-value="{$resource->GetLocation()}"
						data-name="{FormKeys::RESOURCE_LOCATION}">
						{if $resource->HasLocation()}
							{$resource->GetLocation()}
						{else}
							{translate key='NoLocationLabel'}
						{/if}
					</span>
					<a class="update changeLocation link-primary"
						title="{translate key='Edit'}" href="#">
						<span
							class="visually-hidden">{translate key=Location}</span>
						<span class="bi bi-pencil-square"></span>
					</a>
				</div>
				<div>
					<label
						class="inline fw-bold">{translate key='Contact'}</label>
					{if $ResourceContactIsUser}
						<span class="propertyValue contactValue" data-type="select"
						{else} <span class="propertyValue contactValue"
						data-type="text" {/if} data-pk="{$id}"
						data-value="{$resource->GetContact()}"
						data-name="{FormKeys::RESOURCE_CONTACT}">
						{if $resource->HasContact()}
							{$resource->GetContact()}
						{else}
							{translate key='NoContactLabel'}
						{/if}
					</span>
					<a class="update changeContact link-primary"
						title="{translate key='Edit'}" href="#">
						<span
							class="visually-hidden">{translate key=Contact}</span>
						<span class="bi bi-pencil-square"></span></a>
				</div>
				<div>
					<label
						class="inline fw-bold">{translate key='Description'}</label>
					<a class="update changeDescription link-primary"
						title="{translate key='Edit'}" href="#">
						<span
							class="visually-hidden">{translate key=Description}</span>
						<span class="bi bi-pencil-square"></span></a>
					{if $resource->HasDescription()}
						{assign var=description value=$resource->GetDescription()}
					{else}
						{assign var=description value=''}
					{/if}
					{strip}
						<div class="descriptionValue" data-type="trumbowyg"
							data-pk="{$id}" data-value="{$description}"
							data-name="{FormKeys::RESOURCE_DESCRIPTION}">
							{if $resource->HasDescription()}
								{$description|sanitize_rich_text}
							{else}
								{translate key='NoDescriptionLabel'}
							{/if}
						</div>
					{/strip}
				</div>
				<div>
					<label
						class="inline fw-bold">{translate key='Notes'}</label>
					<a class="update changeNotes link-primary"
						title="{translate key='Edit'}" href="#">
						<span
							class="visually-hidden">{translate key=Notes}</span>
						<span class="bi bi-pencil-square"></span>
					</a>
					{if $resource->HasNotes()}
						{assign var=notes value=$resource->GetNotes()}
					{else}
						{assign var=notes value=''}
					{/if}
					{strip}
						<div class="notesValue" data-type="trumbowyg"
							data-pk="{$id}" data-value="{$notes}"
							data-name="{FormKeys::RESOURCE_NOTES}">
							{if $resource->HasNotes()}
								{$notes|sanitize_rich_text}
							{else}
								{translate key='NoNotesLabel'}
							{/if}
						</div>
					{/strip}
				</div>
				{/if}
			</div>
		</div>
		{if !$isTypedResource}
		<details class="admin-drawer-advanced">
			<summary class="admin-drawer-advanced-summary">{translate key=AdminResourceAdvancedSettings}</summary>
			<div class="row row-cols-auto row-cols-sm-2 mt-2">
			<div class="col">
				<div class="">
					<span class="fs-6 fw-bold">{translate key=Access}</span>
					<a class="inline update changeAccess link-primary"
						title="{translate key='Edit'}" href="#">
						<span
							class="visually-hidden">{translate key=Access}</span>
						<span class="bi bi-pencil-square"></span>
					</a>

					<div class="accessPlaceHolder">
						{include file="Admin/Resources/manage_resources_access.tpl" resource=$resource}
					</div>
				</div>
				<div class="mt-2">
					<span
						class="fs-6 fw-bold">{translate key='ResourceAdministrator'}</span>
					{if $AdminGroups|default:array()|count > 0}
						<a class="update changeResourceAdmin link-primary"
							title="{translate key='Edit'}" href="#">
							<span
								class="visually-hidden">{translate key=ResourceAdministrator}</span>
							<span class="bi bi-pencil-square"></span></a>
					{/if}
					<div>
						<span class="propertyValue resourceAdminValue"
							data-type="select" data-pk="{$id}"
							data-value="{$resource->GetAdminGroupId()}"
							 data-name="{FormKeys::RESOURCE_ADMIN_GROUP_ID}">
								{assign var=adminGroupId value=$resource->GetAdminGroupId()}
								{if $adminGroupId !== null && isset($GroupLookup[$adminGroupId])}
									{$GroupLookup[$adminGroupId]->Name|escape:'html'}
								{else}
									{translate key='None'}
								{/if}
							</span>
					</div>
				</div>
				<div class="mt-2">
					<span
						class="fs-6 fw-bold">{translate key='Permissions'}</span>
					<div>
						<a href="#"
							class="update changeUserPermission link-primary">{translate key=Users}</a>
						<div class="vr mx-1"></div>
						<a href="#"
							class="update changeGroupPermissions link-primary">{translate key=Groups}</a>
					</div>
				</div>
			</div>
			<div class="col">
				<div class="">
					<span class="fs-6 fw-bold">{translate key=Duration}</span>
					<a class="inline update changeDuration link-primary"
						title="{translate key='Edit'}" href="#">
						<span
							class="visually-hidden">{translate key=Duration}</span>
						<span class="bi bi-pencil-square"></span>
					</a>

					<div class="durationPlaceHolder">
						{include file="Admin/Resources/manage_resources_duration.tpl" resource=$resource}
					</div>
				</div>

				{if $CreditsEnabled}
					<div class="mt-2">
						<span class="fs-6 fw-bold">{translate key='Credits'}</span>
						<a class="inline update changeCredits link-primary"
							title="{translate key='Edit'}" href="#">
							<span
								class="visually-hidden">{translate key=Credits}</span>
							<span class="bi bi-pencil-square"></span>
						</a>
						<div class="creditsPlaceHolder">
							{include file="Admin/Resources/manage_resources_credits.tpl" resource=$resource}
						</div>
					</div>
				{/if}

				<div class="mt-2">
					<span class="fs-6 fw-bold">{translate key='Capacity'}</span>
					<a class="link-primary update changeCapacity"
						title="{translate key='Edit'}" href="#">
						<span
							class="visually-hidden">{translate key=Capacity}</span>
						<span class="bi bi-pencil-square"></span>
					</a>
					<div class="capacityPlaceHolder">
						{include file="Admin/Resources/manage_resources_capacity.tpl" resource=$resource}
					</div>
				</div>
				<div class="mt-2">
					<span
						class="fs-6 fw-bold">{translate key='ResourceGroups'}</span>
					<a class="link-primary update changeResourceGroups"
						title="{translate key='Edit'}" href="#">
						<span
							class="visually-hidden">{translate key=ResourceGroups}</span>
						<span class="bi bi-pencil-square"></span>
					</a>

					<div class="resourceGroupsPlaceHolder">
						{include file="Admin/Resources/manage_resources_groups.tpl" resource=$resource}
					</div>
				</div>
			</div>
		</div>
		</details>
		<hr class="hr" />
		{if $AttributeList|default:array()|count > 0}
			{assign var="hasResults" value=false}
			{foreach from=$AttributeList item=attribute name=attrLoop}
				{if $attribute->AppliesToEntity($id)}
					{if !$hasResults}
						{assign var="hasResults" value=true}
						{* Content at the start of the iteration *}
						<div class="mb-4">
							<span class="fs-6 fw-bold">{translate key='CustomAttributes'}</span>
							<a id="customAttributesControl" class="link-primary" href="#"
								data-bs-toggle="collapse"
								data-bs-target="#customAttributes{$id}">
								<i id="customAttributesIcon{$id}"
									class="bi bi-chevron-down"></i>
							</a>
							<div id="customAttributes{$id}" class="collapse show">
								<div>
								{/if}
								{include file='Admin/InlineAttributeEdit.tpl' url="{$smarty.server.SCRIPT_NAME}?action={ManageResourcesActions::ActionChangeAttribute}"
								id=$id attribute=$attribute
								value=$resource->GetAttributeValue($attribute->Id())}
							{/if}
						{/foreach}
						{if $hasResults}
							{* Content at the end of the iteration *}
						</div>
					</div>
				</div>
			{/if}
		{/if}
		<div class="">
			<div class="fs-6 fw-bold me-2">{translate key='Public'}</div>
			<div class="publicSettingsPlaceHolder">
				{include file="Admin/Resources/manage_resources_public.tpl" resource=$resource}
			</div>
			<div class="">
				<a href="{$smarty.server.SCRIPT_NAME}?action={ManageResourcesActions::ActionPrintQR}&rid={$id}"
					target="_blank" class="link-primary">{translate key=PrintQRCode}
					<i class="bi bi-qr-code"></i></a>
			</div>
		</div>
		{elseif $isVideoConferenceResource}
			<hr class="hr" />
			<div class="mb-4">
				<span class="fs-6 fw-bold">{translate key=AdminVideoConferenceInfo}</span>
				<a class="update changeVideoConferenceInfo link-primary" title="{translate key='Edit'}" href="#">
					<span class="visually-hidden">{translate key=AdminVideoConferenceInfo}</span>
					<span class="bi bi-pencil-square"></span>
				</a>
				{assign var=hostCredentials value=$VideoHostCredentials[$id]|default:null}
				<div class="videoConferenceInfoPlaceHolder">
					{include file="Admin/Resources/manage_resources_video_info.tpl" resource=$resource id=$id attributeList=$AttributeList|default:array() hostCredentials=$hostCredentials}
				</div>
			</div>
		{/if}
	</div>
</div>
