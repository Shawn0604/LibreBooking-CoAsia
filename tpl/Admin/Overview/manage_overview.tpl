{include file='globalheader.tpl'}

<div id="page-admin-overview" class="admin-page">
    <div class="admin-page-header">
        <h1>{translate key=AdminOverview}</h1>
    </div>

    {* 系統警告 *}
    <div class="admin-card" style="margin-bottom:16px;">
        <h2 class="admin-card-title">{translate key=AdminOverviewWarnings}</h2>
        {if $Warnings|default:array()|count > 0}
            {foreach from=$Warnings item=warning}
                <div class="admin-warning admin-warning-{$warning.level}">
                    <i class="bi {if $warning.level == 'danger'}bi-exclamation-octagon-fill{elseif $warning.level == 'warning'}bi-exclamation-triangle-fill{else}bi-info-circle-fill{/if}"></i>
                    <span>{$warning.message}</span>
                </div>
            {/foreach}
        {else}
            <p class="admin-empty">{translate key=AdminOverviewNoWarnings}</p>
        {/if}
    </div>

    <div class="admin-grid">
        {* 今日預約 *}
        <div class="admin-card">
            <h2 class="admin-card-title">{translate key=AdminOverviewTodaysReservations}</h2>
            <div class="admin-stat-row" style="margin-bottom:12px;">
                <div class="admin-stat">
                    <span class="admin-stat-value">{$TodaysReservations|default:array()|count}</span>
                    <span class="admin-stat-label">{translate key=AdminOverviewTodaysReservations}</span>
                </div>
            </div>
            {if $TodaysReservations|default:array()|count > 0}
                <div class="admin-list">
                    {foreach from=$TodaysReservations item=reservation}
                        <div class="admin-list-item">
                            <span class="admin-list-item-primary">
                                {if $reservation.title}{$reservation.title|escape}{else}{translate key=NoReservationTitle}{/if}
                                — {$reservation.resource_name|escape}
                            </span>
                            <span class="admin-list-item-secondary">{formatdate date=$reservation.start_date key=short_datetime} - {formatdate date=$reservation.end_date key=short_datetime}</span>
                        </div>
                    {/foreach}
                </div>
            {else}
                <p class="admin-empty">{translate key=AdminOverviewNoReservationsToday}</p>
            {/if}
        </div>

        {* 會議室狀態 *}
        <div class="admin-card">
            <h2 class="admin-card-title">{translate key=AdminOverviewResourceStatus}</h2>
            <div class="admin-stat-row" style="margin-bottom:12px;">
                <div class="admin-stat">
                    <span class="admin-stat-value admin-stat-available">{$ResourceStatusCounts.available|default:0}</span>
                    <span class="admin-stat-label">{translate key=AdminOverviewAvailable}</span>
                </div>
                <div class="admin-stat">
                    <span class="admin-stat-value admin-stat-unavailable">{$ResourceStatusCounts.unavailable|default:0}</span>
                    <span class="admin-stat-label">{translate key=AdminOverviewUnavailable}</span>
                </div>
                <div class="admin-stat">
                    <span class="admin-stat-value">{$ResourceStatusCounts.hidden|default:0}</span>
                    <span class="admin-stat-label">{translate key=AdminOverviewHidden}</span>
                </div>
            </div>
            {if $UnavailableResources|default:array()|count > 0}
                <div class="admin-list">
                    {foreach from=$UnavailableResources item=resource}
                        <div class="admin-list-item">
                            <span class="admin-list-item-primary">{$resource.name|escape}</span>
                            {if $resource.reason}<span class="admin-list-item-secondary">{$resource.reason|escape}</span>{/if}
                        </div>
                    {/foreach}
                </div>
            {/if}
        </div>

        {* 近期使用者 *}
        <div class="admin-card">
            <h2 class="admin-card-title">{translate key=AdminOverviewRecentUsers}</h2>
            {if $RecentUsers|default:array()|count > 0}
                <div class="admin-list">
                    {foreach from=$RecentUsers item=user}
                        <div class="admin-list-item">
                            <span class="admin-list-item-primary">{$user.fname|escape} {$user.lname|escape} ({$user.username|escape})</span>
                            <span class="admin-list-item-secondary">{translate key=AdminOverviewJoinedOn} {formatdate date=$user.date_created key=general_date}</span>
                        </div>
                    {/foreach}
                </div>
            {else}
                <p class="admin-empty">-</p>
            {/if}
        </div>
    </div>
</div>

{include file="javascript-includes.tpl"}
{include file='globalfooter.tpl'}
