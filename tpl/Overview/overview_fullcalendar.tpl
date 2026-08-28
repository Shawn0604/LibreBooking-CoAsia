{* Prototype template — evaluates FullCalendar Standard against tpl/Overview/overview.tpl's
   hand-rolled week grid. Deliberately does not touch overview.tpl; this is a fully separate
   template that happens to {include} the same shared globalheader.tpl so the left sidebar
   (including the resource checklist) looks and behaves identically. *}
{include file='globalheader.tpl' cssFiles='css/overview_fullcalendar.css,css/onboarding-tour.css'}

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
               both removed). Always at most one specific resource is selected (no "全部資源"
               option — dropped per explicit request, this page shows one room's calendar at a
               time); which one, on first load, follows a priority chain — the user's own
               "設為預設會議室" pick, then their last-selected room, then their classic default-
               schedule setting, then (for a genuinely first-time user with none of the above)
               Taipei office's own "台北 Taipei" room — see resolveDefaultResourceId() in
               overview_fullcalendar.js and OverviewFullCalendarPresenter::
               ResolveDefaultResourceIds()/ResolveFirstLoginDefaultResourceId(). The four groups below always start collapsed
               regardless of which resource that resolves to — expanding one to see/select a
               room is a separate action from what's currently showing. overview_fullcalendar.js
               drives all of it: opens/closes the panel, expands/collapses each group, filters
               the list as you type, and sets the trigger's label/dot/checkmark — see
               setSelectedResource() there. *}
            <div class="resource-picker" id="resourcePicker">
                <button type="button" class="coasia-control resource-picker-trigger" id="resourcePickerTrigger"
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
                        {* Two-level list: 台北/深圳/上海實體會議室 + 線上會議室 (resource_groups,
                           see database_schema/coasia-resources-regions.sql and
                           OverviewFullCalendarPresenter::PageLoad()) each render as an
                           independently expandable/collapsible section — every physical office
                           has its own set of rooms (some names repeat across offices, e.g. each
                           office has its own "上海 Shanghai" room), and Video Conference stays a
                           single shared section rather than repeating per office. *}
                        {foreach from=$ResourceGroups item=group}
                            <div class="resource-picker-group collapsed" data-group-id="{$group.id}" data-video="{if $group.isVideo}1{else}0{/if}">
                                <button type="button" class="resource-picker-group-toggle" aria-expanded="false">
                                    <i class="bi bi-chevron-down resource-picker-group-chevron"></i>
                                    <span class="resource-picker-group-label">{$group.name|escape}</span>
                                    <span class="resource-picker-group-count">（{$group.resources|@count}）</span>
                                </button>
                                <div class="resource-picker-group-items">
                                    {foreach from=$group.resources item=resource}
                                        <button type="button" class="resource-picker-item" data-resource-id="{$resource->GetId()}" data-group-id="{$group.id}">
                                            <span class="resource-picker-check"><i class="bi bi-check-lg"></i></span>
                                            <span class="resource-picker-dot" style="--dot-color:{$ResourceColors[$resource->GetId()].bg};"></span>
                                            <span class="resource-picker-item-label">{$resource->GetName()|escape}</span>
                                        </button>
                                    {/foreach}
                                </div>
                            </div>
                        {/foreach}
                        <div class="resource-picker-empty d-none" id="resourcePickerEmpty">{translate key='NoMatchesFound'}</div>
                    </div>
                    {* Pinned below the scrolling list (a sibling of #resourcePickerList, not
                       inside it) — sets/clears the CURRENTLY SELECTED resource (selectedResourceId
                       in overview_fullcalendar.js) as this user's permanent default, replacing the
                       old per-row star (setResourcePickerDefault()/updateResourcePickerDefaultButton()
                       there). Deliberately still independent of "which resource the calendar is
                       currently showing" only in the sense that clicking a room in the list above
                       never calls this on its own — it's the only control that persists a default;
                       switching rooms to look at them is not the same action. Toolbar-only, same as
                       the star it replaces — Quick Create's own copy of this picker (below) is about
                       choosing a resource for the reservation being drafted, unrelated to a personal
                       default, so it doesn't get this control either. *}
                    <div class="resource-picker-footer">
                        <button type="button" class="resource-picker-set-default" id="resourcePickerSetDefault" aria-pressed="false">
                            <i class="bi bi-star"></i>
                            <span id="resourcePickerSetDefaultLabel">{translate key='SetAsDefaultResource'}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="toolbar-col toolbar-col-center">
            {* Prev/month/next as one bordered group instead of three separate pill buttons —
               fixed-width month label (CSS) keeps the group's overall width constant as the
               text itself changes length between months, and the vertical dividers plus
               per-arrow (not whole-group) hover are what "箭頭不要再有獨立外框" calls for.
               Still pure date-nav via the FullCalendar API (overview_fullcalendar.js) — only
               the markup/styling around fcPrevBtn/fcNextBtn/fcMonthLabel changed. *}
            <div class="date-switcher" id="dateSwitcher">
                <a class="date-switcher-arrow" id="fcPrevBtn" href="#" aria-label="Previous"><i class="bi bi-chevron-left"></i></a>
                <span class="date-switcher-divider"></span>
                <span class="month-label" id="fcMonthLabel"></span>
                <span class="date-switcher-divider"></span>
                <a class="date-switcher-arrow" id="fcNextBtn" href="#" aria-label="Next"><i class="bi bi-chevron-right"></i></a>
            </div>
        </div>

        <div class="toolbar-col toolbar-col-right">
            {* Icon-only "back to today" — 40x40, same shared control shape as the two dropdown
               triggers beside it. Click handler / .disabled-when-already-on-today logic in
               overview_fullcalendar.js is untouched — only styling changed. Position is never
               removed from the layout even while disabled, so the right-hand group never shifts
               width when this week is already showing. *}
            <button type="button" class="coasia-control today-btn" id="fcTodayBtn"
                title="{translate key='BackToToday'}" aria-label="{translate key='BackToToday'}">
                <i class="bi bi-calendar-check"></i>
            </button>

            {* Reservation scope (全體／只看我的) — same two-state filter as before, dropdown
               matching the view picker's own trigger/panel pattern (both now fixed 104px so
               the two read as one consistent pair). overview_fullcalendar.js reads the selected
               data-scope and sets the same `mineOnly` flag + calendar.refetchEvents() the old
               checkbox's change handler did — the actual scope-filtering behavior is unchanged. *}
            <div class="scope-picker" id="scopePicker">
                <button type="button" class="coasia-control scope-picker-trigger" id="scopePickerTrigger"
                    aria-haspopup="listbox" aria-expanded="false">
                    <span class="scope-picker-label" id="scopePickerLabel">{translate key='ReservationScopeAll'}</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="scope-picker-panel" id="scopePickerPanel">
                    <button type="button" class="view-picker-item active" data-scope="all">{translate key='ReservationScopeAll'}</button>
                    <button type="button" class="view-picker-item" data-scope="mine">{translate key='ReservationScopeMine'}</button>
                </div>
            </div>

            <div class="view-picker" id="viewPicker">
                <button type="button" class="coasia-control view-picker-trigger" id="viewPickerTrigger"
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

            {* Same 40x40 shared control shape as fcTodayBtn -- restarts the onboarding tour
               on demand (Web/scripts/onboarding-tour.js). This is a shortcut alongside the
               sidebar "Help" menu's own replay link, not a replacement for it, since this
               button only exists on this page. *}
            <button type="button" class="coasia-control tour-help-btn" id="tourHelpBtn"
                title="{translate key='TourReplayMenuItem'}" aria-label="{translate key='TourReplayMenuItem'}">
                <i class="bi bi-question-circle"></i>
            </button>
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
                <button type="button" class="qc-close" data-bs-dismiss="modal" aria-label="{translate key='Close'}" title="{translate key='Close'}">×</button>
                <div class="modal-body">
                    {* The dialog's own accessible name (aria-labelledby above) now points here
                       instead of the hidden input label below — a real heading is a more
                       correct label for the DIALOG than an input's own hidden <label> ever was. *}
                    <h2 class="qc-heading" id="quickCreateModalHeading">{translate key='NewReservation'}</h2>
                    <p class="qc-subheading">{translate key='QuickCreateSubheading'}</p>

                    <label for="qcTitle" class="visually-hidden" id="qcTitleLabel">{translate key='NewReservation'}</label>

                    <div class="qc-grid-row qc-title-row">
                        <div class="qc-icon"><i class="bi bi-card-heading"></i></div>
                        <input type="text" class="qc-title" id="qcTitle"
                            placeholder="{translate key='AddTitle'}" autocomplete="off"
                            {if $TitleRequired}required aria-required="true"{/if}>
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
                                        {foreach from=$ResourceGroups item=group}
                                            <div class="resource-picker-group collapsed" data-group-id="{$group.id}" data-video="{if $group.isVideo}1{else}0{/if}">
                                                <button type="button" class="resource-picker-group-toggle" aria-expanded="false">
                                                    <i class="bi bi-chevron-down resource-picker-group-chevron"></i>
                                                    <span class="resource-picker-group-label">{$group.name|escape}</span>
                                                    <span class="resource-picker-group-count">（{$group.resources|@count}）</span>
                                                </button>
                                                <div class="resource-picker-group-items">
                                                    {foreach from=$group.resources item=resource}
                                                        <button type="button" class="resource-picker-item" data-resource-id="{$resource->GetId()}" data-group-id="{$group.id}">
                                                            <span class="resource-picker-check"><i class="bi bi-check-lg"></i></span>
                                                            <span class="resource-picker-dot" style="--dot-color:{$ResourceColors[$resource->GetId()].bg};"></span>
                                                            <span class="resource-picker-item-label">{$resource->GetName()|escape}</span>
                                                        </button>
                                                    {/foreach}
                                                </div>
                                            </div>
                                        {/foreach}
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
                       Drawer's own participant editor below. Required participants only —
                       optional participants ("選擇性參與者", non-required attendees) are
                       deliberately NOT offered here; Quick Create is the fast path, and anyone
                       who needs that goes through "更多選項" to the full reservation form
                       (tpl/Reservation/participation.tpl), which already has it. *}
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

                    {if $RemindersEnabled}
                    <div class="qc-grid-row qc-grid-row-top">
                        <div class="qc-icon"><i class="bi bi-bell"></i></div>
                        <div class="qc-content">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="qcReminderEnabled"
                                    {if $DefaultStartReminderChecked}checked{/if}>
                                <label class="form-check-label" for="qcReminderEnabled">{translate key='SendReminder'}</label>
                            </div>
                            <div class="d-flex align-items-center gap-2 mt-1" id="qcReminderFields">
                                <input type="number" min="1" max="999" id="qcReminderTime"
                                    class="form-control form-control-sm" style="width:5rem"
                                    value="{$DefaultStartReminderValue}">
                                <select id="qcReminderInterval" class="form-select form-select-sm" style="width:auto">
                                    <option value="minutes" {if $DefaultStartReminderInterval eq 'minutes'}selected{/if}>{translate key='minutes'}</option>
                                    <option value="hours" {if $DefaultStartReminderInterval eq 'hours'}selected{/if}>{translate key='hours'}</option>
                                    <option value="days" {if $DefaultStartReminderInterval eq 'days'}selected{/if}>{translate key='days'}</option>
                                </select>
                                <span class="text-muted small">{translate key='ReminderBeforeStart'}</span>
                            </div>
                        </div>
                    </div>
                    {/if}

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
        {* Tier 1 of the resource picker's default-resource priority (the "設為預設" star) —
           see OverviewFullCalendarPresenter::ResolveDefaultResourceIds(). *}
        setDefaultResourceUrl: '{$ScriptUrl}/ajax/overview_fullcalendar_set_default_resource.php',
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
        currentUserEmail: '{$UserEmail|escape:'javascript'}',
        allowGuestParticipation: {if $AllowGuestParticipation}true{else}false{/if},
        titleRequired: {if $TitleRequired}true{else}false{/if},
        remindersEnabled: {if $RemindersEnabled}true{else}false{/if},
        defaultStartReminder: {
            value: {$DefaultStartReminderValue|intval},
            interval: '{$DefaultStartReminderInterval|escape:'javascript'}',
            checked: {if $DefaultStartReminderChecked}true{else}false{/if}
        },
        timezone: '{$Timezone|escape:'javascript'}',
        {* Web/scripts/overview_fullcalendar.js is served as a static .js file — Smarty never
           processes it, so any {translate} tag written directly inside it would reach the
           browser as literal text. This is the bridge: translate here, reference via
           opts.translations.* in the JS. *}
        {* {translate_js} (not {translate key=...|escape:'javascript'}) -- that escape chains
           onto the 'key' attribute's own literal value, not this tag's translated output, so
           it was a silent no-op here; see SmartyPage::SmartyTranslateJs()'s doc comment. *}
        translations: {
            editReservation: {translate_js key='EditReservation'},
            newReservation: {translate_js key='NewReservation'},
            update: {translate_js key='Update'},
            create: {translate_js key='Create'},
            loadReservationFailed: {translate_js key='LoadReservationFailed'},
            networkErrorRetry: {translate_js key='NetworkErrorRetry'},
            unsavedChangesPrompt: {translate_js key='UnsavedChangesPrompt'},
            endBeforeStart: {translate_js key='EndDateBeforeStartDate'},
            reservationChanged: {translate_js key='ReservationChanged'},
            reloadLatest: {translate_js key='ReloadLatest'},
            repeatEndDateRequired: {translate_js key='RepeatEndDateRequired'},
            repeatWeekdayRequired: {translate_js key='RepeatWeekdayRequired'},
            repeatCustomDateRequired: {translate_js key='RepeatCustomDateRequired'},
            minutes: {translate_js key='minutes'},
            hours: {translate_js key='hours'},
            days: {translate_js key='days'},
            resourceAvailable: {translate_js key='ResourceAvailable'},
            resourceUnavailable: {translate_js key='ResourceUnavailable'},
            noMatchesFound: {translate_js key='NoMatchesFound'},
            inviteExternalEmail: {translate_js key='InviteExternalEmail'},
            externalParticipantBadge: {translate_js key='ExternalParticipantBadge'},
            selectResourcePlaceholder: {translate_js key='SelectResourcePlaceholder'},
            setAsDefaultResource: {translate_js key='SetAsDefaultResource'},
            removeDefaultResource: {translate_js key='RemoveDefaultResource'},
            currentDefaultResource: {translate_js key='CurrentDefaultResource'},
            titleRequired: {translate_js key='TitleRequiredRule'}
        },
        {* Priority tiers 1, 3 and 4 of the resource picker's default-resource resolution — see
           resolveDefaultResourceId() in overview_fullcalendar.js and
           OverviewFullCalendarPresenter::ResolveDefaultResourceIds()/
           ResolveFirstLoginDefaultResourceId(). Tier 2 (last selected) lives entirely in
           localStorage. *}
        explicitDefaultResourceId: {if $ExplicitDefaultResourceId}"{$ExplicitDefaultResourceId|escape:'javascript'}"{else}null{/if},
        systemDefaultResourceId: {if $SystemDefaultResourceId}"{$SystemDefaultResourceId|escape:'javascript'}"{else}null{/if},
        {* Tier 4: a genuinely first-time user (no explicit pin, no browser history, no classic
           default-schedule setting) lands on Taipei office's own "台北 Taipei" room instead of
           the "請選擇會議室" placeholder — see ResolveFirstLoginDefaultResourceId()'s own doc
           comment for why this is resolved by name+location match server-side rather than a
           hardcoded resource id here. *}
        firstLoginDefaultResourceId: {if $FirstLoginDefaultResourceId}"{$FirstLoginDefaultResourceId|escape:'javascript'}"{else}null{/if},
        resourceNames: {
            {foreach from=$ResourceGroups item=group name=groups}
                {foreach from=$group.resources item=resource name=groupResources}
                    "{$resource->GetId()}": "{$resource->GetName()|escape:'javascript'}"{if !($smarty.foreach.groups.last && $smarty.foreach.groupResources.last)},{/if}
                {/foreach}
            {/foreach}
        },
        resourceColors: {
            {foreach from=$ResourceGroups item=group name=groups}
                {foreach from=$group.resources item=resource name=groupResources}
                    "{$resource->GetId()}": "{$ResourceColors[$resource->GetId()].bg|escape:'javascript'}"{if !($smarty.foreach.groups.last && $smarty.foreach.groupResources.last)},{/if}
                {/foreach}
            {/foreach}
        },
        {* Per-resource region label (resources.location — 台北/深圳/上海/跨地區, see
           Domain/Values/ResourceLocation.php and database_schema/coasia-resources-location.sql)
           for the picker trigger's "地區 · 會議室" pairing — deliberately the room's OWN
           location, not its group's name, since a resource's region and the group it's
           displayed under happen to coincide today but aren't the same concept. $resource here
           is a ResourceGroupAssignment (Domain/ResourceGroup.php), which doesn't carry
           location itself, so this reads OverviewFullCalendarPresenter::GetResourceLocations()'s
           own id => location map instead — same pattern $ResourceColors already uses just
           below. Empty string for a resource that hasn't been classified yet; the JS falls
           back to the bare name. *}
        resourceLocations: {
            {foreach from=$ResourceGroups item=group name=groups}
                {foreach from=$group.resources item=resource name=groupResources}
                    "{$resource->GetId()}": "{$ResourceLocations[$resource->GetId()]|escape:'javascript'}"{if !($smarty.foreach.groups.last && $smarty.foreach.groupResources.last)},{/if}
                {/foreach}
            {/foreach}
        },
        {* opts.resourceGroups drives categoryOf()/allResourceIds()/defaultShanghaiResourceId()
           in overview_fullcalendar.js — an ordered array (not the old {physical,video} pair)
           since there are now 4 sections, one per resource_groups row (see
           OverviewFullCalendarPresenter::PageLoad()), not just 2. *}
        resourceGroups: [
            {foreach from=$ResourceGroups item=group name=groups}
                {ldelim}
                    id: "{$group.id}",
                    name: "{$group.name|escape:'javascript'}",
                    isVideo: {if $group.isVideo}true{else}false{/if},
                    resources: [
                        {foreach from=$group.resources item=resource name=groupResources}
                            {ldelim}id: "{$resource->GetId()}", name: "{$resource->GetName()|escape:'javascript'}"{rdelim}{if !$smarty.foreach.groupResources.last},{/if}
                        {/foreach}
                    ]
                {rdelim}{if !$smarty.foreach.groups.last},{/if}
            {/foreach}
        ]
    };
</script>
<script type="text/javascript">
    {* Web/scripts/onboarding-tour.js reads this the same way overview_fullcalendar.js reads
       overviewFullCalendarOpts.translations above -- pre-localized here since the .js file
       itself is static and never touched by Smarty. Step targets are CSS selectors for
       elements on THIS page only (the resource picker, calendar grid, and toolbar), plus one
       in the shared sidebar (#navMyMeetings) -- see onboarding-tour.js's positionForTarget().
       {translate_js}, not {translate key=...|escape:'javascript'} -- see
       SmartyPage::SmartyTranslateJs()'s doc comment for why that escape was a no-op (an
       English apostrophe like "let's" broke the whole script block only in en_us/zh_cn,
       never in zh_tw, which is what made this so easy to miss). *}
    window.coasiaTourConfig = {
        steps: [
            {
                target: null,
                title: {translate_js key='TourWelcomeTitle'},
                body: {translate_js key='TourWelcomeBody'}
            },
            {
                target: '#appSidebar',
                title: {translate_js key='TourSidebarTitle'},
                body: {translate_js key='TourSidebarBody'}
            },
            {
                target: '#resourcePickerTrigger',
                title: {translate_js key='TourResourceTitle'},
                body: {translate_js key='TourResourceBody'}
            },
            {
                target: '.toolbar-col-right',
                title: {translate_js key='TourAvailabilityTitle'},
                body: {translate_js key='TourAvailabilityBody'}
            },
            {
                target: '#fullcalendarRoot',
                title: {translate_js key='TourDragCreateTitle'},
                body: {translate_js key='TourDragCreateBody'}
            },
            {
                target: '#fullcalendarRoot',
                title: {translate_js key='TourEditCancelTitle'},
                body: {translate_js key='TourEditCancelBody'}
            },
            {
                target: '#navMyMeetings',
                title: {translate_js key='TourMyMeetingsTitle'},
                body: {translate_js key='TourMyMeetingsBody'}
            }
        ],
        strings: {
            skip: {translate_js key='TourSkipButton'},
            back: {translate_js key='TourBackButton'},
            next: {translate_js key='TourNextButton'},
            done: {translate_js key='TourDoneButton'},
            stepOf: {translate_js key='TourStepOf'}
        }
    };
</script>
{jsfile src="onboarding-tour.js"}
{jsfile src="participant-picker.js"}
{jsfile src="reservation-datetime-picker.js"}
{jsfile src="overview_fullcalendar.js"}

{include file='globalfooter.tpl'}
