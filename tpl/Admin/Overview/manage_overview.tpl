{include file='globalheader.tpl'}

<div id="page-admin-overview" class="admin-page">
    <div class="admin-page-header">
        <div>
            <h1>{translate key=AdminOverview}</h1>
            <p class="admin-page-subtitle">{translate key=AdminOverviewSubtitle}</p>
        </div>
    </div>

    {* 四張等寬小卡 *}
    <div class="admin-stat-cards">
        <div class="admin-card admin-stat-card">
            <i class="bi bi-calendar-event admin-stat-card-icon"></i>
            <div>
                <span class="admin-stat-value">{$TodaysReservationsCount|default:0}</span>
                <span class="admin-stat-label">{translate key=AdminOverviewTodaysReservations}</span>
            </div>
        </div>
        <div class="admin-card admin-stat-card">
            <i class="bi bi-people-fill admin-stat-card-icon admin-stat-card-icon-inuse"></i>
            <div>
                <span class="admin-stat-value">{$InUseCount|default:0}</span>
                <span class="admin-stat-label">{translate key=AdminOverviewInUse}</span>
            </div>
        </div>
        <div class="admin-card admin-stat-card">
            <i class="bi bi-building admin-stat-card-icon admin-stat-card-icon-available"></i>
            <div>
                <span class="admin-stat-value">{$AvailableCount|default:0}</span>
                <span class="admin-stat-label">{translate key=AdminOverviewAvailableRooms}</span>
            </div>
        </div>
        <div class="admin-card admin-stat-card">
            <i class="bi bi-bell-fill admin-stat-card-icon {if $NotificationFailureCount|default:null > 0}admin-stat-card-icon-danger{/if}"></i>
            <div>
                {if $NotificationFailureCount === null || $NotificationFailureCount === ''}
                    <span class="admin-stat-value admin-stat-value-muted">{translate key=AdminOverviewNotConfigured}</span>
                {else}
                    <span class="admin-stat-value {if $NotificationFailureCount > 0}admin-stat-value-danger{/if}">{$NotificationFailureCount}</span>
                {/if}
                <span class="admin-stat-label">{translate key=AdminOverviewNotificationFailures}</span>
            </div>
        </div>
    </div>

    <div class="admin-grid admin-grid-2col">
        {* 今日預約 *}
        <div class="admin-card">
            <h2 class="admin-card-title">
                {translate key=AdminOverviewTodaysReservations}
                <a class="admin-card-title-action" href="{$Path}admin/manage_reservations.php">{translate key=AdminOverviewViewAllReservations}</a>
            </h2>
            {if $TodaysReservations|default:array()|count > 0}
                <div class="admin-list">
                    {foreach from=$TodaysReservations item=reservation}
                        <div class="admin-list-item admin-list-item-reservation">
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
                        </div>
                    {/foreach}
                </div>
            {else}
                <p class="admin-empty">{translate key=AdminOverviewNoReservationsToday}</p>
            {/if}
        </div>

        {* 會議室狀態 *}
        <div class="admin-card">
            <h2 class="admin-card-title">
                {translate key=AdminOverviewResourceStatus}
                <a class="admin-card-title-action" href="{$Path}admin/manage_resources.php">{translate key=AdminOverviewManageRooms}</a>
            </h2>
            {if $RoomStatusByLocation|default:array()|count > 0}
                <div class="admin-location-list">
                    {foreach from=$RoomStatusByLocation item=location}
                        <div class="admin-location-row">
                            <span class="admin-location-name">
                                {if $location.location == '__unassigned__'}{translate key=AdminOverviewUnassignedLocation}{else}{$location.location|escape}{/if}
                            </span>
                            <div class="admin-location-bar">
                                {if $location.available > 0}<span class="admin-location-bar-segment admin-location-bar-available" style="flex-grow:{$location.available}"></span>{/if}
                                {if $location.inUse > 0}<span class="admin-location-bar-segment admin-location-bar-inuse" style="flex-grow:{$location.inUse}"></span>{/if}
                                {if $location.unavailable > 0}<span class="admin-location-bar-segment admin-location-bar-unavailable" style="flex-grow:{$location.unavailable}"></span>{/if}
                            </div>
                            <span class="admin-location-counts">
                                <span class="admin-location-count-available">{translate key=AdminOverviewAvailable} {$location.available}</span>
                                <span class="admin-location-count-inuse">{translate key=AdminOverviewInUse} {$location.inUse}</span>
                                {if $location.unavailable > 0}<span class="admin-location-count-unavailable">{translate key=AdminOverviewUnavailable} {$location.unavailable}</span>{/if}
                            </span>
                        </div>
                    {/foreach}
                </div>
            {else}
                <p class="admin-empty">-</p>
            {/if}
        </div>
    </div>

    {* 需要處理 *}
    <div class="admin-card">
        <h2 class="admin-card-title">{translate key=AdminOverviewNeedsAttention}</h2>
        {if $NeedsAttention|default:array()|count > 0}
            {foreach from=$NeedsAttention item=item}
                <div class="admin-warning admin-warning-{$item.level}">
                    <i class="bi {if $item.level == 'danger'}bi-exclamation-octagon-fill{elseif $item.level == 'warning'}bi-exclamation-triangle-fill{else}bi-info-circle-fill{/if}"></i>
                    <span>{$item.message}</span>
                </div>
            {/foreach}
        {else}
            <div class="admin-needs-attention-empty">
                <i class="bi bi-check-circle-fill"></i>
                <span>{translate key=AdminOverviewNoIssues}</span>
            </div>
            <p class="admin-status-summary">{translate key=AdminOverviewSystemStatusSummary}</p>
        {/if}
    </div>
</div>

{include file="javascript-includes.tpl"}
{include file='globalfooter.tpl'}
