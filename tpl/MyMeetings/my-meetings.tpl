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
        <h2>{translate key="NextMeeting"}</h2>
        <div class="next-meeting-card{if !$NextMeeting} next-meeting-card-empty{/if}">
            {if $NextMeeting}
                <div class="next-meeting-top">
                    <span class="next-meeting-title">{if $NextMeeting.title}{$NextMeeting.title|escape}{else}{translate key='Untitled'}{/if}</span>
                    <span class="next-meeting-countdown" id="nextMeetingCountdown" data-start-seconds="{$NextMeeting.startTimestamp}"></span>
                </div>
                <div class="next-meeting-meta">
                    <i class="bi bi-calendar3"></i>
                    <span>{formatdate date=$NextMeeting.date format="n/j"}（{weekdayLabel wd=$NextMeeting.date->Weekday()}） {$NextMeeting.startLabel}–{$NextMeeting.endLabel}</span>
                </div>
                <div class="next-meeting-meta">
                    <i class="bi bi-geo-alt"></i>
                    <span>{$NextMeeting.resourceName|escape}</span>
                </div>
                <div class="next-meeting-actions">
                    <a class="next-meeting-btn next-meeting-btn-ghost" href="{$ScriptUrl}/{Pages::RESERVATION}?{QueryStringKeys::REFERENCE_NUMBER}={$NextMeeting.referenceNumber}">{translate key='ViewDetails'}</a>
                    {if $NextMeeting.joinUrl}
                        <a class="next-meeting-btn next-meeting-btn-primary" href="{$NextMeeting.joinUrl|escape}" target="_blank" rel="noopener">{translate key='JoinMeeting'}</a>
                    {/if}
                </div>
            {else}
                <div class="next-meeting-empty">
                    <i class="bi bi-calendar2-check"></i>
                    <span>{translate key='NoUpcomingMeetingsSoon'}</span>
                </div>
            {/if}
        </div>
    </section>

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

{if $NextMeeting}
<script>
    (function () {
        var el = document.getElementById('nextMeetingCountdown');
        if (!el) {
            return;
        }

        var startMs = parseInt(el.getAttribute('data-start-seconds'), 10) * 1000;
        var inProgressLabel = '{translate key="AdminOverviewInProgress"|escape:"javascript"}';
        var minutesSuffix = '{translate key="CountdownMinutesSuffix"|escape:"javascript"}';
        var hoursSuffix = '{translate key="CountdownHoursSuffix"|escape:"javascript"}';
        var daysSuffix = '{translate key="CountdownDaysSuffix"|escape:"javascript"}';

        function render() {
            var diffMinutes = Math.round((startMs - Date.now()) / 60000);

            if (diffMinutes <= 0) {
                el.textContent = inProgressLabel;
            } else if (diffMinutes < 60) {
                el.textContent = diffMinutes + ' ' + minutesSuffix;
            } else if (diffMinutes < 1440) {
                el.textContent = Math.floor(diffMinutes / 60) + ' ' + hoursSuffix;
            } else {
                el.textContent = Math.floor(diffMinutes / 1440) + ' ' + daysSuffix;
            }
        }

        render();
        // 30s is frequent enough that "30 分鐘後" visibly ticks down while the page sits open,
        // cheap enough (plain text update, no layout thrash) to leave running.
        setInterval(render, 30000);
    })();
</script>
{/if}

{include file="javascript-includes.tpl"}

{include file='globalfooter.tpl'}
