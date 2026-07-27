{include file='globalheader.tpl' cssFiles='css/overview.css'}

{function name="weekdayLabel"}
    {if $index == 0}一{elseif $index == 1}二{elseif $index == 2}三{elseif $index == 3}四{elseif $index == 4}五{elseif $index == 5}六{else}日{/if}
{/function}

<div class="shell" id="page-overview">

    <div class="cal-toolbar">
        <h1>{translate key="Overview"}</h1>

        <div class="toolbar-nav-group">
            {if $IsCurrentWeek}
                <span class="today-btn disabled" aria-disabled="true">{translate key='Today'}</span>
            {else}
                <a class="today-btn" href="{$ScriptUrl}/{Pages::OVERVIEW}">{translate key='Today'}</a>
            {/if}
            <div class="arrows">
                <a class="nav" href="{$ScriptUrl}/{Pages::OVERVIEW}?{QueryStringKeys::START_DATE}={$PreviousWeek}" aria-label="Previous week"><i class="bi bi-chevron-left"></i></a>
                <a class="nav" href="{$ScriptUrl}/{Pages::OVERVIEW}?{QueryStringKeys::START_DATE}={$NextWeek}" aria-label="Next week"><i class="bi bi-chevron-right"></i></a>
            </div>
            <span class="month-label">{if $WeekStart->Format('Y-m') == $WeekEnd->Format('Y-m')}{formatdate date=$WeekStart format="Y年n月"}{elseif $WeekStart->Format('Y') == $WeekEnd->Format('Y')}{formatdate date=$WeekStart format="Y年n月"}－{formatdate date=$WeekEnd format="n月"}{else}{formatdate date=$WeekStart format="Y年n月"}－{formatdate date=$WeekEnd format="Y年n月"}{/if}</span>
        </div>

        <div class="toolbar-actions">
            <a class="toolbar-icon-btn" href="{$Path}{Pages::SEARCH_RESERVATIONS}" title="{translate key='SearchReservations'}">
                <i class="bi bi-search"></i>
            </a>
            <a class="toolbar-icon-btn" href="{$Path}help.php?ht=about" title="{translate key='Help'}">
                <i class="bi bi-question-circle"></i>
            </a>
            {if isset($CanViewAdmin) && $CanViewAdmin}
                <a class="toolbar-icon-btn" href="{$Path}admin/manage_resources.php" title="{translate key='ManageResources'}">
                    <i class="bi bi-gear"></i>
                </a>
            {/if}
            <div class="cal-category-tabs" id="calCategoryTabs">
                <button type="button" class="cal-category-tab active" data-category="physical">{translate key='PhysicalRooms'}</button>
                <button type="button" class="cal-category-tab" data-category="video">{translate key='VideoConferenceResources'}</button>
            </div>
        </div>
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

    <div class="week-card">
        <div class="week-scroll">
            <div class="week-grid">
                <div class="corner">GMT{$WeekStart->Format('P')}</div>
                {foreach from=$Days item=day name=dayLoop}
                    <div class="day-head{if $day.isToday} today{elseif $day.isWeekend} weekend{/if}">
                        <div class="dow">{weekdayLabel index=$smarty.foreach.dayLoop.index}</div>
                        <div class="num">{formatdate date=$day.date format="j"}</div>
                    </div>
                {/foreach}

                <div class="hours-col">
                    {foreach from=$Hours item=hourLabel}
                        <div class="hr">{$hourLabel}</div>
                    {/foreach}
                </div>

                {foreach from=$Days item=day name=dayCols}
                    <div class="day-col{if $day.isToday} today{elseif $day.isWeekend} weekend{/if}"
                         data-date="{$day.isoDate}" data-day-start="{$DayStartHour}" data-day-end="{$DayEndHour}">
                        {if $day.isToday}<div class="now-line" id="nowLine"></div>{/if}
                        {foreach from=$day.events item=event}
                            <a class="event{if $event.isPast} past{/if}"
                               href="{$ScriptUrl}/{Pages::RESERVATION}?{QueryStringKeys::REFERENCE_NUMBER}={$event.referenceNumber}"
                               style="top:{$event.top}%; height:{$event.height}%; left:{$event.left}; width:{$event.width}; background:{$event.bg}; border-left-color:{$event.accent};"
                               data-resource="{$event.resourceId}"
                               data-title="{if $event.title}{$event.title|escape}{else}{translate key='Untitled'}{/if}"
                               data-resource-name="{$event.resourceName|escape}"
                               data-time-range="{$event.startLabel} – {$event.endLabel}">
                                <span class="tt">{if $event.title}{$event.title|escape}{else}{translate key='Untitled'}{/if}</span>
                                <span class="rr">{$event.startLabel} – {$event.endLabel}</span>
                            </a>
                        {/foreach}
                    </div>
                {/foreach}
            </div>
        </div>
    </div>

</div>

{include file="javascript-includes.tpl"}
<script type="text/javascript">
    window.overviewOpts = {
        reservationUrl: '{$ScriptUrl}/{Pages::RESERVATION}',
        overviewUrl: '{$ScriptUrl}/{Pages::OVERVIEW}',
        saveUrl: '{$ScriptUrl}/ajax/reservation_save.php',
        csrfToken: '{$CSRFToken|escape:'javascript'}',
        currentUserId: {$UserId},
        weekStart: '{$WeekStart->Format('Y-m-d')}',
        dayStartHour: {$DayStartHour},
        dayEndHour: {$DayEndHour},
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
{jsfile src="overview.js"}

{include file='globalfooter.tpl'}
