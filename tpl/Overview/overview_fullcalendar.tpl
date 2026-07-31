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

    {* Every row (including the title, whose icon column is deliberately empty) shares the
       same .qc-grid-row: 32px icon column + content column — that's what keeps the title
       underline, date/time, resource picker, participants link, and description textarea all
       starting at the exact same left edge instead of the title (no icon) drifting 20px+32px
       left of everything below it. *}
    <div class="modal fade" id="quickCreateModal" tabindex="-1" aria-hidden="true" aria-labelledby="quickCreateModalHeading">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <button type="button" class="btn-close qc-close" data-bs-dismiss="modal" aria-label="{translate key='Cancel'}"></button>
                <div class="modal-body">
                    {* The dialog's own accessible name (aria-labelledby above) now points here
                       instead of the hidden input label below — a real heading is a more
                       correct label for the DIALOG than an input's own hidden <label> ever was. *}
                    <h2 class="qc-heading" id="quickCreateModalHeading">{translate key='NewReservation'}</h2>
                    <p class="qc-subheading">{translate key='QuickCreateSubheading'}</p>

                    <label for="qcTitle" class="visually-hidden" id="qcTitleLabel">{translate key='NewReservation'}</label>

                    <div class="qc-grid-row qc-title-row">
                        <div class="qc-icon"><i class="bi bi-card-heading"></i></div>
                        <input type="text" class="qc-title" id="qcTitle" placeholder="{translate key='AddTitle'}" autocomplete="off">
                    </div>

                    {* Reservations can't cross midnight (CoAsia policy — see ResourceCrossDayRule.php,
                       now unconditional for every resource) — one date input covers both start and
                       end, so there's no UI path to even attempt a different end day. The native
                       <input type="date"> stays the actual control (keyboard input, native picker,
                       accessibility) but renders its own text transparent — #qcStartDateDisplay sits
                       on top showing "7月30日（四）" instead of the input's own locale-dependent ISO-ish
                       rendering, and is pointer-events:none so clicks still reach the real input
                       underneath (see overview_fullcalendar.css .qc-date-field). *}
                    <div class="qc-grid-row">
                        <div class="qc-icon"><i class="bi bi-clock"></i></div>
                        <div class="qc-content">
                            <div class="qc-time-edit">
                                <div class="qc-date-field">
                                    <input type="date" class="qc-date-input" id="qcStartDate">
                                    <span class="qc-date-display" id="qcStartDateDisplay"></span>
                                </div>
                                <div class="qc-time-picker" id="qcStartPicker">
                                    <button type="button" class="qc-time-pill" id="qcStartPill" aria-haspopup="listbox" aria-expanded="false"></button>
                                    <div class="qc-time-dropdown" id="qcStartDropdown" role="listbox"></div>
                                </div>
                                <span class="qc-time-dash">－</span>
                                <div class="qc-time-picker" id="qcEndPicker">
                                    <button type="button" class="qc-time-pill" id="qcEndPill" aria-haspopup="listbox" aria-expanded="false"></button>
                                    <div class="qc-time-dropdown" id="qcEndDropdown" role="listbox"></div>
                                </div>
                            </div>
                            <div class="qc-time-error d-none" id="qcTimeError"></div>
                        </div>
                    </div>

                    {* Single searchable/grouped picker — same component the toolbar's own resource
                       picker uses (.resource-picker*, styled in overview_fullcalendar.css under
                       #page-overview-fc, which this modal sits inside), just full-width and with
                       its own qc-prefixed ids so its selection is independent of which resource the
                       calendar itself is currently showing. Replaces the old physical/video tab
                       pair + plain dropdown — category is now just a section heading inside the one
                       dropdown instead of two separate top-level controls. *}
                    <div class="qc-grid-row">
                        <div class="qc-icon"><i class="bi bi-building"></i></div>
                        <div class="qc-content">
                            <div class="resource-picker qc-resource-picker" id="qcResourcePicker">
                                <button type="button" class="ghost-btn resource-picker-trigger" id="qcResourcePickerTrigger"
                                    aria-haspopup="listbox" aria-expanded="false">
                                    <span class="resource-picker-dot" id="qcResourcePickerDot" style="display:none;"></span>
                                    <span class="resource-picker-label" id="qcResourcePickerLabel"></span>
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                                <div class="resource-picker-panel" id="qcResourcePickerPanel">
                                    <div class="resource-picker-search">
                                        <i class="bi bi-search"></i>
                                        <input type="text" id="qcResourcePickerSearch" placeholder="{translate key='SearchResources'}" autocomplete="off">
                                    </div>
                                    <div class="resource-picker-list" id="qcResourcePickerList">
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
                                        <div class="resource-picker-empty d-none" id="qcResourcePickerEmpty">{translate key='NoMatchesFound'}</div>
                                    </div>
                                </div>
                            </div>
                            {* Re-queried via ajax/overview_fullcalendar_availability.php on every
                               date/time/resource change (Web/scripts/overview_fullcalendar.js) —
                               never a hardcoded/static state. *}
                            <div class="qc-availability" id="qcAvailability">
                                <i class="bi bi-check-circle-fill"></i>
                                <span id="qcAvailabilityLabel"></span>
                            </div>
                        </div>
                    </div>

                    {* Participant search + editable chip list, built directly into Quick Create
                       (see COASIA-CHANGES.md §45 — supersedes the earlier §44 "hands off to the
                       Drawer" placeholder). Same shared picker (createParticipantPicker() in
                       overview_fullcalendar.js) and same dedicated search endpoint as the
                       Drawer's own participant editor below. *}
                    <div class="qc-grid-row qc-grid-row-top">
                        <div class="qc-icon"><i class="bi bi-people"></i></div>
                        <div class="qc-content">
                            <div class="participant-search" id="qcParticipantSearch">
                                <div class="participant-search-input-wrap">
                                    <i class="bi bi-search"></i>
                                    <input type="text" id="qcParticipantSearchInput" placeholder="{translate key='SearchParticipants'}" autocomplete="off">
                                </div>
                                <div class="participant-search-results d-none" id="qcParticipantSearchResults"></div>
                            </div>
                            <div class="rd-chip-list qc-participant-chip-list d-none" id="qcParticipantChipList"></div>
                        </div>
                    </div>

                    <div class="qc-grid-row qc-grid-row-top">
                        <div class="qc-icon"><i class="bi bi-text-left"></i></div>
                        <div class="qc-content">
                            <textarea id="qcDescription" class="qc-description" rows="2" placeholder="{translate key='AddDescription'}"></textarea>
                        </div>
                    </div>

                    <div id="qcError" class="alert alert-danger py-2 px-3 small d-none"></div>
                </div>
                <div class="modal-footer">
                    <a href="#" id="qcMoreOptions" class="link-primary me-auto">{translate key='MoreOptions'}</a>
                    <button type="button" class="reservation-btn-text" id="qcCancel" data-bs-dismiss="modal">{translate key='CancelAction'}</button>
                    <button type="button" class="btn btn-primary" id="qcSave">{translate key='CreateReservation'}</button>
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

                        {* Reservations can't cross midnight (CoAsia policy — see ResourceCrossDayRule.php,
                           now unconditional for every resource) — one date input covers both start and
                           end, so there's no UI path to even attempt a different end day. *}
                        {* Same transparent-input-plus-overlay technique as Quick Create's #qcStartDate
                           (see .qc-date-field's own comment) — a real <input type="date"> stays the
                           actual control, #rdDateDisplay shows "7月30日（四）" instead of its native
                           locale-dependent rendering. *}
                        <div class="reservation-section">
                            <div class="reservation-row">
                                <i class="bi bi-calendar3"></i>
                                <div class="rd-date-field">
                                    <input type="date" class="reservation-field" id="rdDate">
                                    <span class="rd-date-display" id="rdDateDisplay"></span>
                                </div>
                            </div>
                            <div class="reservation-row">
                                <i class="bi bi-clock rd-row-spacer-icon"></i>
                                <select class="reservation-select" id="rdStartTime"></select>
                                <span class="qc-time-dash">–</span>
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

                        {* Same searchable/grouped resource picker as Quick Create's #qcResourcePicker
                           (see its own comment) — reuses the shared .resource-picker* component rather
                           than the old physical/video tab pair + plain <select>, plus the same
                           "此時段可用" indicator underneath, re-queried from
                           ajax/overview_fullcalendar_availability.php on every date/time/resource
                           change. drawerResourceId (overview_fullcalendar.js) is the actual state —
                           #rdResource below is a hidden field kept in sync purely so
                           buildCreateBody()/buildEditBody() can go on reading it exactly as before. *}
                        <div class="reservation-section">
                            <div class="reservation-row">
                                <i class="bi bi-geo-alt"></i>
                                <div class="reservation-row-fill">
                                    <div class="resource-picker rd-resource-picker" id="rdResourcePicker">
                                        <button type="button" class="ghost-btn resource-picker-trigger" id="rdResourcePickerTrigger"
                                            aria-haspopup="listbox" aria-expanded="false">
                                            <span class="resource-picker-dot" id="rdResourcePickerDot" style="display:none;"></span>
                                            <span class="resource-picker-label" id="rdResourcePickerLabel"></span>
                                            <i class="bi bi-chevron-down"></i>
                                        </button>
                                        <div class="resource-picker-panel" id="rdResourcePickerPanel">
                                            <div class="resource-picker-search">
                                                <i class="bi bi-search"></i>
                                                <input type="text" id="rdResourcePickerSearch" placeholder="{translate key='SearchResources'}" autocomplete="off">
                                            </div>
                                            <div class="resource-picker-list" id="rdResourcePickerList">
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
                                                <div class="resource-picker-empty d-none" id="rdResourcePickerEmpty">{translate key='NoMatchesFound'}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="qc-availability" id="rdAvailability">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <span id="rdAvailabilityLabel"></span>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" id="rdResource">
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

                        {* Participant search + editable chip list — CREATE mode only. Reuses the
                           exact same search-by-name/username/email endpoint the classic
                           reservation form already uses (ajax/autocomplete.php?type=user,
                           Pages/Ajax/AutoCompletePage.php's GetUsers()) and stores real
                           users.user_id values from the response (u.Id), not emails — those
                           get sent as participantList[] in buildCreateBody(). Shown/hidden via
                           JS in openReservationDrawer()/resetDrawerUI(); edit mode keeps using
                           the read-only #rdParticipantsSummary below instead — managing
                           participants on an *existing* reservation is a separate, not-yet-
                           implemented feature, so this editor deliberately doesn't appear there
                           rather than looking editable and silently doing nothing on save. *}
                        <div class="reservation-section d-none" id="rdParticipantsEditor">
                            <div class="reservation-row">
                                <i class="bi bi-people"></i>
                                <div class="reservation-row-fill">
                                    <div class="participant-search" id="rdParticipantSearch">
                                        <div class="participant-search-input-wrap">
                                            <i class="bi bi-search"></i>
                                            <input type="text" id="rdParticipantSearchInput" placeholder="{translate key='SearchParticipants'}" autocomplete="off">
                                        </div>
                                        <div class="participant-search-results d-none" id="rdParticipantSearchResults"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="reservation-row reservation-row-top d-none" id="rdParticipantChipsRow">
                                <i class="bi bi-people rd-row-spacer-icon"></i>
                                <div class="rd-chip-list" id="rdParticipantChipList"></div>
                            </div>
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
        availabilityUrl: '{$ScriptUrl}/ajax/overview_fullcalendar_availability.php',
        {* Dedicated CoAsia endpoint (not the shared ajax/autocomplete.php) — matches Chinese/
           Korean name, English name, username/employee ID, and email (Pages/Ajax/
           OverviewFullCalendarParticipantSearchPage.php), and returns the richer fields the
           search-result rows below display. Only the "term" querystring param is appended at
           call time. *}
        participantSearchUrl: '{$ScriptUrl}/ajax/overview_fullcalendar_participant_search.php?term=',
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
            days: '{translate key='days'|escape:'javascript'}',
            resourceAvailable: '{translate key='ResourceAvailable'|escape:'javascript'}',
            resourceUnavailable: '{translate key='ResourceUnavailable'|escape:'javascript'}',
            noMatchesFound: '{translate key='NoMatchesFound'|escape:'javascript'}'
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
