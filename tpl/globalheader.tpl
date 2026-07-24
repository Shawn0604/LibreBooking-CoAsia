<!DOCTYPE html>
<html lang="{$HtmlLang}" dir="{$HtmlTextDirection}">

<head>
    <title>{if isset($TitleKey) && $TitleKey neq ''}{translate key=$TitleKey args=$TitleArgs}{else}{$Title}{/if}</title>
    <meta http-equiv="Content-Type" content="text/html; charset={$Charset}" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex" />
    {if isset($ShouldLogout) && $ShouldLogout}
        <meta http-equiv="REFRESH"
            content="{$SessionTimeoutSeconds};URL={$Path}logout.php?{QueryStringKeys::REDIRECT}={$smarty.server.REQUEST_URI|urlencode}" />
    {/if}
    <link rel="shortcut icon" href="{$Path}{$FaviconUrl}" />
    <link rel="icon" href="{$Path}{$FaviconUrl}" />
    <!-- JavaScript -->
    {if isset($UseLocalJquery) && $UseLocalJquery}
        {vendor_js src="jquery/3.3.1/jquery-3.3.1.min.js"}
        {vendor_js src="jquery-migrate/3.6.0/jquery-migrate-3.6.0.min.js"}
        {vendor_js src="jquery-ui/1.14.2/js/jquery-ui.1.14.2.min.js"}
        {vendor_js src="bootstrap/5.3.3/js/bootstrap.bundle.min.js"}
    {else}
        <script src="https://code.jquery.com/jquery-3.3.1.min.js"
            integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
        <script src="https://code.jquery.com/jquery-migrate-3.6.0.min.js"
            integrity="sha256-LWwll4H5AAC/20gH21NFgk4rYMvZhvc1KD0c5iG7QvM=" crossorigin="anonymous"></script>
        <script src="https://code.jquery.com/ui/1.14.2/jquery-ui.min.js"
            integrity="sha256-mblSWfbYzaq/f+4akyMhE6XELCou4jbkgPv+JQPER2M=" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
        </script>
    {/if}

    <!-- End JavaScript -->

    <!-- CSS -->
    {if isset($UseLocalJquery) && $UseLocalJquery}
        {vendor_css src="jquery-ui/1.14.2/css/jquery-ui.1.14.2.min.css"}
        {vendor_css src="bootstrap-icons/1.11.3/css/bootstrap-icons.min.css"}
        {vendor_css src="bootstrap/5.3.3/css/bootstrap.css"}
        {vendor_css src="flatpickr/4.6.13/css/flatpickr.min.css"}
        {if isset($Trumbowyg) && $Trumbowyg}
            {vendor_css src="trumbowyg/2.27.3/css/trumbowyg.min.css"}
        {/if}
        {if isset($DataTable) && $DataTable}
            {vendor_css src="datatables/1.13.7/css/dataTables.bootstrap5.min.css"}
            {vendor_css src="datatables-responsive/2.5.0/css/responsive.bootstrap5.min.css"}
            {vendor_css src="datatables-buttons/2.4.2/css/buttons.bootstrap5.min.css"}
        {/if}
    {else}
        <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.2/themes/smoothness/jquery-ui.css" type="text/css" />
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
            integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css"
            integrity="sha384-RkASv+6KfBMW9eknReJIJ6b3UnjKOKC5bOUaNgIY778NFbQ8MtWq9Lr/khUgqtTt" crossorigin="anonymous">
        {if isset($Trumbowyg) && $Trumbowyg}
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Trumbowyg/2.27.3/ui/trumbowyg.min.css"
                type="text/css" />
        {/if}
        {if isset($DataTable) && $DataTable}
            <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" type="text/css" />
            <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
            <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css"
                type="text/css">
        {/if}
    {/if}
    {if isset($InlineEdit) && $InlineEdit}
        {vendor_css src="x-editable/1.5.1/css/bootstrap-editable.css"}
    {/if}
    {if isset($Select2) && $Select2}
        {if isset($UseLocalJquery) && $UseLocalJquery}
            {vendor_css src="select2/4.1.0-rc.0/css/select2.min.css"}
        {else}
            <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        {/if}
    {/if}

    {if isset($UseLocalJquery) && $UseLocalJquery}
        {cssfile src="assets/fonts/hind/v18/hind.css"}
    {else}
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Hind:wght@300;400;500;700&display=swap" />
    {/if}
    {cssfile src="librebooking.css"}
    {if isset($cssFiles) && $cssFiles neq ''}
        {assign var='CssFileList' value=$cssFiles|split:','}
        {foreach from=$CssFileList item=cssFile}
            {cssfile src=$cssFile}
        {/foreach}
    {/if}
    {if isset($CssUrl) && $CssUrl neq ''}
        {cssfile src=$CssUrl}
    {/if}
    {if isset($CssStylingFile) && $CssStylingFile neq ''}
        {cssfile src='styling-plugin.php'}
    {/if}
    {if isset($CssExtensionFile) && $CssExtensionFile neq ''}
        {cssfile src=$CssExtensionFile}
    {/if}

    {if isset($printCssFiles) && $printCssFiles neq ''}
        {assign var='PrintCssFileList' value=$printCssFiles|split:','}
        {foreach from=$PrintCssFileList item=cssFile}
            <link rel='stylesheet' type='text/css' href='{$Path}{$cssFile}' media='print' />
        {/foreach}
    {/if}

    <!-- End CSS -->
</head>

<body data-bs-theme='{$cssTheme}'>

    <noscript>
        <div class="alert alert-warning text-center m-2" role="alert">
            {translate key="JavascriptRequired"}
        </div>
    </noscript>

    {* Alert everyone if `script.url` is unset or missing the required `/Web` suffix, as either will cause issues. *}
    {if !isset($ScriptUrl) || $ScriptUrl eq ''}
        <div id="script-url-warning" class="alert alert-danger text-center m-2" role="alert">
            {translate key="ScriptUrlNotConfigured"}
        </div>
    {elseif !empty($ScriptUrlMissingWebSuffix)}
        <div id="script-url-web-suffix-warning" class="alert alert-danger text-center m-2" role="alert">
            {translate key="ScriptUrlMissingWebSuffix"}
        </div>
    {/if}

    {if !isset($HideNavBar) || $HideNavBar == false}
    <div class="app-shell">
        <aside class="app-sidebar" id="appSidebar">
            <div class="sidebar-brand">
                <a href="{$HomeUrl}">
                    <img src="{$Path}{$LogoUrl}?{$Version}" alt="{$Title}" class="logo">
                </a>
                {if $CompanyName neq ''}
                    <a class="sidebar-brand-company" href="{$CompanyUrl}">{$CompanyName}</a>
                {/if}
            </div>

            {if isset($LoggedIn) && $LoggedIn && !empty($UserName)}
                <div class="sidebar-user">
                    <span class="sidebar-user-avatar">{$UserName|truncate:1:''|upper}</span>
                    <span class="sidebar-user-name">{$UserName|escape}</span>
                </div>
            {/if}

            <button type="button" class="sidebar-toggler d-lg-none" data-bs-toggle="collapse"
                data-bs-target="#librebooking-navigation" aria-controls="librebooking-navigation" aria-expanded="false"
                aria-label="{translate key=ShowHideNavigation}">
                <i class="bi bi-list"></i>
            </button>

            <div class="collapse sidebar-nav-collapse" id="librebooking-navigation">
                <nav class="sidebar-nav">
                    {if isset($LoggedIn) && $LoggedIn}
                        <a class="create-btn" href="{$ScriptUrl}/{Pages::RESERVATION}">
                            <i class="bi bi-plus-lg"></i>{translate key='NewReservation'}
                        </a>

                        {* Mini month calendar — only present when the current page (Overview) has set
                           these vars. Smarty {include} shares the including template's variable scope,
                           so this works without any page-specific plumbing in Page.php. *}
                        {if isset($PhysicalResources) || isset($VideoResources)}
                            <div class="mini-cal" id="miniCal">
                                <div class="mini-cal-head">
                                    <span class="mini-cal-label" id="miniCalLabel"></span>
                                    <div class="mini-cal-nav">
                                        <button type="button" id="miniCalPrev" aria-label="Previous month"><i class="bi bi-chevron-left"></i></button>
                                        <button type="button" id="miniCalNext" aria-label="Next month"><i class="bi bi-chevron-right"></i></button>
                                    </div>
                                </div>
                                <div class="mini-cal-weekdays">
                                    <span>一</span><span>二</span><span>三</span><span>四</span><span>五</span><span>六</span><span>日</span>
                                </div>
                                <div class="mini-cal-grid" id="miniCalGrid"></div>
                            </div>

                            <div class="sidebar-divider"></div>

                            <div id="resourceSidebar">
                                {if count($PhysicalResources) > 0}
                                    <div class="sidebar-group" id="navResGroupPhysical">
                                        <button class="sidebar-link sidebar-group-toggle" type="button" data-bs-toggle="collapse"
                                            data-bs-target="#resGroupPhysicalList" aria-expanded="true">
                                            <i class="bi bi-door-open"></i>{translate key='PhysicalRooms'}
                                            <i class="bi bi-chevron-right ms-auto sidebar-chevron"></i>
                                        </button>
                                        <div class="collapse show sidebar-submenu" id="resGroupPhysicalList">
                                            {foreach from=$PhysicalResources item=resource}
                                                <label class="res-check">
                                                    <input type="checkbox" checked data-resource="{$resource->GetId()}">
                                                    <span class="res-check-box" style="--check-color:{$ResourceColors[$resource->GetId()].bg};"></span>
                                                    <span class="res-check-name">{$resource->GetName()|escape}</span>
                                                </label>
                                            {/foreach}
                                        </div>
                                    </div>
                                {/if}

                                {if count($VideoResources) > 0}
                                    <div class="sidebar-group" id="navResGroupVideo">
                                        <button class="sidebar-link sidebar-group-toggle" type="button" data-bs-toggle="collapse"
                                            data-bs-target="#resGroupVideoList" aria-expanded="true">
                                            <i class="bi bi-camera-video"></i>{translate key='VideoConferenceResources'}
                                            <i class="bi bi-chevron-right ms-auto sidebar-chevron"></i>
                                        </button>
                                        <div class="collapse show sidebar-submenu" id="resGroupVideoList">
                                            {foreach from=$VideoResources item=resource}
                                                <label class="res-check">
                                                    <input type="checkbox" checked data-resource="{$resource->GetId()}">
                                                    <span class="res-check-box" style="--check-color:{$ResourceColors[$resource->GetId()].bg};"></span>
                                                    <span class="res-check-name">{$resource->GetName()|escape}</span>
                                                </label>
                                            {/foreach}
                                        </div>
                                    </div>
                                {/if}
                            </div>

                            <div class="sidebar-divider"></div>
                        {/if}

                        {* CoAsia: hidden for now — day-to-day usage only needs Overview + Bookings below. Remove `{if false}` to restore. *}
                        {if false}
                        <a class="sidebar-link" id="navDashboard" href="{$Path}{Pages::DASHBOARD}">
                            <i class="bi bi-speedometer2"></i>{translate key="Dashboard"}
                        </a>

                        <div class="sidebar-group" id="navMyAccountDropdown">
                            <button class="sidebar-link sidebar-group-toggle" type="button" data-bs-toggle="collapse"
                                data-bs-target="#sidebarMyAccount">
                                <i class="bi bi-person-circle"></i>{translate key="MyAccount"}
                                <i class="bi bi-chevron-right ms-auto sidebar-chevron"></i>
                            </button>
                            <div class="collapse sidebar-submenu" id="sidebarMyAccount">
                                <a id="navProfile" class="sidebar-sublink" href="{$Path}{Pages::PROFILE}">{translate key="Profile"}</a>
                                <a id="navPassword" class="sidebar-sublink" href="{$Path}{Pages::PASSWORD}">{translate key="ChangePassword"}</a>
                                <a id="navNotification" class="sidebar-sublink" href="{$Path}{Pages::NOTIFICATION_PREFERENCES}">{translate key="NotificationPreferences"}</a>
                                {if isset($ShowParticipation) && $ShowParticipation}
                                    <a id="navInvitations" class="sidebar-sublink" href="{$Path}{Pages::PARTICIPATION}">{translate key="OpenInvitations"}</a>
                                {/if}
                                {if isset($CreditsEnabled) && $CreditsEnabled}
                                    <a id="navUserCredits" class="sidebar-sublink" href="{$Path}{Pages::CREDITS}">{translate key="Credits"}</a>
                                {/if}
                            </div>
                        </div>
                        {/if}

                        {* CoAsia: day-to-day nav trimmed to just this one link — Overview itself is reached
                           via the logo and the mini calendar above, so a redundant "總覽" link isn't needed.
                           Everything else in the old "Schedule" group is hidden below but still there
                           (`{if false}`) if it's needed later. *}
                        <a class="sidebar-link" id="navMyMeetings" href="{$Path}{Pages::MY_MEETINGS}">
                            <i class="bi bi-calendar-check"></i>{translate key="MyMeetings"}
                        </a>
                        <a class="sidebar-link" id="navNotification" href="{$Path}{Pages::NOTIFICATION_PREFERENCES}">
                            <i class="bi bi-bell"></i>{translate key="NotificationPreferences"}
                        </a>

                        {if false}
                        <div class="sidebar-group" id="navScheduleDropdown">
                            <button class="sidebar-link sidebar-group-toggle" type="button" data-bs-toggle="collapse"
                                data-bs-target="#sidebarSchedule">
                                <i class="bi bi-calendar3"></i>{translate key="Schedule"}
                                <i class="bi bi-chevron-right ms-auto sidebar-chevron"></i>
                            </button>
                            <div class="collapse show sidebar-submenu" id="sidebarSchedule">
                                <a id="navMyCalendar" class="sidebar-sublink" href="{$Path}{Pages::MY_CALENDAR}">{translate key="MyCalendar"}</a>
                                <a id="navResourceCalendar" class="sidebar-sublink" href="{$Path}{Pages::CALENDAR}">{translate key="ResourceCalendar"}</a>
                                <a id="navFindATime" class="sidebar-sublink" href="{$Path}{Pages::OPENINGS}">{translate key="FindATime"}</a>
                                <a id="navSearchReservations" class="sidebar-sublink" href="{$Path}{Pages::SEARCH_RESERVATIONS}">{translate key="SearchReservations"}</a>
                            </div>
                        </div>
                        {/if}

                        {* CoAsia: hidden for now, not part of the day-to-day booking flow. Flip the condition back on to restore. *}
                        {if false}
                        <div class="sidebar-group" id="navReportsDropdown">
                            <button class="sidebar-link sidebar-group-toggle" type="button" data-bs-toggle="collapse"
                                data-bs-target="#sidebarCheck">
                                <i class="bi bi-eye"></i>{translate key="Check"}
                                <i class="bi bi-chevron-right ms-auto sidebar-chevron"></i>
                            </button>
                            <div class="collapse sidebar-submenu" id="sidebarCheck">
                                <a id="CheckResources" class="sidebar-sublink" href="{$Path}view_resources.php">{translate key=Resources}</a>
                                <a id="CheckSchedules" class="sidebar-sublink" href="{$Path}view_schedules.php">{translate key=Schedules}</a>
                            </div>
                        </div>
                        {/if}

                        {if isset($CanViewAdmin) && $CanViewAdmin}
                            <div class="sidebar-group" id="navApplicationManagementDropdown">
                                <button class="sidebar-link sidebar-group-toggle" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#sidebarAppMgmt">
                                    <i class="bi bi-tools"></i>{translate key="ApplicationManagement"}
                                    <i class="bi bi-chevron-right ms-auto sidebar-chevron"></i>
                                </button>
                                <div class="collapse sidebar-submenu" id="sidebarAppMgmt">
                                    <a id="navManageReservations" class="sidebar-sublink" href="{$Path}admin/manage_reservations.php">{translate key="ManageReservations"}</a>
                                    <a id="navManageSchedules" class="sidebar-sublink" href="{$Path}admin/manage_schedules.php">{translate key="ManageSchedules"}</a>
                                    <a id="navManageResources" class="sidebar-sublink" href="{$Path}admin/manage_resources.php">{translate key="ManageResources"}</a>
                                    <a id="navManageUsers" class="sidebar-sublink" href="{$Path}admin/manage_users.php">{translate key="ManageUsers"}</a>
                                    <a id="navManageAttributes" class="sidebar-sublink" href="{$Path}admin/manage_attributes.php">{translate key="CustomAttributes"}</a>
                                    <a id="navManageGroups" class="sidebar-sublink" href="{$Path}admin/manage_groups.php">{translate key="ManageGroups"}</a>

                                    {* CoAsia: hidden — checked the dev DB and none of these are actually in use:
                                       blackouts/quotas have zero rows, the 3 "accessories" are LibreBooking's own
                                       unattached demo placeholders, and announcements have zero rows (doubly moot
                                       now that Dashboard isn't the landing page). "groups" (ManageGroups, above)
                                       moved out of this block — it's now the real permission-group mechanism
                                       behind the "全體員工" default/everyone group + any future department-restricted
                                       resource groups. Remove `{if false}` to restore if CoAsia starts using one
                                       of these too. *}
                                    {if false}
                                        <a id="navManageBlackouts" class="sidebar-sublink" href="{$Path}admin/manage_blackouts.php">{translate key="ManageBlackouts"}</a>
                                        <a id="navManageQuotas" class="sidebar-sublink" href="{$Path}admin/manage_quotas.php">{translate key="ManageQuotas"}</a>
                                        <a id="navManageAccessories" class="sidebar-sublink" href="{$Path}admin/manage_accessories.php">{translate key="ManageAccessories"}</a>
                                        <a id="navManageAnnouncements" class="sidebar-sublink" href="{$Path}admin/manage_announcements.php">{translate key="ManageAnnouncements"}</a>
                                        {if isset($PaymentsEnabled) && $PaymentsEnabled}
                                            <a id="navManagePayments" class="sidebar-sublink" href="{$Path}admin/manage_payments.php">{translate key="ManagePayments"}</a>
                                        {/if}
                                    {/if}
                                </div>
                            </div>
                        {/if}

                        {if isset($CanViewResponsibilities) && $CanViewResponsibilities}
                            <div class="sidebar-group" id="navResponsibilitiesDropdown">
                                <button class="sidebar-link sidebar-group-toggle" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#sidebarResponsibilities">
                                    <i class="bi bi-person-badge"></i>{translate key="Responsibilities"}
                                    <i class="bi bi-chevron-right ms-auto sidebar-chevron"></i>
                                </button>
                                <div class="collapse sidebar-submenu" id="sidebarResponsibilities">
                                    {if isset($CanViewGroupAdmin) && $CanViewGroupAdmin}
                                        <a id="navResponsibilitiesGAUsers" class="sidebar-sublink" href="{$Path}admin/manage_group_users.php">{translate key="ManageUsers"}</a>
                                        <a id="navResponsibilitiesGAReservations" class="sidebar-sublink" href="{$Path}admin/manage_group_reservations.php">{translate key="GroupReservations"}</a>
                                        <a id="navResponsibilitiesGAGroups" class="sidebar-sublink" href="{$Path}admin/manage_admin_groups.php">{translate key="ManageGroups"}</a>
                                    {/if}
                                    {if (isset($CanViewResourceAdmin) && $CanViewResourceAdmin) || (isset($CanViewScheduleAdmin) && $CanViewScheduleAdmin)}
                                        <a id="navResponsibilitiesRAResources" class="sidebar-sublink" href="{$Path}admin/manage_admin_resources.php">{translate key="ManageResources"}</a>
                                        <a id="navResponsibilitiesRABlackouts" class="sidebar-sublink" href="{$Path}admin/manage_blackouts.php">{translate key="ManageBlackouts"}</a>
                                    {/if}
                                    {if isset($CanViewResourceAdmin) && $CanViewResourceAdmin}
                                        <a id="navResponsibilitiesRAReservations" class="sidebar-sublink" href="{$Path}admin/manage_resource_reservations.php">{translate key="ResourceReservations"}</a>
                                    {/if}
                                    {if isset($CanViewScheduleAdmin) && $CanViewScheduleAdmin}
                                        <a id="navResponsibilitiesSASchedules" class="sidebar-sublink" href="{$Path}admin/manage_admin_schedules.php">{translate key="ManageSchedules"}</a>
                                        <a id="navResponsibilitiesSAReservations" class="sidebar-sublink" href="{$Path}admin/manage_schedule_reservations.php">{translate key="ScheduleReservations"}</a>
                                    {/if}
                                    <a id="navResponsibilitiesAnnouncements" class="sidebar-sublink" href="{$Path}admin/manage_announcements.php">{translate key="ManageAnnouncements"}</a>
                                </div>
                            </div>
                        {/if}

                        {* CoAsia: hidden for now (was `{if isset($CanViewReports) && $CanViewReports}`), not part of the day-to-day booking flow. Remove the `false &&` to restore. *}
                        {if false && isset($CanViewReports) && $CanViewReports}
                            <div class="sidebar-group" id="navReportsDropdown">
                                <button class="sidebar-link sidebar-group-toggle" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#sidebarReports">
                                    <i class="bi bi-bar-chart-fill"></i>{translate key="Reports"}
                                    <i class="bi bi-chevron-right ms-auto sidebar-chevron"></i>
                                </button>
                                <div class="collapse sidebar-submenu" id="sidebarReports">
                                    <a id="navGenerateReport" class="sidebar-sublink" href="{$Path}reports/{Pages::REPORTS_GENERATE}">{translate key=GenerateReport}</a>
                                    <a id="navSavedReports" class="sidebar-sublink" href="{$Path}reports/{Pages::REPORTS_SAVED}">{translate key=MySavedReports}</a>
                                    <a id="navCommonReports" class="sidebar-sublink" href="{$Path}reports/{Pages::REPORTS_COMMON}">{translate key=CommonReports}</a>
                                </div>
                            </div>
                        {/if}
                    {/if}

                    {if isset($ShowScheduleLink) && $ShowScheduleLink}
                        <div class="sidebar-group" id="navScheduleDropdownPublic">
                            <button class="sidebar-link sidebar-group-toggle" type="button" data-bs-toggle="collapse"
                                data-bs-target="#sidebarPublicSchedule">
                                <i class="bi bi-calendar3"></i>{translate key="Schedule"}
                                <i class="bi bi-chevron-right ms-auto sidebar-chevron"></i>
                            </button>
                            <div class="collapse sidebar-submenu" id="sidebarPublicSchedule">
                                <a id="navViewSchedule" class="sidebar-sublink" href="view-schedule.php">{translate key='ViewSchedule'}</a>
                                <a id="navViewCalendar" class="sidebar-sublink" href="view-calendar.php">{translate key='ViewCalendar'}</a>
                            </div>
                        </div>
                    {/if}
                </nav>

                <div class="sidebar-footer">
                    {if isset($CanViewAdmin) && $CanViewAdmin}
                        <div class="sidebar-group" id="navConfigDropdown">
                            <button class="sidebar-link sidebar-group-toggle" type="button" data-bs-toggle="collapse"
                                data-bs-target="#sidebarConfig">
                                <i class="bi bi-gear-fill"></i>
                                <span class="visually-hidden">Configuration</span>
                                <span>{translate key="ApplicationManagement"}</span>
                                {if isset($ShowNewVersion) && $ShowNewVersion}<span
                                        class="badge badge-new-version new-version"
                                    id="newVersionBadge">{translate key=NewVersion}</span>{/if}
                                <i class="bi bi-chevron-right ms-auto sidebar-chevron"></i>
                            </button>
                            <div class="collapse sidebar-submenu" id="sidebarConfig">
                                {if isset($EnableConfigurationPage) && $EnableConfigurationPage}
                                    <a id="navManageConfiguration" class="sidebar-sublink" href="{$Path}admin/manage_configuration.php">{translate key="ManageConfiguration"}</a>
                                {/if}
                                <a id="navEmailTemplates" class="sidebar-sublink" href="{$Path}admin/manage_email_templates.php">{translate key="ManageEmailTemplates"}</a>
                                <a id="navLookAndFeel" class="sidebar-sublink" href="{$Path}admin/manage_theme.php">{translate key="LookAndFeel"}</a>
                                <a id="navImport" class="sidebar-sublink" href="{$Path}admin/ics_import.php">{translate key="Import"}</a>
                                <a id="navServerSettings" class="sidebar-sublink" href="{$Path}admin/server_settings.php">{translate key="ServerSettings"}</a>
                                <a id="navDataCleanup" class="sidebar-sublink" href="{$Path}admin/data_cleanup.php">{translate key="DataCleanup"}</a>
                                {if isset($ShowNewVersion) && $ShowNewVersion}
                                    <a id="navNewVersion" class="sidebar-sublink new-version" href="https://github.com/LibreBooking/librebooking/releases">{translate key=WhatsNew}</a>
                                {/if}
                            </div>
                        </div>
                    {/if}

                    {* CoAsia: hidden for now — language switcher/help aren't part of the trimmed-down day-to-day nav. Remove `{if false}` to restore. *}
                    {if false}
                    {if isset($LoggedIn) && $LoggedIn && count($AvailableLanguages) > 1}
                        <div class="sidebar-group" id="navLanguageDropdown">
                            <button class="sidebar-link sidebar-group-toggle" type="button" data-bs-toggle="collapse"
                                data-bs-target="#sidebarLanguage" aria-label="{translate key=ChangeLanguage}"
                                title="{translate key=ChangeLanguage}">
                                <i class="bi bi-globe-americas" aria-hidden="true"></i>
                                <span>{translate key=ChangeLanguage}</span>
                                <i class="bi bi-chevron-right ms-auto sidebar-chevron"></i>
                            </button>
                            <div class="collapse sidebar-submenu" id="sidebarLanguage" style="max-height: 40vh; overflow-y: auto;">
                                <div id="languageDropdownMenu">
                                    {foreach from=$AvailableLanguages item=lang}
                                        <a class="sidebar-sublink {if $CurrentLanguage == $lang->GetLanguageCode()}active{/if}"
                                            href="#" data-lang-code="{$lang->GetLanguageCode()}">
                                            {$lang->GetDisplayName()}
                                        </a>
                                    {/foreach}
                                </div>
                            </div>
                        </div>
                    {/if}

                    <div class="sidebar-group" id="navHelpDropdown">
                        <button class="sidebar-link sidebar-group-toggle" type="button" data-bs-toggle="collapse"
                            data-bs-target="#sidebarHelp">
                            <i class="bi bi-question-circle"></i>{translate key="Help"}
                            <i class="bi bi-chevron-right ms-auto sidebar-chevron"></i>
                        </button>
                        <div class="collapse sidebar-submenu" id="sidebarHelp">
                            <a id="navHelp" class="sidebar-sublink" href="https://librebooking.readthedocs.io/en/latest/">{translate key=Help}</a>
                            {if isset($CanViewAdmin) && $CanViewAdmin}
                                <a id="navHelpAdmin" class="sidebar-sublink" href="https://librebooking.readthedocs.io/en/latest/ADMINISTRATION.html">{translate key=Administration}</a>
                            {/if}
                            <a id="navAbout" class="sidebar-sublink" href="{$Path}help.php?ht=about">{translate key=About}</a>
                        </div>
                    </div>
                    {/if}

                    {if isset($LoggedIn) && $LoggedIn}
                        <a class="sidebar-link" id="navSignOut" href="{$Path}logout.php">
                            <i class="bi bi-box-arrow-right"></i>{translate key="SignOut"}
                        </a>
                    {else}
                        <a class="sidebar-link" id="navLogIn" href="{$Path}index.php">
                            <i class="bi bi-box-arrow-in-right"></i>{translate key="LogIn"}
                        </a>
                    {/if}
                </div>
            </div>
        </aside>

        <div class="app-content">
    {else}
    <div class="app-content app-content-bare">
    {/if}

<div id="main" class="container-fluid my-3" role="main">
