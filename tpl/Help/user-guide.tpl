{include file='globalheader.tpl' cssFiles='css/user-guide.css'}

{function name="guideSection" id="" titleKey="" bodyKey=""}
    <section class="guide-section" id="{$id}">
        <h2>{translate key=$titleKey}</h2>
        <div class="guide-body">{translate key=$bodyKey}</div>
    </section>
{/function}

<div class="shell" id="page-user-guide">

    <div class="page-head">
        <h1>{translate key="UserGuide"}</h1>
        <p class="page-subhead">{translate key="UserGuideIntro"}</p>
    </div>

    <div class="guide-layout">
        <nav class="guide-toc" id="guideToc">
            <div class="guide-toc-group-label">{translate key="UserGuideGeneralSection"}</div>
            <a href="#login">{translate key="UserGuideLoginTitle"}</a>
            <a href="#resource-picker">{translate key="UserGuideResourcePickerTitle"}</a>
            <a href="#availability">{translate key="UserGuideAvailabilityTitle"}</a>
            <a href="#drag-create">{translate key="UserGuideDragCreateTitle"}</a>
            <a href="#full-form">{translate key="UserGuideFullFormTitle"}</a>
            <a href="#edit-cancel">{translate key="UserGuideEditCancelTitle"}</a>
            <a href="#my-meetings">{translate key="UserGuideMyMeetingsTitle"}</a>
            <a href="#room-types">{translate key="UserGuideRoomTypesTitle"}</a>
            <a href="#notifications-account">{translate key="UserGuideNotificationsAccountTitle"}</a>
            <a href="#faq">{translate key="UserGuideFaqTitle"}</a>

            {if isset($CanViewAdmin) && $CanViewAdmin}
                <div class="guide-toc-group-label guide-toc-group-label-admin">{translate key="UserGuideAdminSection"}</div>
                <a href="#admin-overview">{translate key="UserGuideAdminOverviewTitle"}</a>
                <a href="#admin-reservations">{translate key="UserGuideAdminReservationsTitle"}</a>
                <a href="#admin-resources">{translate key="UserGuideAdminResourcesTitle"}</a>
                <a href="#admin-users">{translate key="UserGuideAdminUsersTitle"}</a>
                <a href="#admin-schedules">{translate key="UserGuideAdminSchedulesTitle"}</a>
                <a href="#admin-analytics">{translate key="UserGuideAdminAnalyticsTitle"}</a>
                <a href="#admin-settings">{translate key="UserGuideAdminSettingsTitle"}</a>
                <a href="#admin-troubleshooting">{translate key="UserGuideAdminTroubleshootingTitle"}</a>
            {/if}
        </nav>

        <div class="guide-content">
            <div class="guide-group">
                <h3 class="guide-group-title">{translate key="UserGuideGeneralSection"}</h3>

                {guideSection id="login" titleKey="UserGuideLoginTitle" bodyKey="UserGuideLoginBody"}
                {guideSection id="resource-picker" titleKey="UserGuideResourcePickerTitle" bodyKey="UserGuideResourcePickerBody"}
                {guideSection id="availability" titleKey="UserGuideAvailabilityTitle" bodyKey="UserGuideAvailabilityBody"}
                {guideSection id="drag-create" titleKey="UserGuideDragCreateTitle" bodyKey="UserGuideDragCreateBody"}
                {guideSection id="full-form" titleKey="UserGuideFullFormTitle" bodyKey="UserGuideFullFormBody"}
                {guideSection id="edit-cancel" titleKey="UserGuideEditCancelTitle" bodyKey="UserGuideEditCancelBody"}
                {guideSection id="my-meetings" titleKey="UserGuideMyMeetingsTitle" bodyKey="UserGuideMyMeetingsBody"}
                {guideSection id="room-types" titleKey="UserGuideRoomTypesTitle" bodyKey="UserGuideRoomTypesBody"}
                {guideSection id="notifications-account" titleKey="UserGuideNotificationsAccountTitle" bodyKey="UserGuideNotificationsAccountBody"}
                {guideSection id="faq" titleKey="UserGuideFaqTitle" bodyKey="UserGuideFaqBody"}
            </div>

            {if isset($CanViewAdmin) && $CanViewAdmin}
                <div class="guide-group guide-group-admin">
                    <h3 class="guide-group-title">{translate key="UserGuideAdminSection"}</h3>

                    {guideSection id="admin-overview" titleKey="UserGuideAdminOverviewTitle" bodyKey="UserGuideAdminOverviewBody"}
                    {guideSection id="admin-reservations" titleKey="UserGuideAdminReservationsTitle" bodyKey="UserGuideAdminReservationsBody"}
                    {guideSection id="admin-resources" titleKey="UserGuideAdminResourcesTitle" bodyKey="UserGuideAdminResourcesBody"}
                    {guideSection id="admin-users" titleKey="UserGuideAdminUsersTitle" bodyKey="UserGuideAdminUsersBody"}
                    {guideSection id="admin-schedules" titleKey="UserGuideAdminSchedulesTitle" bodyKey="UserGuideAdminSchedulesBody"}
                    {guideSection id="admin-analytics" titleKey="UserGuideAdminAnalyticsTitle" bodyKey="UserGuideAdminAnalyticsBody"}
                    {guideSection id="admin-settings" titleKey="UserGuideAdminSettingsTitle" bodyKey="UserGuideAdminSettingsBody"}
                    {guideSection id="admin-troubleshooting" titleKey="UserGuideAdminTroubleshootingTitle" bodyKey="UserGuideAdminTroubleshootingBody"}
                </div>
            {/if}
        </div>
    </div>
</div>

{jsfile src="user-guide.js"}

{include file='globalfooter.tpl'}
