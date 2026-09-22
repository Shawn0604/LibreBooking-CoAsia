{block name="header"}
    {include file='globalheader.tpl' cssFiles='css/schedule.css,css/reservation-page.css' printCssFiles='css/reservation.print.css'}
{/block}

{function name="displayResource"}
    <div class="resourceName rounded-1 m-1 p-1 {if !$resource->GetColor()}text-success bg-success bg-opacity-10" {else}"
    style="background-color:{$resource->GetColor()};color:{$resource->GetTextColor()}" {/if}>
    <span class="resourceDetails" data-resourceId="{$resource->GetId()}">{$resource->Name}</span>
    {if $resource->GetRequiresApproval()}<span class="bi bi-lock-fill" data-bs-toggle="tooltip"
        data-bs-title="approval"></span>{/if}
    {if $resource->IsCheckInEnabled()}<i class="bi bi-box-arrow-in-right" data-bs-toggle="tooltip"
        data-bs-title="checkin"></i>{/if}
    {if $resource->IsAutoReleased()}<i class="bi bi-clock-history" data-bs-toggle="tooltip" data-bs-title="autorelease"
        data-autorelease="{$resource->GetAutoReleaseMinutes()}"></i>{/if}
</div>
{/function}

{* Google-Calendar-style full page: same icon-row visual language as the Overview page's
   Quick Create modal (see css/reservation-page.css) instead of the old Bootstrap
   card/form-group layout — "Quick Create expanded into a full page". Every field below
   keeps its original id/name/{formname} exactly as before; only the surrounding markup and
   classes changed, so reservation.js/recurrence.js/the reminder script all still bind to the
   same elements they always did. *}
<div id="page-reservation" class="rp-page">
    <div class="rp-card">
        <form id="form-reservation" method="post" enctype="multipart/form-data" role="form">

            <div class="rp-topbar">
                <button type="button" class="rp-back-btn" onclick="window.location='{$ReturnUrl}'"
                    aria-label="{translate key='Cancel'}">
                    <i class="bi bi-arrow-left"></i>
                </button>
                <div class="rp-topbar-heading">
                    <h1 class="rp-heading-title">{block name=reservationHeader}{translate key="CreateReservationHeading"}{/block}
                    </h1>
                    <p class="rp-heading-subtitle">{if !empty($ReferenceNumber)}{translate key="EditFullReservationSubtitle"}{else}{translate key="CreateFullReservationSubtitle"}{/if}</p>
                    {if !empty($ReferenceNumber)}
                    <div class="rp-reference-number">
                        <span>{translate key=ReferenceNumber}</span> {$ReferenceNumber}
                    </div>
                    {/if}
                </div>
                <div class="rp-topbar-actions">
                    <button type="button" class="rp-cancel-link" onclick="window.location='{$ReturnUrl}'">
                        {translate key='Cancel'}
                    </button>
                    {block name="submitButtons"}
                    <button type="button" class="rp-primary-btn save create btnCreate">
                        {translate key='Create'}
                    </button>
                    {/block}
                </div>
            </div>

            <div class="rp-body">
                <div class="rp-row rp-row-title">
                    <i class="bi bi-card-text rp-row-icon"></i>
                    {* When $TitleRequired (CoAsia: reservation.title.required = true) the field is
                       genuinely required — the submit handler below blocks an empty title instead
                       of silently saving it as "（無標題）". When not required, the blank-to-
                       "(Untitled)" fallback still applies. *}
                    {textbox name="RESERVATION_TITLE" class="rp-title-input" value="ReservationTitle" id="reservationTitle" maxlength="300" placeholderkey='AddMeetingTitle' required=$TitleRequired}
                </div>

                <div class="rp-row rp-row-datetime">
                    <i class="bi bi-clock rp-row-icon"></i>
                    <div class="rp-datetime-fields">
                        {if $LockPeriods}
                        <input type="text" id="BeginDate" class="rp-date-input no-show" {formname key=BEGIN_DATE} />
                        <select id="BeginPeriod" {formname key=BEGIN_PERIOD}
                            class="rp-time-select timeinput no-show" title="Begin time">
                            {foreach from=$StartPeriods item=period}
                            {if $period->IsReservable()}
                            {assign var='selected' value=''}
                            {if $period eq $SelectedStart}
                            {assign var='selected' value=' selected="selected"'}
                            {assign var='startPeriod' value=$period}
                            {/if}
                            <option value="{$period->Begin()}" {$selected}>{$period->Label()}</option>
                            {/if}
                            {/foreach}
                        </select>
                        <span class="rp-time-dash">—</span>
                        <select id="EndPeriod" {formname key=END_PERIOD}
                            class="rp-time-select timeinput no-show" title="End time">
                            {foreach from=$EndPeriods item=period name=endPeriods}
                            {if $period->IsReservable()}
                            {assign var='selected' value=''}
                            {if $period eq $SelectedEnd}
                            {assign var='selected' value=' selected="selected"'}
                            {assign var='endPeriod' value=$period}
                            {/if}
                            <option value="{$period->End()}" {$selected}>{$period->LabelEnd()}</option>
                            {/if}
                            {/foreach}
                        </select>
                        <span class="rp-locked-period">{formatdate date=$StartDate} {$startPeriod->Label()}</span>
                        <input type="text" id="EndDate" class="d-none" {formname key=END_DATE} />
                        {else}
                        {* Same date+time picker Quick Create uses (Overview page) — shared
                           factory/formatters live in Web/scripts/reservation-datetime-picker.js,
                           loaded by both pages so this is the exact same 30-minute-grid widget,
                           not a lookalike. CSS is copied into reservation-page.css under
                           #page-reservation (same pattern as the resource picker below), since
                           this page doesn't load overview_fullcalendar.css. The four fields this
                           form has always submitted keep their original id/{formname} (so
                           Web/scripts/recurrence.js and reservation.js need no changes) but are
                           now JS-written hidden inputs instead of user-facing controls. *}
                        <div class="qc-time-edit">
                            <div class="qc-date-field">
                                <input type="date" class="qc-date-input" id="rpStartDate">
                                <span class="qc-date-display" id="rpStartDateDisplay"></span>
                            </div>
                            <div class="qc-time-picker" id="rpStartPicker">
                                <button type="button" class="qc-time-pill" id="rpStartPill" aria-haspopup="listbox" aria-expanded="false"></button>
                                <div class="qc-time-dropdown" id="rpStartDropdown" role="listbox"></div>
                            </div>
                            <span class="qc-time-dash">－</span>
                            <div class="qc-time-picker" id="rpEndPicker">
                                <button type="button" class="qc-time-pill" id="rpEndPill" aria-haspopup="listbox" aria-expanded="false"></button>
                                <div class="qc-time-dropdown" id="rpEndDropdown" role="listbox"></div>
                            </div>
                        </div>
                        <div class="qc-time-error d-none" id="rpTimeError"></div>
                        <input type="text" id="BeginDate" class="d-none" {formname key=BEGIN_DATE} value="{formatdate date=$StartDate key=system}" />
                        <input type="text" id="BeginPeriod" class="d-none" {formname key=BEGIN_PERIOD} value="{if $StartDate}{formatdate date=$StartDate format='H:i:s'}{else}09:00:00{/if}" />
                        <input type="text" id="EndPeriod" class="d-none" {formname key=END_PERIOD} value="{if $EndDate}{formatdate date=$EndDate format='H:i:s'}{else}09:30:00{/if}" />
                        <input type="text" id="EndDate" class="d-none" {formname key=END_DATE} value="{formatdate date=$StartDate key=system}" />
                        {/if}
                    </div>
                </div>

                {if !$HideRecurrence}
                <div class="rp-row rp-row-recurrence">
                    <i class="bi bi-arrow-repeat rp-row-icon"></i>
                    <div class="rp-recurrence-wrap">{$HideRecurrence}
                        {* Two-stage picker in front of RecurrenceControl's own #repeatOptions
                           (hidden — see .rp-recurrence-wrap .repeat-div .form-group >
                           .d-flex:first-child in reservation-page.css): a plain 不重複/重複 gate
                           by itself by default, with the actual frequency dropdown only
                           appearing once "重複" is picked. #repeatOptions stays the single
                           source of truth recurrence.js/the backend read — these two just drive
                           its value and .trigger('change') so ChangeRepeatOptions()'s existing
                           show/hide/clear logic runs unmodified either way. *}
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-2" id="rpRepeatGateRow">
                            <select id="rpRepeatGate" class="form-select form-select-sm repeat-drop w-auto">
                                <option value="none">{translate key=DoesNotRepeat}</option>
                                <option value="repeat">{translate key=RepeatPrompt}</option>
                            </select>
                            <select id="rpRepeatFrequency" class="form-select form-select-sm repeat-drop w-auto d-none">
                                <option value="daily">{translate key=Daily}</option>
                                <option value="weekly">{translate key=Weekly}</option>
                                <option value="monthly">{translate key=Monthly}</option>
                                <option value="custom">{translate key=Custom}</option>
                            </select>
                        </div>
                        {control type="RecurrenceControl" RepeatTerminationDate=$RepeatTerminationDate}
                    </div>
                </div>
                {/if}

                <div class="reservationResources rp-row rp-row-resource" id="reservation-resources">
                    <i class="bi bi-building rp-row-icon"></i>
                    <div class="rp-resource-wrap">
                        <input type="hidden" id="scheduleId" {formname key=SCHEDULE_ID} value="{$ScheduleId}" />
                        {* #primaryResourceContainer itself (not just the .resourceId input inside
                           it) is kept — Web/scripts/reservation.js's AddResources() (the resource-
                           groups dialog's confirm handler) does
                           $('#primaryResourceContainer').find('.resourceId') / .find('.resourceName')
                           and can inject a replacement .resourceName div into it when the first
                           checked group item becomes the new primary resource; removing the
                           wrapper entirely would silently break that hidden-value update too, not
                           just the (rare) visual swap. *}
                        <div id="primaryResourceContainer">
                            <input class="resourceId" type="hidden" id="primaryResourceId" {formname key=RESOURCE_ID}
                                value="{$ResourceId}" />
                        </div>

                        {* Searchable/switchable resource picker — same component pattern as
                           Quick Create's own #qcResourcePicker (open/close/search/select logic
                           in this template's own script below; CSS is copied into
                           reservation-page.css under #page-reservation since this page doesn't
                           load overview_fullcalendar.css). Switching resource re-runs the
                           availability check (#rpAvailability) below, same as changing the
                           date/time does. Lists $SwitchableResources (every resource the user
                           can access, ReservationComponentBinder::ReservationResourceBinder),
                           not $AvailableResources (that one's scoped to the current resource's
                           own schedule, used below for the "additional resources on this same
                           booking" section) — otherwise this picker could only ever offer
                           resources sharing a schedule with whatever you started with. *}
                        <div class="resource-picker rp-resource-picker" id="rpResourcePicker">
                            <button type="button" class="ghost-btn resource-picker-trigger" id="rpResourcePickerTrigger"
                                aria-haspopup="listbox" aria-expanded="false">
                                <span class="resource-picker-dot" id="rpResourcePickerDot" style="display:none;"></span>
                                <span class="resource-picker-label" id="rpResourcePickerLabel"></span>
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <div class="resource-picker-panel" id="rpResourcePickerPanel">
                                <div class="resource-picker-search">
                                    <i class="bi bi-search"></i>
                                    <input type="text" id="rpResourcePickerSearch" placeholder="{translate key='SearchResources'}" autocomplete="off">
                                </div>
                                <div class="resource-picker-list" id="rpResourcePickerList">
                                    {foreach from=$SwitchableResources item=resource}
                                    <button type="button" class="resource-picker-item{if $resource->GetId() == $ResourceId} selected{/if}"
                                        data-resource-id="{$resource->GetId()}" data-color="{$SwitchableResourceColors[$resource->GetId()].bg}"
                                        data-linked-resource-id="{$resource->GetLinkedResourceId()}">
                                        <span class="resource-picker-check"><i class="bi bi-check-lg"></i></span>
                                        <span class="resource-picker-dot" style="--dot-color:{$SwitchableResourceColors[$resource->GetId()].bg}"></span>
                                        <span class="resource-picker-item-label">{$resource->Name|escape:'html'}</span>
                                    </button>
                                    {/foreach}
                                </div>
                                <div class="resource-picker-empty d-none" id="rpResourcePickerEmpty">{translate key='NoMatchesFound'}</div>
                            </div>
                        </div>

                        <div class="rp-availability d-none" id="rpAvailability">
                            <i class="bi bi-check-circle-fill"></i>
                            <span></span>
                        </div>

                        <div id="additionalResources">
                            {foreach from=$AvailableResources item=resource}
                            {if is_array($AdditionalResourceIds) && in_array($resource->Id, $AdditionalResourceIds)}
                            <input class="resourceId" type="hidden" name="{FormKeys::ADDITIONAL_RESOURCES}[]"
                                value="{$resource->Id}" />
                            {displayResource resource=$resource}
                            {/if}
                            {/foreach}
                        </div>
                        <div id="availableCredits" class="d-none">
                            {translate key=AvailableCredits}
                            <span id="availableCreditsCount" class="fw-bold">{$CurrentUserCredits}</span> |
                            {translate key=CreditsRequired}
                            <span id="requiredCreditsCount">
                                <span class="spinner-border spinner-border-sm" role="status"></span></span>
                            <span id="creditCost" class="fw-bold"></span>
                        </div>
                    </div>
                </div>

                <div class="rp-row rp-row-participants">
                    <i class="bi bi-people rp-row-icon"></i>
                    <div class="rp-participants-wrap">
                        {if $ShowParticipation && $AllowParticipation && $ShowReservationDetails}
                        {include file="Reservation/participation.tpl"}
                        {else}
                        {include file="Reservation/private-participation.tpl"}
                        {/if}
                    </div>
                </div>

                <div class="rp-row rp-row-description">
                    <i class="bi bi-text-left rp-row-icon"></i>
                    <textarea id="description" name="{FormKeys::DESCRIPTION}" class="rp-description"
                        placeholder="{translate key='AddDescription'}"
                        {if $DescriptionRequired}required="required" {/if}>{$Description}</textarea>
                </div>
            </div>

            <div class="rp-divider"></div>

            <div class="rp-collapsible-list">
                {if $RemindersEnabled}
                <details class="rp-collapse-row">
                    <summary>
                        <i class="bi bi-bell rp-row-icon"></i>
                        <span class="rp-collapse-label">{translate key=SendReminder}</span>
                        <i class="bi bi-chevron-down rp-chevron"></i>
                    </summary>
                    <div class="rp-collapse-body reservationReminders">
                        <div id="reminderOptionsStart" class="rp-reminder-row">
                            <div class="form-check">
                                <input type="checkbox" id="startReminderEnabled"
                                    class="reminderEnabled form-check-input" {formname key=START_REMINDER_ENABLED}
                                    aria-label="Enable Start Reminder Interval" />
                            </div>
                            <label for="startReminderTime" class="visually-hidden">Start Reminder Time</label>
                            <label for="startReminderInterval" class="visually-hidden">Start Reminder Interval</label>
                            <input type="number" min="0" max="999" size="3" maxlength="3" value="15"
                                class="reminderTime rp-reminder-number" {formname key=START_REMINDER_TIME}
                                id="startReminderTime" />
                            <select class="reminderInterval rp-reminder-select" {formname key=START_REMINDER_INTERVAL}
                                id="startReminderInterval">
                                <option value="{ReservationReminderInterval::Minutes}">{translate key=minutes}</option>
                                <option value="{ReservationReminderInterval::Hours}">{translate key=hours}</option>
                                <option value="{ReservationReminderInterval::Days}">{translate key=days}</option>
                            </select>
                            <span class="reminderLabel">{translate key=ReminderBeforeStart}</span>
                        </div>
                        <div id="reminderOptionsEnd" class="rp-reminder-row">
                            <div class="form-check">
                                <input type="checkbox" id="endReminderEnabled"
                                    class="reminderEnabled form-check-input" {formname key=END_REMINDER_ENABLED}
                                    aria-label="Enable End Reminder Interval" />
                            </div>
                            <label for="endReminderTime" class="visually-hidden">End Reminder Time</label>
                            <label for="endReminderInterval" class="visually-hidden">End Reminder Interval</label>
                            <input type="number" min="0" max="999" size="3" maxlength="3" value="15"
                                class="reminderTime rp-reminder-number" {formname key=END_REMINDER_TIME}
                                id="endReminderTime" />
                            <select class="reminderInterval rp-reminder-select" {formname key=END_REMINDER_INTERVAL}
                                id="endReminderInterval">
                                <option value="{ReservationReminderInterval::Minutes}">{translate key=minutes}</option>
                                <option value="{ReservationReminderInterval::Hours}">{translate key=hours}</option>
                                <option value="{ReservationReminderInterval::Days}">{translate key=days}</option>
                            </select>
                            <span class="reminderLabel">{translate key=ReminderBeforeEnd}</span>
                        </div>
                    </div>
                </details>
                {/if}

                {if $UploadsEnabled}
                <details class="rp-collapse-row">
                    <summary>
                        <i class="bi bi-paperclip rp-row-icon"></i>
                        <span class="rp-collapse-label">{translate key=AttachFile}</span>
                        <span class="rp-collapse-hint">({$MaxUploadSize} MB {translate key=Maximum})</span>
                        <i class="bi bi-chevron-down rp-chevron"></i>
                    </summary>
                    <div class="rp-collapse-body reservationAttachments">
                        {block name='attachments'}
                        {/block}
                        <div id="reservationAttachments">
                            <div class="attachment-item rp-attachment-item">
                                <label class="visually-hidden" for="reservationUploadFile">Reservation Upload File</label>
                                <input type="file" {formname key=RESERVATION_FILE multi=true} id="reservationUploadFile"
                                    class="rp-file-input" />
                                <a class="add-attachment rp-collapse-action" href="#">{translate key=Add}<i
                                        class="bi bi-plus-square-fill ms-1"></i></a>
                                <a class="remove-attachment rp-icon-btn" href="#"><span
                                        class="visually-hidden">{translate key=Delete}</span><i
                                        class="bi bi-dash-square-fill"></i></a>
                            </div>
                        </div>
                    </div>
                </details>
                {/if}

            </div>

            <input type="hidden" {formname key=RESERVATION_ID} value="{$ReservationId}" />
            <input type="hidden" {formname key=REFERENCE_NUMBER} value="{$ReferenceNumber}" id="referenceNumber" />
            <input type="hidden" {formname key=RESERVATION_ACTION} value="{$ReservationAction}" />
            <input type="hidden" {formname key=DELETE_REASON} value="" id="hdnDeleteReason" />
            <input type="hidden" {formname key=USER_ID} value="{$UserId}" id="userId" />

            <input type="hidden" {formname key=SERIES_UPDATE_SCOPE} id="hdnSeriesUpdateScope"
                value="{SeriesUpdateScope::FullSeries}" />

            {* Idempotency (create) / optimistic-locking (edit) — same mechanism the Overview
               page's own quick-create/quick-edit already rely on (IdempotencyService.php /
               ExistingReservationSeries::SetExpectedVersion()), now wired into the classic form
               too. Exactly one of these renders: requestId only makes sense for a create that
               hasn't happened yet, expectedVersion only for a series that already exists. *}
            {if empty($ReferenceNumber)}
            <input type="hidden" {formname key=REQUEST_ID} value="" id="rpRequestId" />
            {else}
            <input type="hidden" {formname key=EXPECTED_VERSION} value="{$Version}" id="rpExpectedVersion" />
            {/if}

            {csrf_token}

            <div id="retrySubmitParams" class="d-none"></div>
        </form>
    </div>
</div>


<div id="wait-box" class="modal fade" aria-labelledby="update-boxLabel" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body">
                <div id="creatingNotification" class="text-center">
                    <h3 id="createUpdateMessage" class="d-none">
                        {block name="ajaxMessage"}
                        {translate key=CreatingReservation}
                        {/block}
                    </h3>
                    <h3 id="checkingInMessage" class="d-none">
                        {translate key=CheckingIn}
                    </h3>
                    <h3 id="checkingOutMessage" class="d-none">
                        {translate key=CheckingOut}
                    </h3>
                    <h3 id="joiningWaitingList" class="d-none">
                        {translate key=AddingToWaitlist}
                    </h3>
                    <div class="spinner-border text-secondary" style="width: 3rem; height: 3rem;" role="status"></div>
                </div>
                <div id="result" class="text-center"></div>
            </div>
        </div>
    </div>
</div>

<div id="user-availability-box"></div>

</div>

{block name=extras}{/block}

{include file="javascript-includes.tpl"}

{* BeginDate/EndDate no longer get a jQuery UI datepicker attached -- the non-locked path uses
   the native <input type="date"> Quick Create-style picker instead (see rp-row-datetime above),
   and the locked-periods path never lets the user change the date at all. *}
{control type="DatePickerSetupControl" ControlId="EndRepeat" DefaultDate=$RepeatTerminationDate MinDate=$StartDate MaxDate=$AvailabilityEnd FirstDay=$FirstWeekday}
{control type="DatePickerSetupControl" ControlId="RepeatDate" MaxDate=$AvailabilityEnd FirstDay=$FirstWeekday MinDate=Date::Now()->ToTimezone($Timezone) Multiple=false}

{jsfile src="resourcePopup.js"}
{jsfile src="userPopup.js"}
{jsfile src="date-helper.js"}
{jsfile src="recurrence.js"}
{jsfile src="participant-picker.js"}
{jsfile src="reservation-datetime-picker.js"}
{jsfile src="reservation.js"}
{jsfile src="autocomplete.js"}
{jsfile src="force-numeric.js"}
{jsfile src="reservation-reminder.js"}
{jsfile src="ajax-helpers.js"}
{jsfile src="reservation-pdf.js"}
{vendor_js src="jqtree/1.8.11/js/tree.jquery.js"}

{include file="Reservation/pdf_libraries.tpl"}
{include file="Reservation/pdf.tpl"}
<script type="text/javascript">
    $(function() {
        // One UUID per page load of the create form — covers a double-click/resend of the
        // exact same submission (IdempotencyService.php recognizes a resend of the same
        // requestId and returns the original reservation instead of creating a second one).
        // No-op on the edit form (no #rpRequestId there — see this template's hidden-fields
        // section, which only renders it when there's no reference number yet).
        (function () {
            var $requestId = $('#rpRequestId');
            if (!$requestId.length) {
                return;
            }
            var uuid = (window.crypto && typeof window.crypto.randomUUID === 'function')
                ? window.crypto.randomUUID()
                : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                    var r = (Math.random() * 16) | 0;
                    var v = c === 'x' ? r : (r & 0x3) | 0x8;
                    return v.toString(16);
                });
            $requestId.val(uuid);
        })();

        var scopeOptions = {
            instance: '{SeriesUpdateScope::ThisInstance}',
            full: '{SeriesUpdateScope::FullSeries}',
            future: '{SeriesUpdateScope::FutureInstances}'
        };

        var reservationOpts = {
            additionalResourceElementId: '{FormKeys::ADDITIONAL_RESOURCES}',
            accessoryListInputId: '{FormKeys::ACCESSORY_LIST}[]',
            returnUrl: '{$ReturnUrl}',
            scopeOpts: scopeOptions,
            createUrl: 'ajax/reservation_save.php',
            updateUrl: 'ajax/reservation_update.php',
            deleteUrl: 'ajax/reservation_delete.php',
            checkinUrl: 'ajax/reservation_checkin.php?action={ReservationAction::Checkin}',
            checkoutUrl: 'ajax/reservation_checkin.php?action={ReservationAction::Checkout}',
            waitlistUrl: 'ajax/reservation_waitlist.php',
            userAutocompleteUrl: "ajax/autocomplete.php?type={AutoCompleteType::User}",
            groupAutocompleteUrl: "ajax/autocomplete.php?type={AutoCompleteType::Group}",
            changeUserAutocompleteUrl: "ajax/autocomplete.php?type={AutoCompleteType::MyUsers}",
            maxConcurrentUploads: '{$MaxUploadCount}',
            guestLabel: '({translate key=Guest})',
            accessoriesUrl: 'ajax/available_accessories.php?{QueryStringKeys::START_DATE}=[sd]&{QueryStringKeys::END_DATE}=[ed]&{QueryStringKeys::START_TIME}=[st]&{QueryStringKeys::END_TIME}=[et]&{QueryStringKeys::REFERENCE_NUMBER}=[rn]',
            resourcesUrl: 'ajax/unavailable_resources.php?{QueryStringKeys::SCHEDULE_ID}={$ScheduleId}&{QueryStringKeys::START_DATE}=[sd]&{QueryStringKeys::END_DATE}=[ed]&{QueryStringKeys::START_TIME}=[st]&{QueryStringKeys::END_TIME}=[et]&{QueryStringKeys::REFERENCE_NUMBER}=[rn]',
            creditsUrl: 'ajax/reservation_credits.php',
            creditsEnabled: '{$CreditsEnabled}',
            emailUrl: 'ajax/reservation_email.php?{QueryStringKeys::REFERENCE_NUMBER}={$ReferenceNumber}',
            availabilityUrl: 'ajax/availability.php?{QueryStringKeys::SCHEDULE_ID}={$ScheduleId}',
            maximumResources: {$MaximumResources|default:0}
        };

        var reminderOpts = {
            reminderTimeStart: '{$ReminderTimeStart}',
            reminderTimeEnd: '{$ReminderTimeEnd}',
            reminderIntervalStart: '{$ReminderIntervalStart}',
            reminderIntervalEnd: '{$ReminderIntervalEnd}'
        };

        var reservation = new Reservation(reservationOpts);
        reservation.init('{$UserId}', '{format_date date=$StartDate key=system_datetime timezone=$Timezone}', '{format_date date=$EndDate key=system_datetime timezone=$Timezone}');

        var reminders = new Reminder(reminderOpts);
        reminders.init();

        // Shared participant picker (Web/scripts/participant-picker.js) — same search endpoint/
        // conditions (Chinese/Korean/English name, username/employee ID, email) and same chip
        // style as Quick Create. Also accepts external (non-user) email invitees when guest
        // participation is allowed, and excludes the reservation owner so they can't add
        // themselves. #participantList's old hidden-input-per-chip markup is gone;
        // participantList[] / guestParticipationList[] are instead synced into
        // #rpParticipantHiddenInputs on every change, which is what
        // Pages/Ajax/ReservationSavePage.php::GetParticipants()/GetParticipatingGuests() and
        // ReservationUpdatePresenter actually read on submit.
        var rpOwnerId = {$UserId};
        var rpOwnerEmail = '{$UserEmail|escape:"javascript"}';
        var rpAllowGuestParticipation = {if $AllowGuestParticipation}true{else}false{/if};
        var rpInviteEmailLabel = '{translate key="InviteExternalEmail"|escape:"javascript"}';
        var rpExternalBadgeText = '{translate key="ExternalParticipantBadge"|escape:"javascript"}';
        var rpNoMatchesText = '{translate key="NoMatchesFound"|escape:"javascript"}';

        function rpSyncHiddenInputs(containerSelector, internalFieldName, externalFieldName, participants) {
            var $hidden = $(containerSelector).empty();
            participants.forEach(function (p) {
                var name = p.type === 'external' ? externalFieldName : internalFieldName;
                var value = p.type === 'external' ? p.email : p.id;
                $('<input type="hidden">').attr('name', name).val(value).appendTo($hidden);
            });
        }

        var rpParticipantPicker;

        rpParticipantPicker = createParticipantPicker({
            input: '#rpParticipantSearchInput',
            results: '#rpParticipantSearchResults',
            chipList: '#rpParticipantChipList',
            wrapper: '#rpParticipantSearch',
            searchUrl: 'ajax/overview_fullcalendar_participant_search.php?term=',
            noMatchesText: rpNoMatchesText,
            allowExternal: rpAllowGuestParticipation,
            inviteEmailLabel: rpInviteEmailLabel,
            externalBadgeText: rpExternalBadgeText,
            excludeIds: [rpOwnerId],
            excludeEmails: [rpOwnerEmail],
            onChange: function (participants) {
                rpSyncHiddenInputs('#rpParticipantHiddenInputs', 'participantList[]', 'guestParticipationList[]', participants);
                $('#participantBadge').text(participants.length);
            },
        });

        rpParticipantPicker.reset([
            {foreach from=$Participants item=user name=rpParticipantsLoop}
            { id: {$user->UserId}, label: "{$user->FullName|escape:'javascript'}", type: 'internal' },
            {/foreach}
            {foreach from=$ParticipatingGuests item=guest name=rpParticipatingGuestsLoop}
            { id: 'ext:{$guest|lower|escape:"javascript"}', label: "{$guest|escape:'javascript'}", type: 'external', email: "{$guest|escape:'javascript'}" }{if !$smarty.foreach.rpParticipatingGuestsLoop.last},{/if}
            {/foreach}
        ]);

        // Google-Calendar-style "更多選項": Quick Create's in-progress draft (title/
        // description/participants) travels here via sessionStorage rather than the URL —
        // see saveQuickCreateDraft() in overview_fullcalendar.js. Resource/date/time already
        // arrive through rid/sd/ed (NewReservationInitializer), this only backfills what
        // those query params don't cover. Single read-then-discard, and create mode only
        // (checked the same way this file already distinguishes create/edit for
        // autoSetTerminationDate below) — editing an existing reservation always loads its
        // real data instead, never a leftover draft from some earlier quick-create attempt.
        if ($('#referenceNumber').val() === '') {
            try {
                var qcDraftRaw = sessionStorage.getItem('overviewFullCalendarQuickCreateDraft');
                sessionStorage.removeItem('overviewFullCalendarQuickCreateDraft');
                if (qcDraftRaw) {
                    var qcDraft = JSON.parse(qcDraftRaw);
                    if (qcDraft.title) {
                        $('#reservationTitle').val(qcDraft.title);
                    }
                    if (qcDraft.description) {
                        $('#description').val(qcDraft.description);
                    }
                    if ((qcDraft.participants || []).length) {
                        rpParticipantPicker.reset(qcDraft.participants);
                    }
                }
            } catch (e) {
                // Malformed/unavailable sessionStorage — the form just opens blank instead of
                // prefilled, same as clicking "更多選項" with nothing typed in yet.
            }
        }

        {foreach from=$Accessories item=accessory}
        reservation.addAccessory({$accessory->AccessoryId}, {$accessory->QuantityReserved}, "{$accessory->Name|escape:'javascript'}");
        {/foreach}

        reservation.addResourceGroups({$ResourceGroupsAsJson});

        {if !$LockPeriods}
        // Start/end time picker — same widget Quick Create uses, driven by the shared factory
        // in Web/scripts/reservation-datetime-picker.js (TimeDropdown/formatTimeLabel/
        // minutesToHHMMSS/buildStartTimeOptions/buildEndTimeOptions/formatDateLabel). Writes
        // into #BeginDate/#BeginPeriod/#EndPeriod/#EndDate (this form's original field ids and
        // name/POST-key bindings, unchanged) and fires 'change' on each so the
        // existing $('#BeginDate, #BeginPeriod, #EndPeriod').on('change', ...) binding further
        // below (scheduleRpAvailabilityCheck) and recurrence.js's own listeners on those same
        // ids keep working unmodified.
        (function () {
            function parseHHMM(str) {
                return parseInt(str.slice(0, 2), 10) * 60 + parseInt(str.slice(3, 5), 10);
            }

            var rpBeginMinutes = parseHHMM('{if $StartDate}{formatdate date=$StartDate format="H:i:s"}{else}09:00:00{/if}');
            var rpEndMinutes = parseHHMM('{if $EndDate}{formatdate date=$EndDate format="H:i:s"}{else}09:30:00{/if}');

            var rpAllDropdownClosers = [];
            function rpCloseAllDropdowns() {
                rpAllDropdownClosers.forEach(function (close) { close(); });
            }
            $(document).on('click', rpCloseAllDropdowns);

            var rpStartPicker = TimeDropdown('#rpStartPill', '#rpStartDropdown', function (minutes) {
                var duration = Math.max(30, rpEndMinutes - rpBeginMinutes);
                rpBeginMinutes = minutes;
                rpEndMinutes = Math.min(24 * 60 - 30, minutes + duration);
                refreshRpTimeUI();
            }, rpCloseAllDropdowns);
            var rpEndPicker = TimeDropdown('#rpEndPill', '#rpEndDropdown', function (minutes) {
                rpEndMinutes = minutes;
                refreshRpTimeUI();
            }, rpCloseAllDropdowns);
            rpAllDropdownClosers.push(rpStartPicker.close, rpEndPicker.close);

            function refreshRpTimeUI() {
                var minEndMinutes = rpBeginMinutes + 30;
                if (rpEndMinutes < minEndMinutes) {
                    rpEndMinutes = minEndMinutes;
                }
                if (rpEndMinutes > 24 * 60 - 30) {
                    rpEndMinutes = 24 * 60 - 30;
                }

                $('#rpStartPill').text(formatTimeLabel(rpBeginMinutes));
                $('#rpEndPill').text(formatTimeLabel(rpEndMinutes));

                rpStartPicker.render(buildStartTimeOptions(), rpBeginMinutes);
                rpEndPicker.render(buildEndTimeOptions(rpBeginMinutes, minEndMinutes), rpEndMinutes);

                $('#rpTimeError').addClass('d-none').empty();

                $('#BeginPeriod').val(minutesToHHMMSS(rpBeginMinutes)).trigger('change');
                $('#EndPeriod').val(minutesToHHMMSS(rpEndMinutes)).trigger('change');
            }

            $('#rpStartDate').val($('#BeginDate').val());
            $('#rpStartDateDisplay').text(formatDateLabel($('#rpStartDate').val()));

            $('#rpStartDate').on('change', function () {
                var isoDate = $(this).val();
                $('#BeginDate').val(isoDate).trigger('change');
                $('#EndDate').val(isoDate).trigger('change');
                $('#rpStartDateDisplay').text(formatDateLabel(isoDate));
            });

            refreshRpTimeUI();
        })();
        {/if}

        // Searchable/switchable primary resource picker — same open/close/search/select
        // pattern as Quick Create's own #qcResourcePicker (Web/scripts/overview_fullcalendar.js
        // initQcResourcePicker()), written standalone here rather than extracted into a shared
        // factory since this page's list is flat (no physical/video split) where Quick Create's
        // is grouped — different enough markup that sharing one function would need as much
        // branching as just having two.
        (function () {
            var $picker = $('#rpResourcePicker');
            var $trigger = $('#rpResourcePickerTrigger');
            var $panel = $('#rpResourcePickerPanel');
            var $search = $('#rpResourcePickerSearch');
            var $list = $('#rpResourcePickerList');

            function close() {
                $picker.removeClass('open');
                $trigger.attr('aria-expanded', 'false');
            }

            function updateTrigger() {
                var $selected = $list.find('.resource-picker-item.selected').first();
                $('#rpResourcePickerLabel').text($selected.find('.resource-picker-item-label').text());
                var color = $selected.data('color');
                var $dot = $('#rpResourcePickerDot');
                $dot.toggle(!!color);
                $dot.get(0).style.setProperty('--dot-color', color || '');
            }
            updateTrigger();

            $trigger.on('click', function (e) {
                e.stopPropagation();
                if ($picker.hasClass('open')) {
                    close();
                    return;
                }
                $picker.addClass('open');
                $trigger.attr('aria-expanded', 'true');
                $search.val('').trigger('input');
                setTimeout(function () { $search.trigger('focus'); }, 0);
            });
            $panel.on('click', function (e) { e.stopPropagation(); });
            $(document).on('click', function (e) {
                if (!$(e.target).closest($picker).length) {
                    close();
                }
            });

            // Admin > Manage Resources lets a resource be paired with another one it should
            // always be booked alongside (typically a physical room paired with its usual
            // video-conference resource, BookableResource::GetLinkedResourceId()). Switching
            // the primary resource here auto-adds/replaces that pairing in #additionalResources
            // -- bypassing the schedule-scoped "add resources" checkbox tree entirely, since
            // the linked resource usually lives on its own separate schedule and would never
            // appear in that tree (see COASIA-CHANGES.md for why). Only ever touches the one
            // slot it manages (data-auto-linked="1"), so a resource the user added manually via
            // that dialog is left alone; and it only runs on an actual click here, never on
            // page load, so opening an existing reservation for edit never silently re-adds
            // something the user had deliberately removed.
            function applyLinkedResource(linkedResourceId) {
                var $additional = $('#additionalResources');
                var $existingAutoLinked = $additional.find('.resourceName[data-auto-linked="1"]');
                // Only reload credits/custom-attributes (notifyResourcesChanged) when this
                // actually changes #additionalResources -- most resources have no link
                // configured, and that case should behave exactly as it did before this
                // feature existed.
                var changed = $existingAutoLinked.length > 0;
                $existingAutoLinked.remove();

                linkedResourceId = linkedResourceId ? String(linkedResourceId) : '';
                var alreadyPresent = !linkedResourceId ||
                    $('#primaryResourceId').val() === linkedResourceId ||
                    $additional.find('.resourceId').filter(function () {
                        return $(this).val() === linkedResourceId;
                    }).length > 0;

                if (!alreadyPresent) {
                    var $source = $list.find('.resource-picker-item[data-resource-id="' + linkedResourceId + '"]');
                    if ($source.length) {
                        var name = $source.find('.resource-picker-item-label').text();
                        var color = $source.data('color');
                        var className = 'resourceName rounded-1 my-1 p-1';
                        var styleAttr = '';
                        if (color) {
                            styleAttr = ' style="background-color:' + color + ';"';
                        } else {
                            className += ' text-success bg-success bg-opacity-10';
                        }
                        $additional.append(
                            '<div class="' + className + '" data-auto-linked="1"' + styleAttr + '>' +
                            '<span class="resourceDetails" data-resourceId="' + linkedResourceId + '">' +
                            $('<div>').text(name).html() +
                            '</span><input class="resourceId" type="hidden" name="{FormKeys::ADDITIONAL_RESOURCES}[]" value="' +
                            linkedResourceId + '" /></div>'
                        );
                        changed = true;
                    }
                }

                if (changed) {
                    reservation.notifyResourcesChanged();
                }
            }

            $panel.on('click', '.resource-picker-item', function () {
                var resourceId = String($(this).data('resource-id'));
                $list.find('.resource-picker-item').removeClass('selected');
                $(this).addClass('selected');
                $('#primaryResourceId').val(resourceId);
                updateTrigger();
                close();
                scheduleRpAvailabilityCheck();
                applyLinkedResource($(this).data('linked-resource-id'));
            });

            $search.on('input', function () {
                var term = $(this).val().trim().toLowerCase();
                var anyVisible = false;
                $list.find('.resource-picker-item').each(function () {
                    var match = $(this).find('.resource-picker-item-label').text().toLowerCase().indexOf(term) !== -1;
                    $(this).toggleClass('d-none', !match);
                    anyVisible = anyVisible || match;
                });
                $('#rpResourcePickerEmpty').toggleClass('d-none', anyVisible);
            });
        })();

        // Live "此時段可用" check — same endpoint/contract Quick Create's own indicator uses
        // (ajax/overview_fullcalendar_availability.php, params resourceId/start/end/
        // excludeReferenceNumber, JSON response with an "available" boolean), re-run on every
        // resource/date/time change. excludeReferenceNumber only matters in edit mode —
        // without it, editing a reservation would always see its own current slot as a
        // conflict with itself.
        var rpAvailabilityTimer = null;
        function scheduleRpAvailabilityCheck() {
            if (rpAvailabilityTimer) {
                clearTimeout(rpAvailabilityTimer);
            }
            rpAvailabilityTimer = setTimeout(checkRpAvailability, 300);
        }
        function checkRpAvailability() {
            var resourceId = $('#primaryResourceId').val();
            var date = $('#BeginDate').val();
            var startTime = $('#BeginPeriod').val();
            var endTime = $('#EndPeriod').val();
            if (!resourceId || !date || !startTime || !endTime) {
                return;
            }
            var params = new URLSearchParams({
                resourceId: resourceId,
                start: date + ' ' + startTime,
                end: date + ' ' + endTime,
            });
            var referenceNumber = $('#referenceNumber').val();
            if (referenceNumber) {
                params.set('excludeReferenceNumber', referenceNumber);
            }
            var requestKey = resourceId + '|' + date + '|' + startTime + '|' + endTime;
            fetch('ajax/overview_fullcalendar_availability.php?' + params.toString(), { credentials: 'same-origin' })
                .then(function (res) { return res.ok ? res.json() : null; })
                .then(function (json) {
                    if (!json || requestKey !== (
                        $('#primaryResourceId').val() + '|' + $('#BeginDate').val() + '|' +
                        $('#BeginPeriod').val() + '|' + $('#EndPeriod').val()
                    )) {
                        return;
                    }
                    var $el = $('#rpAvailability').removeClass('d-none');
                    $el.toggleClass('unavailable', !json.available);
                    $el.find('i').toggleClass('bi-check-circle-fill', json.available).toggleClass('bi-x-circle-fill', !json.available);
                    $el.find('span').text(json.available
                        ? '{translate key="ResourceAvailable"|escape:"javascript"}'
                        : '{translate key="ResourceUnavailable"|escape:"javascript"}');
                })
                .catch(function () {});
        }
        $('#BeginDate, #BeginPeriod, #EndPeriod').on('change', scheduleRpAvailabilityCheck);
        scheduleRpAvailabilityCheck();

        var recurOpts = {
            repeatType: '{$RepeatType}',
            repeatInterval: '{$RepeatInterval}',
            repeatMonthlyType: '{$RepeatMonthlyType}',
            repeatWeekdays: [{foreach from=$RepeatWeekdays item=day}{$day}, {/foreach}],
            autoSetTerminationDate: $('#referenceNumber').val() != '',
            customRepeatExclusions: ['{formatdate date=$StartDate key=system}']
        };

        var recurrence = new Recurrence(recurOpts);
        recurrence.init();

        // This page's own reduced set (不重複/每天/每週/每月/自訂) — RecurrenceControl/
        // RecurrenceDiv.tpl/recurrence.js are shared with search-availability.tpl and the admin
        // blackouts pages, which still offer "每年", so this only trims the <option> here rather
        // than touching the shared control. Kept if a reservation being edited already repeats
        // yearly (pre-existing data), so its saved value still has a matching option to select.
        if ('{$RepeatType}' !== 'yearly') {
            $('#repeatOptions option[value="yearly"]').remove();
        }

        // Two-stage 不重複/重複 gate in front of #repeatOptions (see rp-row-recurrence markup
        // above) — #rpRepeatGate/#rpRepeatFrequency don't submit anything themselves, they just
        // drive #repeatOptions' value and .trigger('change') so recurrence.js's own
        // ChangeRepeatOptions()/NotifyChange() keep running exactly as before.
        (function () {
            var $gate = $('#rpRepeatGate');
            var $frequency = $('#rpRepeatFrequency');
            var $realSelect = $('#repeatOptions');

            function syncFromReal() {
                var val = $realSelect.val();
                if (!val || val === 'none') {
                    $gate.val('none');
                    $frequency.addClass('d-none');
                    return;
                }
                $gate.val('repeat');
                if ($frequency.find('option[value="' + val + '"]').length) {
                    $frequency.val(val);
                    $frequency.removeClass('d-none');
                }
            }

            $gate.on('change', function () {
                if ($gate.val() === 'none') {
                    $frequency.addClass('d-none');
                    $realSelect.val('none').trigger('change');
                } else {
                    var freqVal = $frequency.val() || 'daily';
                    $frequency.val(freqVal).removeClass('d-none');
                    $realSelect.val(freqVal).trigger('change');
                }
            });

            $frequency.on('change', function () {
                $realSelect.val($frequency.val()).trigger('change');
            });

            syncFromReal();
        })();

        recurrence.onChange(reservation.repeatOptionsChanged);

        {foreach from=$CustomRepeatDates item=date}
        recurrence.addCustomDate('{format_date date=$date key=system timezone=$Timezone}',
        '{format_date date=$date key=schedule_daily timezone=$Timezone}');
        {/foreach}

        // "未儲存便離開時需要提醒使用者" — any direct edit marks the form dirty; cleared the
        // moment a save actually succeeds (the ajaxOptions.success wrapper below) so the
        // normal "click Create/Update, get redirected to returnUrl" flow never itself
        // triggers this prompt.
        var formDirty = false;
        $('#form-reservation').on('input change', function () {
            formDirty = true;
        });
        window.addEventListener('beforeunload', function (e) {
            if (!formDirty) {
                return;
            }
            e.preventDefault();
            // "leave site?" prompt actually appear at all in those browsers.
            e.returnValue = '';
        });

        var ajaxOptions = {
            target: '#result', // target element(s) to be updated with server response
            beforeSubmit: reservation.preSubmit, // pre-submit callback
            // Wraps reservation.showResponse (which it still calls unchanged) purely to also
            // clear formDirty the moment a save actually succeeds — see the beforeunload
            // handler below. Checking the response HTML for the same #reservation-created
            // marker ShowReservationAjaxResponse() itself keys off avoids depending on
            // *when* the user clicks that dialog's own "OK" button: the reservation is
            // already persisted server-side as soon as this response comes back, so nothing
            // needs "unsaved changes" protection from that point on, even before it's
            // acknowledged.
            success: function (responseText, statusText, xhr, $form) {
                if ($('<div></div>').html(responseText).find('#reservation-created').length) {
                    formDirty = false;
                }
                reservation.showResponse(responseText, statusText, xhr, $form);
            },
            // A version conflict (Pages/Ajax/ReservationUpdatePage.php) responds 409 with the
            // same #reservation-failed HTML a normal validation failure would — jQuery Form
            // Plugin only auto-fills #target on a 2xx response, so without this the request is
            // correctly rejected server-side but the page would otherwise just sit there
            // showing nothing, looking like the click did nothing at all.
            error: function (xhr) {
                $('#result').html(xhr.responseText);
                reservation.showResponse(xhr.responseText, 'error', xhr, $('#form-reservation'));
            }
        };

        $('#form-reservation').submit(function() {
            var $title = $('#reservationTitle');
            {if $TitleRequired}
                // CoAsia: a meeting name is mandatory — block the submit and surface the error
                // inline rather than saving an empty/placeholder title.
                if ($title.val().trim() === '') {
                    $('#result').html('<div class="error"><div>{translate key="TitleRequiredRule"|escape:"javascript"}</div></div>');
                    reservation.showResponse('', 'error', null, $('#form-reservation'));
                    $title.trigger('focus');
                    return false;
                }
            {else}
                // "空白儲存為（無標題）" — same default the calendar/email views already fall
                // back to display-side (Resources::GetString('NoReservationTitle')); writing it
                // into the field here means it's the literal stored title.
                if ($title.val().trim() === '') {
                    $title.val('{translate key="NoReservationTitle"|escape:"javascript"}');
                }
            {/if}
            $(this).ajaxSubmit(ajaxOptions);
            return false;
        });

        $('#userName').bindUserDetails();

        translateTooltips();

    });
    $('.modal').on('shown.bs.modal', function() {
        $(this).find('[autofocus]').focus();
    });
</script>

<script>
    function translateTooltips() {
        var resourcesContainer = document.querySelector('#reservation-resources');
        var resources = [].slice.call(resourcesContainer.querySelectorAll('[data-bs-toggle="tooltip"]'));
        resources.forEach(function(resource) {
            var tooltipType = resource.getAttribute('data-bs-title');
            if (tooltipType === 'approval') {
                var tooltipText = "{translate key=RequiresApproval}";
            }
            if (tooltipType === 'checkin') {
                var tooltipText ="{translate key=RequiresCheckInNotification}";
            }
            if (tooltipType === 'autorelease') {
                var text = "{translate key=AutoReleaseNotification args='%s'}";
                    var tooltipText = text.replace('%s', resource.getAttribute('data-autorelease'));
                }
                resource.setAttribute('data-bs-title', tooltipText);
                new bootstrap.Tooltip(resource);
            });
        }
    </script>

    {include file='globalfooter.tpl'}
