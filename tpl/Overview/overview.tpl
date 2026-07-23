{include file='globalheader.tpl' cssFiles='css/overview.css'}

{function name="weekdayLabel"}
    {if $index == 0}一{elseif $index == 1}二{elseif $index == 2}三{elseif $index == 3}四{elseif $index == 4}五{elseif $index == 5}六{else}日{/if}
{/function}

<div class="shell" id="page-overview">

    <div class="page-head">
        <div>
            <h1>{translate key="Overview"}</h1>
        </div>
        <div class="cal-toolbar">
            <div class="range">
                <span class="label">{formatdate date=$WeekStart format="n/j"} – {formatdate date=$WeekEnd format="n/j"}</span>
                <div class="arrows">
                    <a class="nav" href="{$ScriptUrl}/{Pages::OVERVIEW}?{QueryStringKeys::START_DATE}={$PreviousWeek}" aria-label="Previous week">‹</a>
                    <a class="nav" href="{$ScriptUrl}/{Pages::OVERVIEW}?{QueryStringKeys::START_DATE}={$NextWeek}" aria-label="Next week">›</a>
                </div>
                <a class="today" href="{$ScriptUrl}/{Pages::OVERVIEW}">{translate key='Today'}</a>
            </div>
        </div>
    </div>

    <div class="chips" id="resourceChips">
        <button class="chip all active" type="button" data-resource="all">{translate key='All'}</button>
        {foreach from=$Resources item=resource}
            <button class="chip" type="button" data-resource="{$resource->GetId()}">
                <span class="dot" style="background:{$ResourceColors[$resource->GetId()].bg};"></span>{$resource->GetName()|escape}
            </button>
        {/foreach}
    </div>

    <div class="week-card">
        <div class="week-scroll">
            <div class="week-grid">
                <div class="corner">{$DayStartHour}:00–{$DayEndHour}:00</div>
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
                    <div class="day-col{if $day.isToday} today{elseif $day.isWeekend} weekend{/if}">
                        {if $day.isWeekend && $day.events|default:array()|count == 0}
                            <div class="weekend-note">{translate key='Weekend'}</div>
                        {/if}
                        {foreach from=$day.events item=event}
                            <a class="event{if $event.isMine} mine{/if}"
                               href="{$ScriptUrl}/{Pages::RESERVATION}?{QueryStringKeys::REFERENCE_NUMBER}={$event.referenceNumber}"
                               style="top:{$event.top}%; height:{$event.height}%; background:{$event.bg}; color:{$event.fg};"
                               data-resource="{$event.resourceId}">
                                <span class="tt">{if $event.title}{$event.title|escape}{else}{translate key='Untitled'}{/if}</span>
                                <span class="rr">{$event.resourceName|escape}</span>
                            </a>
                        {/foreach}
                    </div>
                {/foreach}
            </div>
        </div>
    </div>

    <div class="legend">
        {foreach from=$Resources item=resource}
            <span><i style="background:{$ResourceColors[$resource->GetId()].bg};"></i>{$resource->GetName()|escape}</span>
        {/foreach}
        <span><i class="mine-i"></i>{translate key='YourReservations'}</span>
    </div>

</div>

{include file="javascript-includes.tpl"}
{jsfile src="overview.js"}

{include file='globalfooter.tpl'}
