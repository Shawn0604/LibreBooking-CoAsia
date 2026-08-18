{include file='globalheader.tpl' InlineEdit=true DataTable=true}

<div id="page-manage-reservations" class="admin-page">
	{capture name=headerActions}
		<div class="dropdown">
			<button class="btn btn-primary btn-sm dropdown-toggle" type="button" id="moreReservationActions"
				data-bs-toggle="dropdown">
				<i class="bi bi-three-dots"></i>
				<span class="visually-hidden">Expand</span>
			</button>
			<ul class="dropdown-menu" role="menu" aria-labelledby="moreReservationActions">
				{if $CanViewAdmin}
					<li role="presentation">
						<a role="menuitem" href="#" id="import-reservations" class="add-link dropdown-item">
							<i class="bi bi-box-arrow-in-down"></i>
							{translate key=Import}
						</a>
					</li>
				{/if}
				<li role="presentation">
					<a role="menuitem" href="{$CsvExportUrl}" download="{$CsvExportUrl}" class="add-link dropdown-item"
						target="_blank">
						<i class="bi bi-box-arrow-in-up"></i>
						{translate key=Export} CVS
					</a>
				</li>
				{if $CanViewAdmin}
					<li role="separator" class="divider"></li>
					<li role="presentation">
						<a role="menuitem" href="#" id="addTermsOfService" class="add-link dropdown-item">
							<i class="bi bi-journals"></i>
							{translate key=TermsOfService}
						</a>
					</li>
					<li role="presentation">
						<a role="menuitem" href="manage_reservation_colors.php" id="addTermsOfService"
							class="add-link dropdown-item">
							<i class="bi bi-brush"></i>
							{translate key=ReservationColors}
						</a>
					</li>
				{/if}
			</ul>
		</div>
	{/capture}
	{include file='Admin/admin_page_shell.tpl' TitleKey='ManageReservations' DescriptionKey='AdminReservationsDescription' HeaderActions=$smarty.capture.headerActions}

	<div class="admin-page-toolbar">
		<div class="card shadow filterTable" id="filter-reservations-panel">
			<div class="card-body">
				<form id="filterForm" role="form" class="admin-toolbar-row">
					<div class="d-flex align-items-center gap-1">
						<label for="startDate" class="visually-hidden">{translate key='BeginDate'}</label>
						<input id="startDate" type="text" class="form-control form-control-sm"
							style="width:8.5em" placeholder="{translate key='BeginDate'}" />
						<span>&ndash;</span>
						<label for="endDate" class="visually-hidden">{translate key='EndDate'}</label>
						<input id="endDate" type="text" class="form-control form-control-sm"
							style="width:8.5em" placeholder="{translate key='EndDate'}" />
					</div>
					<label class="admin-search" for="userFilter">
						<i class="bi bi-search"></i>
						<input id="userFilter" type="text" value="{$UserNameFilter}" placeholder="{translate key=User}" />
					</label>
					<input id="userId" type="hidden" value="{$UserIdFilter}" />
					<select id="resourceId" class="form-select form-select-sm admin-toolbar-select" aria-label="{translate key=Resource}">
						<option value="">{translate key=AllResources}</option>
						{object_html_options options=$Resources key='GetId' label="GetName" selected=$ResourceId}
					</select>
					<select id="locationId" class="form-select form-select-sm admin-toolbar-select" aria-label="{translate key=Location}">
						<option value="">{translate key=AllLocations}</option>
						<option value="{ResourceLocation::TAIPEI}" {if $LocationFilter == ResourceLocation::TAIPEI}selected{/if}>{translate key=ResourceLocationTaipei}</option>
						<option value="{ResourceLocation::SHENZHEN}" {if $LocationFilter == ResourceLocation::SHENZHEN}selected{/if}>{translate key=ResourceLocationShenzhen}</option>
						<option value="{ResourceLocation::SHANGHAI}" {if $LocationFilter == ResourceLocation::SHANGHAI}selected{/if}>{translate key=ResourceLocationShanghai}</option>
						<option value="{ResourceLocation::CROSS_REGION}" {if $LocationFilter == ResourceLocation::CROSS_REGION}selected{/if}>{translate key=ResourceLocationCrossRegion}</option>
					</select>
					<select id="statusId" class="form-select form-select-sm admin-toolbar-select" aria-label="{translate key=Status}">
						<option value="">{translate key=AllReservations}</option>
						<option value="{ReservationStatus::Pending}"
							{if $ReservationStatusId eq ReservationStatus::Pending}selected="selected" {/if}>
							{translate key=PendingReservations}</option>
					</select>
					{filter_button id="filter"}
					{reset_button id="clearFilter"}

					{* Hidden rather than removed -- reservations.js's filterReservations() still reads
					   these ids unconditionally when building the query string, so keeping them (just
					   not surfaced by default) avoids having to make every .val() call defensive.
					   They keep whatever value the page loaded with (from a shared link, say) even
					   though there's no UI to change them anymore. *}
					<div class="d-none">
						<select id="scheduleId">
							<option value="">{translate key=AllSchedules}</option>
							{object_html_options options=$Schedules key='GetId' label="GetName" selected=$ScheduleId}
						</select>
						<input id="referenceNumber" type="text" value="{$ReferenceNumber}" />
						<input id="reservationTitle" type="text" value="{$ReservationTitle}" />
						<input id="reservationDescription" type="text" value="{$ReservationDescription}" />
						<select id="resourceStatusIdFilter">
							<option value="">{translate key=AllResourceStatuses}</option>
							<option value="{ResourceStatus::AVAILABLE}">{translate key=Available}</option>
							<option value="{ResourceStatus::UNAVAILABLE}">{translate key=Unavailable}</option>
							<option value="{ResourceStatus::HIDDEN}">{translate key=Hidden}</option>
						</select>
						<select id="resourceReasonIdFilter"></select>
						<input type="checkbox" id="missedCheckin" {if $MissedCheckin}checked="checked"{/if} />
						<input type="checkbox" id="missedCheckout" {if $MissedCheckout}checked="checked"{/if} />
					</div>
				</form>
			</div>
		</div>
	</div>

	<div class="card shadow admin-clean-table-card">
		<div class="table-responsive">
			{assign var=tableId value=reservationTable}
			<table class="table admin-clean-table w-100" id="{$tableId}">
				<thead>
					<tr>
						<th>{translate key=AdminReservationColumnTime}</th>
						<th>{translate key='Title'}</th>
						<th>{translate key='Resource'}</th>
						<th>{translate key='User'}</th>
						<th>{translate key=Status}</th>
						<th class="text-end">{translate key=Actions}</th>
					</tr>
				</thead>
				<tbody>
					{foreach from=$reservations item=reservation}
						{assign var=reservationId value=$reservation->ReservationId}
						<tr data-seriesId="{$reservation->SeriesId}"
							data-refnum="{$reservation->ReferenceNumber}">
							<td>
								{formatdate date=$reservation->StartDate timezone=$Timezone key=period_time} -
								{formatdate date=$reservation->EndDate timezone=$Timezone key=period_time}
								<div class="admin-cell-secondary">
									{formatdate date=$reservation->StartDate timezone=$Timezone key=general_date}</div>
							</td>
							<td>{if !empty($reservation->Title)}{$reservation->Title|escape:'html'}{else}{translate key='NoTitleLabel'}{/if}</td>
							<td class="resource">
								{if !empty($reservation->Location)}
									<span class="admin-cell-secondary">{$reservation->Location|escape:'html'} ・</span>
								{/if}
								{$reservation->ResourceName|escape:'html'}
								{if $reservation->ResourceStatusId != ResourceStatus::AVAILABLE}
									<i class="bi bi-exclamation-triangle-fill text-warning"
										title="{translate key=Unavailable}"></i>
								{/if}
							</td>
							<td>{fullname first=$reservation->FirstName|unescape:'html' last=$reservation->LastName|unescape:'html' ignorePrivacy=true}</td>
							<td>
								{if $reservation->RequiresApproval}
									<span class="admin-badge admin-badge-upcoming">{translate key=AdminReservationStatusPending}</span>
								{else}
									<span class="admin-badge admin-badge-inprogress">{translate key=AdminReservationStatusConfirmed}</span>
								{/if}
							</td>
							<td class="text-end">
								<div class="admin-row-actions">
									<button type="button" class="admin-row-edit-btn admin-reservation-edit">
										<i class="bi bi-pencil-square"></i>{translate key='Edit'}
									</button>
									<div class="dropdown">
										<button type="button" class="admin-row-menu-btn" data-bs-toggle="dropdown"
											aria-label="{translate key=Actions}">
											<i class="bi bi-three-dots"></i>
										</button>
										<ul class="dropdown-menu dropdown-menu-end" role="menu">
											{if $reservation->RequiresApproval}
												<li role="presentation"><a role="menuitem" href="#"
														class="dropdown-item admin-reservation-approve">{translate key='Approve'}</a>
												</li>
											{/if}
											<li role="presentation"><a role="menuitem" href="#"
													class="dropdown-item text-danger admin-reservation-cancel">{translate key=AdminReservationCancel}</a>
											</li>
										</ul>
									</div>
								</div>
							</td>
						</tr>
					{foreachelse}
						<tr>
							<td colspan="6" class="admin-empty">{translate key=AdminReservationNoResults}</td>
						</tr>
					{/foreach}
				</tbody>
			</table>
		</div>
		<div class="admin-pager" id="reservationPager"></div>
	</div>

	{* Kept for its existing AJAX wiring (ConfigureAsyncForm) -- no longer wrapped in a visible
	   modal, submitted programmatically from AdminConfirm's onConfirm instead (see
	   Web/scripts/admin/reservations.js showDeleteReservation()). *}
	<form id="deleteInstanceForm" method="post" class="d-none">
		<div class="delResResponse"></div>
		<input type="hidden" {formname key=SERIES_UPDATE_SCOPE} value="{SeriesUpdateScope::ThisInstance}" />
		<input type="hidden" {formname key=REFERENCE_NUMBER} value="" class="referenceNumber" />
	</form>

	<div class="modal fade" id="deleteSeriesDialog" tabindex="-1" role="dialog"
		aria-labelledby="deleteSeriesDialogLabel" aria-hidden="true">
		<div class="modal-dialog">
			<form id="deleteSeriesForm" method="post">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title" id="deleteSeriesDialogLabel">{translate key=Delete}</h4>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
					</div>
					<div class="modal-body">
						<div class="alert alert-warning">
							{translate key=DeleteWarning}
						</div>
						<input type="hidden" id="hdnSeriesUpdateScope" {formname key=SERIES_UPDATE_SCOPE} />
						<input type="hidden" {formname key=REFERENCE_NUMBER} value="" class="referenceNumber" />
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-outline-secondary cancel"
							data-bs-dismiss="modal">{translate key='Cancel'}</button>

						<button type="button" class="btn btn-danger saveSeries btnUpdateThisInstance"
							id="btnUpdateThisInstance">
							{translate key='ThisInstance'}
						</button>
						<button type="button" class="btn btn-danger saveSeries btnUpdateAllInstances"
							id="btnUpdateAllInstances">
							{translate key='AllInstances'}
						</button>
						<button type="button" class="btn btn-danger saveSeries btnUpdateFutureInstances"
							id="btnUpdateFutureInstances">
							{translate key='FutureInstances'}
						</button>
						{indicator}
					</div>
				</div>
			</form>
		</div>
	</div>

	<div id="deleteMultipleDialog" class="modal fade" tabindex="-1" role="dialog"
		aria-labelledby="deleteMultipleModalLabel" aria-hidden="true">
		<form id="deleteMultipleForm" method="post" ajaxAction="{ManageReservationsActions::DeleteMultiple}">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title" id="deleteMultipleModalLabel">{translate key=Delete} (<span
								id="deleteMultipleCount"></span>)</h4>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
					</div>
					<div class="modal-body">
						<div class="alert alert-warning">
							<div>{translate key=DeleteWarning}</div>

							<div>{translate key=DeleteMultipleReservationsWarning}</div>
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

	<div id="inlineUpdateErrorDialog" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="inlineErrorLabel"
		aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h4 class="modal-title" id="inlineErrorLabel">{translate key=Error}</h4>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
				</div>
				<div class="modal-body">
					<div id="inlineUpdateErrors" class="hidden error">&nbsp;</div>
					<div id="reservationAccessError" class="hidden error"></div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-outline-secondary cancel"
						data-bs-dismiss="modal">{translate key='OK'}</button>
				</div>
			</div>
		</div>
	</div>

	<div id="importReservationsDialog" class="modal" tabindex="-1" role="dialog"
		aria-labelledby="importReservationsModalLabel" aria-hidden="true">
		<form id="importReservationsForm" class="form" role="form" method="post" enctype="multipart/form-data"
			ajaxAction="{ManageReservationsActions::Import}">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title" id="importReservationsModalLabel">{translate key=Import}</h4>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
					</div>
					<div class="modal-body">
						<div id="importInstructions" class="alert alert-info">
							<div class="note">{translate key=ReservationImportInstructions}</div>
							<a href="{$smarty.server.SCRIPT_NAME}?dr=template" class="alert-link"
								download="{$smarty.server.SCRIPT_NAME}?dr=template"
								target="_blank">{translate key=GetTemplate}<span class="bi bi-download ms-1"></span></a>
						</div>
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
							<a class="alert-link" href="{$smarty.server.SCRIPT_NAME}">{translate key=Done}<i
									class="bi bi-arrow-repeat ms-1"></i></a>
						</div>
						<div class="">
							<label for="reservationImportFile" class="visually-hidden">{translate key=File}</label>
							<input type="file" {formname key=RESERVATION_IMPORT_FILE} id="reservationImportFile"
								class="form-control form-control-sm" accept=".csv" />
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

	<div class="modal fade" id="termsOfServiceDialog" tabindex="-1" role="dialog"
		aria-labelledby="termsOfServiceDialogLabel" aria-hidden="true">
		<div class="modal-dialog">
			<form id="termsOfServiceForm" method="post" ajaxAction="termsOfService" enctype="multipart/form-data">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="termsOfServiceDialogLabel">{translate key=TermsOfService}</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
					</div>
					<div class="modal-body">
						<div>
							<div class="form-check">
								<input type="radio" {formname key=TOS_METHOD} value="manual" id="tos_manual_radio"
									checked="checked" data-ref="tos_manual_div" class="toggle form-check-input">
								<label for="tos_manual_radio">{translate key=EnterTermsManually}</label>
							</div>
							<div class="form-check">
								<input type="radio" {formname key=TOS_METHOD} value="url" id="tos_url_radio"
									data-ref="tos_url_div" class="toggle form-check-input">
								<label for="tos_url_radio">{translate key=LinkToTerms}</label>
							</div>
							<div class="form-check">
								<input type="radio" {formname key=TOS_METHOD} value="upload" id="tos_upload_radio"
									data-ref="tos_upload_div" class="toggle form-check-input">
								<label for="tos_upload_radio">{translate key=UploadTerms}</label>
							</div>
						</div>
						<div id="tos_manual_div" class="tos-div">
							<div class="form-group">
								<label for="tos-manual" class="fw-bold">{translate key=TermsOfService}</label>
								<textarea id="tos-manual" class="form-control form-control-sm w-100" rows="10"
									{formname key=TOS_TEXT}></textarea>
							</div>
						</div>
						<div id="tos_url_div" class="tos-div d-none">
							<div class="form-group">
								<label for="tos-url" class="fw-bold">{translate key=LinkToTerms}</label>
								<input type="url" id="tos-url" class="form-control form-control-sm"
									placeholder="http://www.example.com/tos.html" {formname key=TOS_URL}
									maxlength="255" />
							</div>
						</div>
						<div id="tos_upload_div" class="tos-div d-none margin-bottom-15">
							<label for="tos-upload" class="fw-bold">{translate key=TermsOfService} PDF</label>
							<div class="dropzone text-center border border-2 rounded-3 bg-light"
								id="termsOfServiceUpload">
								<div class="">
									<i class="bi bi-filetype-pdf fs-1"></i><br />
									{translate key=ChooseOrDropFile}
								</div>
								<input id="tos-upload" type="file" {formname key=TOS_UPLOAD} accept="application/pdf" />
							</div>
							<div id="tos-upload-link" class="d-none">
								<a href="{$ScriptUrl}/uploads/tos/tos.pdf" target="_blank" class="link-primary"><span
										class="bi bi-filetype-pdf me-1"></span> {translate key=ViewTerms}
								</a>
							</div>
						</div>
						<div class="mt-3">
							<div>{translate key=RequireTermsOfServiceAcknowledgement}</div>
							<div class="form-check">
								<input type="radio" {formname key=TOS_APPLICABILITY} class="form-check-input"
									value="{TermsOfService::RESERVATION}" id="tos_reservation" checked="checked">
								<label for="tos_reservation"
									class="form-check-label">{translate key=UponReservation}</label>
							</div>
							<div class="form-check">
								<input type="radio" {formname key=TOS_APPLICABILITY} class="form-check-input"
									value="{TermsOfService::REGISTRATION}" id="tos_registration">
								<label for="tos_registration"
									class="form-check-label">{translate key=UponRegistration}</label>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						{cancel_button}
						{delete_button id='deleteTerms' class='d-none'}
						{update_button submit=true}
						{indicator}
					</div>
				</div>
			</form>
		</div>
	</div>

	{include file="javascript-includes.tpl" InlineEdit=true}
	{jsfile src="ajax-helpers.js"}
	{jsfile src="admin/admin-confirm.js"}
	{jsfile src="admin/admin-table-paginate.js"}
	{jsfile src="admin/reservations.js"}
	{jsfile src="search-clear.js"}
	{jsfile src="autocomplete.js"}
	{jsfile src="approval.js"}
	{vendor_js src="dropzone/1.0.0/js/dropzone.js"}

	<script type="text/javascript">
		/*function hidePopoversWhenClickAway() {
			$('body').on('click', function(e) {
				$('[rel="popover"]').each(function() {
					if (!$(this).is(e.target) && $(this).has(e.target).length === 0 && $('.popover').has(e
							.target).length === 0) {
						$(this).popover('hide');
					}
				});
			});
		}*/

		function setUpPopovers() {
			var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
			var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
				return new bootstrap.Tooltip(tooltipTriggerEl);
			});
		}

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
				url: updateUrl + '{ManageReservationsActions::UpdateAttribute}', emptytext: '-'
			});
		}

		$(document).ready(function() {

			setUpPopovers();
			//hidePopoversWhenClickAway();
			setUpEditables();
			dropzone($("#termsOfServiceUpload"));

			var updateScope = {};
			updateScope['btnUpdateThisInstance'] = '{SeriesUpdateScope::ThisInstance}';
			updateScope['btnUpdateAllInstances'] = '{SeriesUpdateScope::FullSeries}';
			updateScope['btnUpdateFutureInstances'] = '{SeriesUpdateScope::FutureInstances}';

			var actions = {};

			var resOpts = {
				autocompleteUrl: "{$Path}ajax/autocomplete.php?type={AutoCompleteType::User}",
				reservationUrlTemplate: "{$Path}reservation.php?{QueryStringKeys::REFERENCE_NUMBER}=[refnum]",
				popupUrl: "{$Path}ajax/respopup.php",
				updateScope: updateScope,
				actions: actions,
				deleteUrl: '{$Path}ajax/reservation_delete.php?{QueryStringKeys::RESPONSE_TYPE}=json',
				resourceStatusUrl: '{$smarty.server.SCRIPT_NAME}?{QueryStringKeys::ACTION}=changeStatus',
				submitUrl: '{$smarty.server.SCRIPT_NAME}',
				termsOfServiceUrl: '{$smarty.server.SCRIPT_NAME}?dr=tos',
				updateTermsOfServiceAction: 'termsOfService',
				deleteTermsOfServiceAction: 'deleteTerms',
				confirmCancelTitle: '{translate key=AdminReservationConfirmCancelTitle|escape:javascript}',
				confirmCancelMessage: '{translate key=AdminReservationConfirmCancelMessage|escape:javascript}',
				confirmCancelButton: '{translate key=AdminReservationCancel|escape:javascript}'
			};

			var approvalOpts = {
				url: '{$Path}ajax/reservation_approve.php'
			};

			var approval = new Approval(approvalOpts);

			var reservationManagement = new ReservationManagement(resOpts, approval);
			reservationManagement.init();

			{foreach from=$reservations item=reservation}
				reservationManagement.addReservation({
					id: '{$reservation->ReservationId}',
					referenceNumber: '{$reservation->ReferenceNumber}',
					isRecurring: '{$reservation->IsRecurring}',
					resourceStatusId: '{$reservation->ResourceStatusId}',
					resourceStatusReasonId: '{$reservation->ResourceStatusReasonId}',
					resourceId: '{$reservation->ResourceId}',
					requiresApproval: '{$reservation->RequiresApproval}'
				});
			{/foreach}

			{foreach from=$StatusReasons item=reason}
				reservationManagement.addStatusReason('{$reason->Id()}', '{$reason->StatusId()}', '{$reason->Description()|escape:javascript}');
			{/foreach}

			reservationManagement.initializeStatusFilter('{$ResourceStatusFilterId}', '{$ResourceStatusReasonFilterId}');

			AdminTablePaginate({ tableId: 'reservationTable', containerId: 'reservationPager', pageSize: 20 });
		});
	</script>

	{control type="DatePickerSetupControl" ControlId="startDate" DefaultDate=$StartDate}
	{control type="DatePickerSetupControl" ControlId="endDate" DefaultDate=$EndDate}

	{csrf_token}

	<div class="modal fade" id="approveDiv" tabindex="-1" role="dialog" aria-labelledby="approveDivLabel"
		data-bs-backdrop="static" aria-hidden="true">
		{include file="wait-box.tpl" translateKey='Approving'}
	</div>

</div>
{include file='globalfooter.tpl'}
