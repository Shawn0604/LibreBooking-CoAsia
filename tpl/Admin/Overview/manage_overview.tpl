{include file='globalheader.tpl'}

<div id="page-admin-overview" class="admin-page">
    <div class="admin-page-header">
        <div>
            <h1>{translate key=AdminOverview}</h1>
            <p class="admin-page-subtitle">{translate key=AdminOverviewSubtitle}</p>
        </div>
    </div>

    {* 第一排：今日預約／進行中／一小時內即將開始／停用或維修中會議室 *}
    <div class="admin-stat-cards">
        <div class="admin-card admin-stat-card">
            <i class="bi bi-calendar-event admin-stat-card-icon"></i>
            <div>
                <span class="admin-stat-value">{$TodaysReservationsCount|default:0}</span>
                <span class="admin-stat-label">{translate key=AdminOverviewTodaysReservations}</span>
            </div>
        </div>
        <div class="admin-card admin-stat-card">
            <i class="bi bi-play-circle-fill admin-stat-card-icon admin-stat-card-icon-inuse"></i>
            <div>
                <span class="admin-stat-value">{$InProgressCount|default:0}</span>
                <span class="admin-stat-label">{translate key=AdminOverviewInProgress}</span>
            </div>
        </div>
        <div class="admin-card admin-stat-card">
            <i class="bi bi-alarm-fill admin-stat-card-icon admin-stat-card-icon-available"></i>
            <div>
                <span class="admin-stat-value">{$StartingSoonCount|default:0}</span>
                <span class="admin-stat-label">{translate key=AdminOverviewStartingSoon}</span>
            </div>
        </div>
        <div class="admin-card admin-stat-card">
            <i class="bi bi-tools admin-stat-card-icon {if $UnavailableResourceCount|default:0 > 0}admin-stat-card-icon-danger{/if}"></i>
            <div>
                <span class="admin-stat-value {if $UnavailableResourceCount|default:0 > 0}admin-stat-value-danger{/if}">{$UnavailableResourceCount|default:0}</span>
                <span class="admin-stat-label">{translate key=AdminOverviewUnavailableRooms}</span>
            </div>
        </div>
    </div>

    {* 第二排：今日預約清單＋即時會議室狀態 *}
    <div class="admin-grid admin-grid-2col">
        <div class="admin-card">
            <h2 class="admin-card-title">
                {translate key=AdminOverviewTodaysReservations}
                <a class="admin-card-title-action" href="{$Path}admin/manage_reservations.php">{translate key=AdminOverviewViewAllReservations}</a>
            </h2>
            {if $TodaysReservations|default:array()|count > 0}
                <div class="admin-list">
                    {foreach from=$TodaysReservations item=reservation}
                        <a class="admin-list-item admin-list-item-reservation admin-list-item-link" href="{$Path}reservation.php?{QueryStringKeys::REFERENCE_NUMBER}={$reservation.reference_number|escape:'url'}">
                            <span class="admin-list-item-time">{formatdate date=$reservation.start_date key=period_time} - {formatdate date=$reservation.end_date key=period_time}</span>
                            <span class="admin-list-item-primary">
                                {if $reservation.title}{$reservation.title|escape}{else}{translate key=NoReservationTitle}{/if}
                            </span>
                            <span class="admin-list-item-tag">{$reservation.resource_name|escape}</span>
                            <span class="admin-list-item-secondary">{$reservation.fname|escape} {$reservation.lname|escape}</span>
                            <span class="admin-badge admin-badge-{$reservation.live_status}">
                                {if $reservation.status_id == 3}{translate key=AdminOverviewStatusPending}
                                {elseif $reservation.live_status == 'inprogress'}{translate key=AdminOverviewStatusInProgress}
                                {elseif $reservation.live_status == 'upcoming'}{translate key=AdminOverviewStatusUpcoming}
                                {else}{translate key=AdminOverviewStatusEnded}{/if}
                            </span>
                        </a>
                    {/foreach}
                </div>
            {else}
                <p class="admin-empty">{translate key=AdminOverviewNoReservationsToday}</p>
            {/if}
        </div>

        <div class="admin-card">
            <h2 class="admin-card-title">
                {translate key=AdminOverviewLiveRoomStatus}
                <a class="admin-card-title-action" href="{$Path}admin/manage_resources.php">{translate key=AdminOverviewManageRooms}</a>
            </h2>
            {if $LiveResourceStatus|default:array()|count > 0}
                <div class="admin-room-status-list">
                    {foreach from=$LiveResourceStatus item=resource}
                        <a class="admin-room-status-row" href="{$Path}admin/manage_resources.php">
                            <span class="admin-room-status-name">
                                {$resource.name|escape}
                                {if $resource.location}<span class="admin-cell-secondary">{$resource.location|escape}</span>{/if}
                            </span>
                            {if $resource.status == 'unavailable'}
                                <span class="admin-badge admin-badge-unavailable">{translate key=AdminOverviewMaintenance}</span>
                                <span class="admin-room-status-time">{if $resource.reason}{$resource.reason|escape}{/if}</span>
                            {elseif $resource.status == 'inuse'}
                                <span class="admin-badge admin-badge-inprogress">{translate key=AdminOverviewInUse}</span>
                                <span class="admin-room-status-time">{translate key=AdminOverviewUntil} {formatdate date=$resource.until key=period_time}</span>
                            {else}
                                <span class="admin-badge admin-badge-free">{translate key=AdminOverviewFree}</span>
                                <span class="admin-room-status-time">
                                    {if $resource.until}{translate key=AdminOverviewNextAt} {formatdate date=$resource.until key=period_time}{else}{translate key=AdminOverviewNothingScheduled}{/if}
                                </span>
                            {/if}
                        </a>
                    {/foreach}
                </div>
            {else}
                <p class="admin-empty">-</p>
            {/if}
        </div>
    </div>

    {* 第三排：近七日預約趨勢＋熱門會議室排行 *}
    <div class="admin-grid admin-grid-2col">
        <div class="admin-card">
            <h2 class="admin-card-title">{translate key=AdminOverviewWeeklyTrend}</h2>
            {if $WeeklyTrend|default:array()|count > 0}
                <div class="admin-trend-chart">
                    {foreach from=$WeeklyTrend item=day}
                        <div class="admin-trend-col">
                            <span class="admin-trend-count">{$day.count}</span>
                            <span class="admin-trend-bar" style="height:{$day.barHeight}%"></span>
                            <span class="admin-trend-label">{$day.label}</span>
                        </div>
                    {/foreach}
                </div>
            {else}
                <p class="admin-empty">-</p>
            {/if}
        </div>

        <div class="admin-card">
            <h2 class="admin-card-title">{translate key=AdminOverviewTopResources}</h2>
            {if $TopResources|default:array()|count > 0}
                <div class="admin-rank-list">
                    {foreach from=$TopResources item=resource name=topResourcesLoop}
                        <a class="admin-rank-item" href="{$Path}admin/manage_resources.php">
                            <span class="admin-rank-number">{$smarty.foreach.topResourcesLoop.iteration}</span>
                            <span class="admin-list-item-primary">
                                {$resource.name|escape}
                                {if $resource.location}<span class="admin-cell-secondary">{$resource.location|escape}</span>{/if}
                            </span>
                            <span class="admin-list-item-tag">{translate key=AdminOverviewReservationCount args=$resource.count}</span>
                        </a>
                    {/foreach}
                </div>
            {else}
                <p class="admin-empty">{translate key=AdminOverviewNoReservationsThisWeek}</p>
            {/if}
        </div>
    </div>

    {* 第四排：需要處理的異常 *}
    <div class="admin-card">
        <h2 class="admin-card-title">{translate key=AdminOverviewNeedsAttention}</h2>
        {if $NeedsAttention|default:array()|count > 0}
            {foreach from=$NeedsAttention item=item}
                {if $item.link}
                    <a href="{$Path}{$item.link}" class="admin-warning admin-warning-{$item.level} admin-warning-link">
                        <i class="bi {if $item.level == 'danger'}bi-exclamation-octagon-fill{elseif $item.level == 'warning'}bi-exclamation-triangle-fill{else}bi-info-circle-fill{/if}"></i>
                        <span>{$item.message}</span>
                    </a>
                {else}
                    <div class="admin-warning admin-warning-{$item.level}">
                        <i class="bi {if $item.level == 'danger'}bi-exclamation-octagon-fill{elseif $item.level == 'warning'}bi-exclamation-triangle-fill{else}bi-info-circle-fill{/if}"></i>
                        <span>{$item.message}</span>
                    </div>
                {/if}
            {/foreach}
        {else}
            <div class="admin-needs-attention-empty">
                <i class="bi bi-check-circle-fill"></i>
                <span>{translate key=AdminOverviewNoIssues}</span>
            </div>
        {/if}
    </div>
</div>

{include file="javascript-includes.tpl"}
{include file='globalfooter.tpl'}
