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

    <div class="event-popover d-none" id="eventPopover">
        <div class="ep-color-bar" id="epColorBar"></div>
        <div class="ep-body">
            <div class="ep-title" id="epTitle"></div>
            <div class="ep-row"><i class="bi bi-clock"></i><span id="epTime"></span></div>
            <div class="ep-row"><i class="bi bi-geo-alt"></i><span id="epResource"></span></div>
        </div>
        <div class="ep-footer">
            <a href="#" id="epViewLink" class="btn btn-sm btn-primary">{translate key='ViewDetails'}</a>
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
        csrfToken: '{$CSRFToken|escape:'javascript'}',
        currentUserId: {$UserId},
        timezone: '{$Timezone|escape:'javascript'}',
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
