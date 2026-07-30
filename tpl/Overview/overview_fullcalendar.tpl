{* Prototype template — evaluates FullCalendar Standard against tpl/Overview/overview.tpl's
   hand-rolled week grid. Deliberately does not touch overview.tpl; this is a fully separate
   template that happens to {include} the same shared globalheader.tpl so the left sidebar
   (including the resource checklist) looks and behaves identically. *}
{include file='globalheader.tpl' cssFiles='css/overview_fullcalendar.css'}

<div class="shell" id="page-overview-fc">

    {* Three-column toolbar (left/center/right each `1fr`, see overview_fullcalendar.css) —
       the center date-nav group is only ever as wide as its own content and sits on the
       toolbar's true horizontal center regardless of how wide the resource picker (left) or
       the mine-only toggle + view picker (right) are, rather than just being centered
       *between* its neighbors the way a plain flex row would. *}
    <div class="cal-toolbar cal-toolbar-3col">
        <div class="toolbar-col toolbar-col-left">
            {* Physical/video resource type and resource search are both facets of the same
               one decision — "which resource is the calendar showing right now" — so they
               live together in one control instead of separate toolbar widgets (the old
               top-level physical/video tabs + the sidebar's own duplicate resource checklist,
               both removed). Always exactly one specific resource is selected (no "全部資源"
               option — dropped per explicit request, this page shows one room's calendar at
               a time); the default is whichever resource's name matches Shanghai (see
               defaultShanghaiResourceId() in overview_fullcalendar.js), falling back to the
               first physical resource if no Shanghai room exists. overview_fullcalendar.js
               drives all of it: opens/closes the panel, filters the list as you type, and
               sets the trigger's label/dot/checkmark — see setSelectedResource() there. *}
            <div class="resource-picker" id="resourcePicker">
                <button type="button" class="ghost-btn resource-picker-trigger" id="resourcePickerTrigger"
                    aria-haspopup="listbox" aria-expanded="false">
                    <span class="resource-picker-dot" id="resourcePickerDot" style="display:none;"></span>
                    <span class="resource-picker-label" id="resourcePickerLabel"></span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="resource-picker-panel" id="resourcePickerPanel">
                    <div class="resource-picker-search">
                        <i class="bi bi-search"></i>
                        <input type="text" id="resourcePickerSearch" placeholder="{translate key='SearchResources'}" autocomplete="off">
                    </div>
                    <div class="resource-picker-list" id="resourcePickerList">
                        {if count($PhysicalResources) > 0}
                            <div class="resource-picker-section-label" data-section="physical">{translate key='PhysicalRooms'}</div>
                            {foreach from=$PhysicalResources item=resource}
                                <button type="button" class="resource-picker-item" data-resource-id="{$resource->GetId()}" data-section="physical">
                                    <span class="resource-picker-check"><i class="bi bi-check-lg"></i></span>
                                    <span class="resource-picker-dot" style="--dot-color:{$ResourceColors[$resource->GetId()].bg};"></span>
                                    <span class="resource-picker-item-label">{$resource->GetName()|escape}</span>
                                </button>
                            {/foreach}
                        {/if}
                        {if count($VideoResources) > 0}
                            <div class="resource-picker-section-label" data-section="video">{translate key='VideoConferenceResources'}</div>
                            {foreach from=$VideoResources item=resource}
                                <button type="button" class="resource-picker-item" data-resource-id="{$resource->GetId()}" data-section="video">
                                    <span class="resource-picker-check"><i class="bi bi-check-lg"></i></span>
                                    <span class="resource-picker-dot" style="--dot-color:{$ResourceColors[$resource->GetId()].bg};"></span>
                                    <span class="resource-picker-item-label">{$resource->GetName()|escape}</span>
                                </button>
                            {/foreach}
                        {/if}
                        <div class="resource-picker-empty d-none" id="resourcePickerEmpty">{translate key='NoMatchesFound'}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="toolbar-col toolbar-col-center">
            <a class="ghost-btn today-btn" id="fcTodayBtn" href="#">{translate key='Today'}</a>
            <div class="arrows">
                <a class="ghost-btn nav" id="fcPrevBtn" href="#" aria-label="Previous"><i class="bi bi-chevron-left"></i></a>
                <a class="ghost-btn nav" id="fcNextBtn" href="#" aria-label="Next"><i class="bi bi-chevron-right"></i></a>
            </div>
            <span class="month-label" id="fcMonthLabel"></span>
        </div>

        <div class="toolbar-col toolbar-col-right">
            <label class="mine-only-toggle" for="fcMineOnlyToggle">
                <span class="mine-only-label">{translate key='ReservationScopeMine'}</span>
                <span class="toggle-switch">
                    <input type="checkbox" id="fcMineOnlyToggle">
                    <span class="toggle-track"><span class="toggle-thumb"></span></span>
                </span>
            </label>
            <div class="view-picker" id="viewPicker">
                <button type="button" class="ghost-btn view-picker-trigger" id="viewPickerTrigger"
                    aria-haspopup="listbox" aria-expanded="false">
                    <span id="viewPickerLabel">{translate key='Week'}</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="view-picker-panel" id="viewPickerPanel">
                    <button type="button" class="view-picker-item" data-view="timeGridDay">{translate key='Day'}</button>
                    <button type="button" class="view-picker-item active" data-view="timeGridWeek">{translate key='Week'}</button>
                    <button type="button" class="view-picker-item" data-view="dayGridMonth">{translate key='Month'}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="fc-frame">
        <div id="fullcalendarRoot"></div>
        {* Custom now-indicator dot — FullCalendar's own default arrow lives inside the axis
           column's own now-indicator container (a different DOM subtree than the day-column
           that holds the actual red line), so anchoring it there with a fixed CSS offset only
           happened to look right at one specific width; it drifts under resize/scroll/whenever
           FullCalendar re-renders the grid. This sits as a sibling of #fullcalendarRoot inside
           .fc-frame (position: relative) and gets repositioned in JS from the real, current
           geometry of the now-indicator line's own left edge every time it could have moved,
           so the dot always sits at the actual start of the red line. *}
        <div id="fcNowDot" class="d-none"></div>
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
                                <input type="date" class="qc-date-input" id="qcStartDate">
                                <div class="qc-time-picker" id="qcStartPicker">
                                    <button type="button" class="qc-time-pill" id="qcStartPill" aria-haspopup="listbox" aria-expanded="false"></button>
                                    <div class="qc-time-dropdown" id="qcStartDropdown" role="listbox"></div>
                                </div>
                                <span class="qc-time-dash">–</span>
                                <input type="date" class="qc-date-input" id="qcEndDate">
                                <div class="qc-time-picker" id="qcEndPicker">
                                    <button type="button" class="qc-time-pill" id="qcEndPill" aria-haspopup="listbox" aria-expanded="false"></button>
                                    <div class="qc-time-dropdown" id="qcEndDropdown" role="listbox"></div>
                                </div>
                            </div>
                            <div class="qc-time-error d-none" id="qcTimeError"></div>
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

                        {* Repeat rule — reuses the exact same backend field names/values the classic
                           form's RecurrenceControl already submits (FormKeys::REPEAT_OPTIONS etc, see
                           RepeatType/RepeatMonthlyType in Domain/RepeatOptions.php), so
                           RepeatOptionsFactory needs no changes. Built lightweight here rather than
                           reusing RecurrenceDiv.tpl/recurrence.js directly, since those are wired to
                           the classic form's own field ids. *}
                        <div class="reservation-section" id="rdRepeatSection">
                            <div class="reservation-row">
                                <i class="bi bi-arrow-repeat"></i>
                                <select class="reservation-select" id="rdRepeatType">
                                    <option value="none">{translate key='DoesNotRepeat'}</option>
                                    <option value="daily">{translate key='Daily'}</option>
                                    <option value="weekly">{translate key='Weekly'}</option>
                                    <option value="monthly">{translate key='Monthly'}</option>
                                    <option value="custom">{translate key='Custom'}</option>
                                </select>
                            </div>
                            <div class="reservation-row d-none" id="rdRepeatIntervalRow">
                                <i class="bi bi-arrow-repeat rd-row-spacer-icon"></i>
                                <span>{translate key='RepeatEveryPrompt'}</span>
                                <input type="number" class="reservation-field rd-repeat-interval-input" id="rdRepeatInterval" min="1" value="1">
                                <span id="rdRepeatIntervalUnit"></span>
                            </div>
                            <div class="reservation-row d-none" id="rdRepeatWeekdaysRow">
                                <i class="bi bi-arrow-repeat rd-row-spacer-icon"></i>
                                <div class="rd-weekday-picker" id="rdWeekdayPicker">
                                    <button type="button" class="rd-weekday-btn" data-day="0">{translate key='DaySundayAbbr'}</button>
                                    <button type="button" class="rd-weekday-btn" data-day="1">{translate key='DayMondayAbbr'}</button>
                                    <button type="button" class="rd-weekday-btn" data-day="2">{translate key='DayTuesdayAbbr'}</button>
                                    <button type="button" class="rd-weekday-btn" data-day="3">{translate key='DayWednesdayAbbr'}</button>
                                    <button type="button" class="rd-weekday-btn" data-day="4">{translate key='DayThursdayAbbr'}</button>
                                    <button type="button" class="rd-weekday-btn" data-day="5">{translate key='DayFridayAbbr'}</button>
                                    <button type="button" class="rd-weekday-btn" data-day="6">{translate key='DaySaturdayAbbr'}</button>
                                </div>
                            </div>
                            <div class="reservation-row d-none" id="rdRepeatMonthlyTypeRow">
                                <i class="bi bi-arrow-repeat rd-row-spacer-icon"></i>
                                <select class="reservation-select" id="rdRepeatMonthlyType">
                                    <option value="dayOfMonth">{translate key='repeatDayOfMonth'}</option>
                                    <option value="dayOfWeek">{translate key='repeatDayOfWeek'}</option>
                                </select>
                            </div>
                            <div class="reservation-row d-none" id="rdRepeatEndRow">
                                <i class="bi bi-arrow-repeat rd-row-spacer-icon"></i>
                                <span>{translate key='RepeatUntilPrompt'}</span>
                                <input type="date" class="reservation-field" id="rdRepeatEndDate">
                            </div>
                            <div class="reservation-row reservation-row-top d-none" id="rdRepeatCustomRow">
                                <i class="bi bi-arrow-repeat rd-row-spacer-icon"></i>
                                <div class="rd-custom-dates" id="rdRepeatCustom">
                                    <div class="rd-custom-dates-input">
                                        <input type="date" class="reservation-field" id="rdRepeatCustomDateInput">
                                        <button type="button" class="reservation-btn-text" id="rdRepeatCustomAddBtn">{translate key='AddDate'}</button>
                                    </div>
                                    <div class="rd-chip-list" id="rdRepeatCustomList"></div>
                                </div>
                            </div>
                            <div class="rd-time-error d-none" id="rdRepeatError"></div>
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

                        {* Additional resources / accessories / allow-participation are read-only
                           here (not editable via the Drawer yet — see COASIA-CHANGES.md P0-1
                           scope note); management still goes through "更多選項" into the classic
                           form. *}
                        <div class="reservation-section d-none" id="rdAdditionalResourcesSummary">
                            <div class="reservation-row reservation-row-top">
                                <i class="bi bi-door-open"></i>
                                <div class="rd-chip-list" id="rdAdditionalResourcesList"></div>
                            </div>
                        </div>

                        <div class="reservation-section d-none" id="rdAccessoriesSummary">
                            <div class="reservation-row reservation-row-top">
                                <i class="bi bi-tools"></i>
                                <div class="rd-chip-list" id="rdAccessoriesList"></div>
                            </div>
                        </div>

                        <div class="reservation-section d-none" id="rdAllowParticipationSummary">
                            <div class="reservation-row"><i class="bi bi-people-fill"></i><span id="rdAllowParticipationText">{translate key='AllowParticipantsToJoin'}</span></div>
                        </div>

                        <div class="reservation-section d-none" id="rdParticipantsSummary">
                            <div class="reservation-row reservation-row-top">
                                <i class="bi bi-people"></i>
                                <div class="rd-chip-list" id="rdParticipantsList"></div>
                            </div>
                        </div>

                        <div class="reservation-section d-none" id="rdGuestsSummary">
                            <div class="reservation-row reservation-row-top">
                                <i class="bi bi-person-plus"></i>
                                <div class="rd-chip-list" id="rdGuestsList"></div>
                            </div>
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

                        <div class="reservation-section d-none" id="rdAttributesSummary">
                            <div class="reservation-row reservation-row-top">
                                <i class="bi bi-tags"></i>
                                <div class="rd-attribute-list" id="rdAttributesList"></div>
                            </div>
                        </div>

                        <div class="reservation-section d-none" id="rdAttachmentsSummary">
                            <div class="reservation-row reservation-row-top">
                                <i class="bi bi-paperclip"></i>
                                <div class="rd-chip-list" id="rdAttachmentsList"></div>
                            </div>
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
            repeatEndDateRequired: '{translate key='RepeatEndDateRequired'|escape:'javascript'}',
            repeatWeekdayRequired: '{translate key='RepeatWeekdayRequired'|escape:'javascript'}',
            repeatCustomDateRequired: '{translate key='RepeatCustomDateRequired'|escape:'javascript'}',
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
        resourceColors: {
            {foreach from=$PhysicalResources item=resource name=physicalColors}
                "{$resource->GetId()}": "{$ResourceColors[$resource->GetId()].bg|escape:'javascript'}"{if !$smarty.foreach.physicalColors.last},{/if}
            {/foreach}
            {if count($PhysicalResources) > 0 && count($VideoResources) > 0},{/if}
            {foreach from=$VideoResources item=resource name=videoColors}
                "{$resource->GetId()}": "{$ResourceColors[$resource->GetId()].bg|escape:'javascript'}"{if !$smarty.foreach.videoColors.last},{/if}
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
