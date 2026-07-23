{include file='globalheader.tpl' cssFiles='css/my-meetings.css'}

{function name="weekdayLabel"}
    {if $wd == 0}日{elseif $wd == 1}一{elseif $wd == 2}二{elseif $wd == 3}三{elseif $wd == 4}四{elseif $wd == 5}五{else}六{/if}
{/function}

{function name="dayGroup" days=[]}
    {foreach from=$days item=day}
        <div class="day-group">
            <div class="day-group-label">
                {formatdate date=$day.date format="n/j"}（{weekdayLabel wd=$day.date->Weekday()}）
            </div>
            <div class="day-group-items">
                {foreach from=$day.items item=item}
                    <a class="meeting-row" href="{$ScriptUrl}/{Pages::RESERVATION}?{QueryStringKeys::REFERENCE_NUMBER}={$item.referenceNumber}"
                       style="--dot-color:{$item.bg};">
                        <span class="meeting-time">{$item.startLabel} – {$item.endLabel}</span>
                        <span class="meeting-info">
                            <span class="meeting-title">{if $item.title}{$item.title|escape}{else}{translate key='Untitled'}{/if}</span>
                            <span class="meeting-resource">{$item.resourceName|escape}</span>
                        </span>
                        {if $item.requiresApproval}
                            <span class="meeting-pending">{translate key='PendingApproval'}</span>
                        {/if}
                    </a>
                {/foreach}
            </div>
        </div>
    {/foreach}
{/function}

<div class="shell" id="page-my-meetings">

    <div class="page-head">
        <h1>{translate key="MyMeetings"}</h1>
    </div>

    <section class="meetings-section">
        <h2>{translate key="UpcomingReservations"}</h2>
        {if count($Upcoming) > 0}
            {dayGroup days=$Upcoming}
        {else}
            <p class="empty-note">{translate key="NoUpcomingReservations"}</p>
        {/if}
    </section>

    <details class="meetings-section past-section moreOptions">
        <summary>
            <i class="bi bi-chevron-right me-1"></i>{translate key="PastReservations"}
        </summary>
        {if count($Past) > 0}
            {dayGroup days=$Past}
        {else}
            <p class="empty-note">{translate key="NoPastReservations"}</p>
        {/if}
    </details>

</div>

{include file="javascript-includes.tpl"}

{include file='globalfooter.tpl'}
