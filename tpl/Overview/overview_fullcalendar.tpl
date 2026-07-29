{* Prototype template — evaluates FullCalendar Standard against tpl/Overview/overview.tpl's
   hand-rolled week grid. Deliberately does not touch overview.tpl; this is a fully separate
   template that happens to {include} the same shared globalheader.tpl so the left sidebar
   (including the resource checklist) looks and behaves identically. *}
{include file='globalheader.tpl' cssFiles='css/overview_fullcalendar.css'}

<div class="shell" id="page-overview-fc">

    <div class="cal-toolbar">
        <h1>{translate key="Overview"} <span class="fc-proto-badge">FullCalendar prototype</span></h1>

        <div class="toolbar-nav-group">
            <a class="today-btn" id="fcTodayBtn" href="#">{translate key='Today'}</a>
            <div class="arrows">
                <a class="nav" id="fcPrevBtn" href="#" aria-label="Previous week"><i class="bi bi-chevron-left"></i></a>
                <a class="nav" id="fcNextBtn" href="#" aria-label="Next week"><i class="bi bi-chevron-right"></i></a>
            </div>
            <span class="month-label" id="fcMonthLabel"></span>
        </div>

        <div class="toolbar-actions">
            {* Three independent dimensions — reservation scope (mine/all), resource type
               (physical/video), and view (day/week/month) are NOT the same axis and must
               not share one mutually-exclusive tab group. Each toggles its own state in
               overview_fullcalendar.js (mineOnly / activeCalCategory / FullCalendar's view)
               without touching the other two. *}
            <div class="cal-scope-tabs" id="fcScopeTabs">
                <button type="button" class="cal-category-tab active" data-scope="all">{translate key='ReservationScopeAll'}</button>
                <button type="button" class="cal-category-tab" data-scope="mine">{translate key='ReservationScopeMine'}</button>
            </div>
            <div class="cal-category-tabs" id="fcCategoryTabs">
                <button type="button" class="cal-category-tab active" data-category="physical">{translate key='PhysicalRooms'}</button>
                <button type="button" class="cal-category-tab" data-category="video">{translate key='VideoConferenceResources'}</button>
            </div>
            <div class="cal-view-tabs" id="fcViewTabs">
                <button type="button" class="cal-view-tab" data-view="timeGridDay">{translate key='Day'}</button>
                <button type="button" class="cal-view-tab active" data-view="timeGridWeek">{translate key='Week'}</button>
                <button type="button" class="cal-view-tab" data-view="dayGridMonth">{translate key='Month'}</button>
            </div>
        </div>
    </div>

    <div class="fc-frame">
        <div id="fullcalendarRoot"></div>
    </div>

    <div class="modal fade" id="quickCreateModal" tabindex="-1" aria-hidden="true" aria-labelledby="quickCreateModalLabel">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <button type="button" class="btn-close qc-close" data-bs-dismiss="modal" aria-label="{translate key='Cancel'}"></button>
                <div class="modal-body">
                    <label for="qcTitle" class="visually-hidden" id="quickCreateModalLabel">{translate key='NewReservation'}</label>
                    <input type="text" class="qc-title" id="qcTitle" placeholder="{translate key='NewReservation'}" autocomplete="off">

                    <div class="qc-row">
                        <i class="bi bi-clock"></i>
                        <div class="qc-row-text">
                            <div class="qc-time-edit">
                                <span id="qcDateLabel"></span>
                                <div class="qc-time-picker" id="qcStartPicker">
                                    <button type="button" class="qc-time-pill" id="qcStartPill" aria-haspopup="listbox" aria-expanded="false"></button>
                                    <div class="qc-time-dropdown" id="qcStartDropdown" role="listbox"></div>
                                </div>
                                <span class="qc-time-dash">–</span>
                                <div class="qc-time-picker" id="qcEndPicker">
                                    <button type="button" class="qc-time-pill" id="qcEndPill" aria-haspopup="listbox" aria-expanded="false"></button>
                                    <div class="qc-time-dropdown" id="qcEndDropdown" role="listbox"></div>
                                </div>
                            </div>
                            <span class="qc-row-sub">{translate key='DoesNotRepeat'}</span>
                        </div>
                    </div>
                    <div class="qc-row">
                        <i class="bi bi-geo-alt"></i>
                        <div class="qc-row-text">
                            <div class="qc-resource-tabs" id="qcResourceTabs">
                                <button type="button" class="qc-resource-tab active" data-category="physical">{translate key='PhysicalRooms'}</button>
                                <button type="button" class="qc-resource-tab" data-category="video">{translate key='VideoConferenceResources'}</button>
                            </div>
                            <div class="qc-resource-picker" id="qcResourcePicker">
                                <button type="button" class="qc-time-pill" id="qcResourcePill" aria-haspopup="listbox" aria-expanded="false"></button>
                                <div class="qc-time-dropdown" id="qcResourceDropdown" role="listbox"></div>
                            </div>
                        </div>
                    </div>
                    <div class="qc-row qc-row-top">
                        <i class="bi bi-text-left"></i>
                        <div class="qc-row-text">
                            <textarea id="qcDescription" class="qc-description" rows="2" placeholder="{translate key='Description'}"></textarea>
                        </div>
                    </div>

                    <div id="qcError" class="alert alert-danger py-2 px-3 small d-none"></div>
                </div>
                <div class="modal-footer">
                    <a href="#" id="qcMoreOptions" class="link-primary me-auto">{translate key='MoreOptions'}</a>
                    <button type="button" class="btn btn-primary" id="qcSave">{translate key='Create'}</button>
                </div>
            </div>
        </div>
    </div>

    {* Google Calendar-style event card: a thin colour strip, an icon-only action row
       (edit/delete/close — never text buttons), body rows, and "查看詳情" as a plain text
       link (not a primary button) at the bottom. Edit/delete are hidden per-event via
       canEdit/canDelete (server-computed, ReservationAuthorization::CanEdit()) — someone
       who can only view gets the close button and the details link, nothing else. *}
    <div class="event-popover" id="eventPopover">
        <div class="ep-color-bar" id="epColorBar"></div>
        <div class="ep-toolbar">
            <a href="#" id="epEditLink" class="reservation-icon-button d-none" title="{translate key='Edit'}" aria-label="{translate key='Edit'}">
                <i class="bi bi-pencil"></i>
            </a>
            <button type="button" id="epDeleteBtn" class="reservation-icon-button reservation-icon-button-danger d-none" title="{translate key='Delete'}" aria-label="{translate key='Delete'}">
                <i class="bi bi-trash"></i>
            </button>
            <button type="button" id="epCloseBtn" class="reservation-icon-button" title="{translate key='Cancel'}" aria-label="{translate key='Cancel'}">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="ep-body">
            <div class="ep-title" id="epTitle"></div>
            <div class="ep-row"><i class="bi bi-clock"></i><span id="epTime"></span></div>
            <div class="ep-row"><i class="bi bi-geo-alt"></i><span id="epResource"></span></div>
            <div class="ep-row d-none" id="epOwnerRow"><i class="bi bi-person"></i><span id="epOwner"></span></div>
        </div>
        <div class="ep-footer">
            <a href="#" id="epViewLink" class="ep-link-btn">{translate key='ViewDetails'} <i class="bi bi-arrow-right"></i></a>
        </div>
    </div>

    {* Delete confirmation — reuses the existing delete pipeline (Web/ajax/reservation_delete.php,
       Presenters/Reservation/ReservationDeletePresenter.php, SeriesUpdateScope) rather than a new
       endpoint. Non-recurring reservations get a plain confirm; recurring ones pick a scope via
       radio list (ThisInstance/FutureInstances/FullSeries) instead of three separate action
       buttons — this dialog has exactly one affirmative action ("delete"), never three. No
       Bootstrap .modal-header/.modal-footer/.btn-outline-danger — Bootstrap only drives the
       show/hide behavior here; every visible surface is del-* (overview_fullcalendar.css). *}
    <div class="modal fade" id="deleteReservationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content del-dialog">
                <button type="button" class="del-close-btn" data-bs-dismiss="modal" aria-label="{translate key='Cancel'}">
                    <i class="bi bi-x-lg"></i>
                </button>
                <div class="del-body">
                    <div class="del-icon"><i class="bi bi-trash"></i></div>
                    <div class="del-title">{translate key='ConfirmDeleteReservation'}</div>
                    <div class="del-subtitle">{translate key='DeleteNotifiesParticipants'}</div>

                    <div id="epDeleteRecurringBody" class="del-scope d-none">
                        <div class="del-scope-label">{translate key='DeleteRecurringScopePrompt'}</div>
                        <label class="del-radio">
                            <input type="radio" name="epDeleteScope" value="this">
                            <span>{translate key='ThisInstance'}</span>
                        </label>
                        <label class="del-radio">
                            <input type="radio" name="epDeleteScope" value="future">
                            <span>{translate key='FutureInstances'}</span>
                        </label>
                        <label class="del-radio">
                            <input type="radio" name="epDeleteScope" value="full" checked>
                            <span>{translate key='AllInstances'}</span>
                        </label>
                    </div>

                    <div class="del-reason">
                        <label for="epDeleteReason">{translate key='Reason'} ({translate key='Optional'})</label>
                        <textarea id="epDeleteReason" rows="2"></textarea>
                    </div>

                    <div id="epDeleteError" class="del-error d-none"></div>
                </div>
                <div class="del-footer">
                    <button type="button" class="del-btn-text" data-bs-dismiss="modal">{translate key='Cancel'}</button>
                    <button type="button" class="del-btn-danger" id="epDeleteConfirmBtn">
                        <span class="spinner-border spinner-border-sm del-btn-spinner d-none" aria-hidden="true"></span>
                        <span id="epDeleteConfirmLabel">{translate key='Delete'}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {* Reservation Drawer — the one full editor shared by "更多選項" (create, prefilled from
       the quick-create draft) and the event card's pencil icon (edit, loaded by reference
       number via ajax/overview_fullcalendar_reservation.php). Replaces navigating to
       reservation.php for the normal create/edit flow; that page stays reachable directly
       (bookmarks, admin tools) but is no longer a UI a regular user gets routed through.
       Sections for data this phase doesn't yet expose editing for (recurrence rule,
       participants, reminders) render as a read-only summary rather than disappearing, and
       are echoed back unchanged on save (overview_fullcalendar.js) so a basic edit here can
       never silently wipe them. *}
    <div class="modal fade reservation-drawer-modal" id="reservationDrawer" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog reservation-drawer-dialog">
            <div class="modal-content reservation-drawer">
                <div class="rd-header">
                    <div class="rd-header-title" id="rdHeaderTitle">{translate key='NewReservation'}</div>
                    <div class="rd-header-actions">
                        <button type="button" class="reservation-icon-button reservation-icon-button-danger d-none" id="rdDeleteBtn" title="{translate key='Delete'}" aria-label="{translate key='Delete'}">
                            <i class="bi bi-trash"></i>
                        </button>
                        <button type="button" class="reservation-icon-button" id="rdCloseBtn" title="{translate key='Cancel'}" aria-label="{translate key='Cancel'}">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                <div class="rd-body" id="rdBody">
                    <div class="rd-skeleton d-none" id="rdSkeleton">
                        <div class="rd-skeleton-line rd-skeleton-line-lg"></div>
                        <div class="rd-skeleton-line"></div>
                        <div class="rd-skeleton-line"></div>
                        <div class="rd-skeleton-line rd-skeleton-line-sm"></div>
                    </div>
                    <div class="rd-load-error d-none" id="rdLoadError"></div>
                    <div id="rdForm">
                        <div class="reservation-section">
                            <input type="text" class="reservation-field rd-title-input" id="rdTitle" placeholder="{translate key='NewReservation'}">
                        </div>

                        <div class="reservation-section">
                            <div class="reservation-row">
                                <i class="bi bi-calendar3"></i>
                                <input type="date" class="reservation-field" id="rdStartDate">
                                <select class="reservation-select" id="rdStartTime"></select>
                            </div>
                            <div class="reservation-row">
                                <i class="bi bi-calendar3 rd-row-spacer-icon"></i>
                                <input type="date" class="reservation-field" id="rdEndDate">
                                <select class="reservation-select" id="rdEndTime"></select>
                            </div>
                            <div class="rd-time-error d-none" id="rdTimeError"></div>
                        </div>

                        <div class="reservation-section d-none" id="rdRecurringSummary">
                            <div class="reservation-row"><i class="bi bi-arrow-repeat"></i><span id="rdRecurringSummaryText">{translate key='RecurringSummary'}</span></div>
                        </div>

                        <div class="reservation-section">
                            <div class="reservation-row">
                                <i class="bi bi-geo-alt"></i>
                                <div class="rd-category-tabs" id="rdCategoryTabs">
                                    <button type="button" class="rd-category-tab active" data-category="physical">{translate key='PhysicalRooms'}</button>
                                    <button type="button" class="rd-category-tab" data-category="video">{translate key='VideoConferenceResources'}</button>
                                </div>
                            </div>
                            <div class="reservation-row">
                                <i class="bi bi-door-open rd-row-spacer-icon"></i>
                                <select class="reservation-select" id="rdResource"></select>
                            </div>
                        </div>

                        <div class="reservation-section d-none" id="rdParticipantsSummary">
                            <div class="reservation-row"><i class="bi bi-people"></i><span id="rdParticipantsSummaryText"></span></div>
                        </div>

                        <div class="reservation-section">
                            <div class="reservation-row reservation-row-top">
                                <i class="bi bi-text-left"></i>
                                <textarea class="reservation-field" id="rdDescription" placeholder="{translate key='Description'}" rows="3"></textarea>
                            </div>
                        </div>

                        <div class="reservation-section d-none" id="rdRemindersSummary">
                            <div class="reservation-row"><i class="bi bi-bell"></i><span id="rdRemindersSummaryText">{translate key='RemindersSummary'}</span></div>
                        </div>
                    </div>
                    <div class="rd-save-error d-none" id="rdSaveError"></div>
                </div>
                <div class="rd-footer">
                    <button type="button" class="del-btn-text d-none" id="rdDeleteFooterBtn">{translate key='Delete'}</button>
                    <div class="rd-footer-actions">
                        <button type="button" class="reservation-btn-text" id="rdCancelBtn" data-bs-dismiss="modal">{translate key='Cancel'}</button>
                        <button type="button" class="reservation-primary-button" id="rdSaveBtn">
                            <span class="spinner-border spinner-border-sm rd-save-spinner d-none" aria-hidden="true"></span>
                            <span id="rdSaveLabel">{translate key='Create'}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {* Recurring-reservation update scope — appears after "儲存" when editing a recurring
       reservation, same three SeriesUpdateScope options the delete dialog offers. *}
    <div class="modal fade" id="reservationUpdateScopeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content del-dialog">
                <button type="button" class="del-close-btn" data-bs-dismiss="modal" aria-label="{translate key='Cancel'}">
                    <i class="bi bi-x-lg"></i>
                </button>
                <div class="del-body">
                    <div class="del-title">{translate key='ApplyUpdateTo'}</div>
                    <div class="del-scope">
                        <label class="del-radio">
                            <input type="radio" name="rdUpdateScope" value="this">
                            <span>{translate key='ThisInstance'}</span>
                        </label>
                        <label class="del-radio">
                            <input type="radio" name="rdUpdateScope" value="future">
                            <span>{translate key='FutureInstances'}</span>
                        </label>
                        <label class="del-radio">
                            <input type="radio" name="rdUpdateScope" value="full" checked>
                            <span>{translate key='AllInstances'}</span>
                        </label>
                    </div>
                </div>
                <div class="del-footer">
                    <button type="button" class="del-btn-text" data-bs-dismiss="modal">{translate key='Cancel'}</button>
                    <button type="button" class="reservation-primary-button" id="rdUpdateScopeConfirmBtn">
                        <span class="spinner-border spinner-border-sm rd-save-spinner d-none" aria-hidden="true"></span>
                        <span>{translate key='Update'}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

{include file="javascript-includes.tpl"}
{vendor_js src="fullcalendar/6.1.20/js/index.global.min.js"}
{vendor_js src="fullcalendar/6.1.20/js/locales-all.global.min.js"}
<script type="text/javascript">
    window.overviewFullCalendarOpts = {
        reservationUrl: '{$ScriptUrl}/{Pages::RESERVATION}',
        eventsUrl: '{$ScriptUrl}/ajax/overview_fullcalendar_events.php',
        saveUrl: '{$ScriptUrl}/ajax/reservation_save.php',
        reservationDataUrl: '{$ScriptUrl}/ajax/overview_fullcalendar_reservation.php',
        {* Deliberately NOT ajax/reservation_update.php — see OverviewFullCalendarReservationUpdatePresenter's
           doc comment for why the Drawer's basic-field edit needs its own narrower endpoint. *}
        reservationUpdateUrl: '{$ScriptUrl}/ajax/overview_fullcalendar_reservation_update.php',
        {* QueryStringKeys::RESPONSE_TYPE is 'rs', not 'response_type' — that's what actually
           routes DeleteReservationPageFactory to ReservationDeleteJsonPage (clean JSON) instead
           of the default HTML-fragment ReservationDeletePage. *}
        deleteUrl: '{$ScriptUrl}/ajax/reservation_delete.php?{QueryStringKeys::RESPONSE_TYPE}=json',
        csrfToken: '{$CSRFToken|escape:'javascript'}',
        currentUserId: {$UserId},
        timezone: '{$Timezone|escape:'javascript'}',
        {* Web/scripts/overview_fullcalendar.js is served as a static .js file — Smarty never
           processes it, so any {translate} tag written directly inside it would reach the
           browser as literal text. This is the bridge: translate here, reference via
           opts.translations.* in the JS. *}
        translations: {
            editReservation: '{translate key='EditReservation'|escape:'javascript'}',
            newReservation: '{translate key='NewReservation'|escape:'javascript'}',
            update: '{translate key='Update'|escape:'javascript'}',
            create: '{translate key='Create'|escape:'javascript'}',
            loadReservationFailed: '{translate key='LoadReservationFailed'|escape:'javascript'}',
            networkErrorRetry: '{translate key='NetworkErrorRetry'|escape:'javascript'}',
            unsavedChangesPrompt: '{translate key='UnsavedChangesPrompt'|escape:'javascript'}',
            endBeforeStart: '{translate key='EndDateBeforeStartDate'|escape:'javascript'}',
            reservationChanged: '{translate key='ReservationChanged'|escape:'javascript'}',
            reloadLatest: '{translate key='ReloadLatest'|escape:'javascript'}',
            minutes: '{translate key='minutes'|escape:'javascript'}',
            hours: '{translate key='hours'|escape:'javascript'}',
            days: '{translate key='days'|escape:'javascript'}'
        },
        resourceNames: {
            {foreach from=$PhysicalResources item=resource name=physicalNames}
                "{$resource->GetId()}": "{$resource->GetName()|escape:'javascript'}"{if !$smarty.foreach.physicalNames.last},{/if}
            {/foreach}
            {if count($PhysicalResources) > 0 && count($VideoResources) > 0},{/if}
            {foreach from=$VideoResources item=resource name=videoNames}
                "{$resource->GetId()}": "{$resource->GetName()|escape:'javascript'}"{if !$smarty.foreach.videoNames.last},{/if}
            {/foreach}
        },
        resourceGroups: {
            physical: [
                {foreach from=$PhysicalResources item=resource name=physicalIds}
                    {ldelim}id: "{$resource->GetId()}", name: "{$resource->GetName()|escape:'javascript'}"{rdelim}{if !$smarty.foreach.physicalIds.last},{/if}
                {/foreach}
            ],
            video: [
                {foreach from=$VideoResources item=resource name=videoIds}
                    {ldelim}id: "{$resource->GetId()}", name: "{$resource->GetName()|escape:'javascript'}"{rdelim}{if !$smarty.foreach.videoIds.last},{/if}
                {/foreach}
            ]
        }
    };
</script>
{jsfile src="overview_fullcalendar.js"}

{include file='globalfooter.tpl'}
