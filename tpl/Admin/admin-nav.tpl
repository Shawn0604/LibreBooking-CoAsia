{* 管理中心 secondary navigation panel — included once from globalheader.tpl (gated on
   $IsAdminPage) rather than from each individual admin page/template. See
   Web/css/admin.css for .admin-side-nav's layout. Active-page highlighting compares
   $AdminCurrentScript (set in PHP by Pages/Admin/AdminPage.php -- this project's Smarty build
   doesn't allow arbitrary PHP functions like basename() as template modifiers) against each
   link's own href basename. *}

<div class="admin-side-nav-header">
    <i class="bi bi-speedometer2"></i>
    <span>{translate key="ApplicationManagement"}</span>
</div>

<a class="admin-side-nav-link {if $AdminCurrentScript == 'manage_overview.php'}active{/if}"
    href="{$Path}admin/manage_overview.php">{translate key="AdminOverview"}</a>

<div class="admin-side-nav-label">{translate key="AdminSectionReservations"}</div>
<a class="admin-side-nav-link {if $AdminCurrentScript == 'manage_reservations.php'}active{/if}"
    href="{$Path}admin/manage_reservations.php">{translate key="ManageReservations"}</a>

<div class="admin-side-nav-label">{translate key="AdminSectionResources"}</div>
<a class="admin-side-nav-link {if $AdminCurrentScript == 'manage_resources.php'}active{/if}"
    href="{$Path}admin/manage_resources.php">{translate key="ManageResources"}</a>

<div class="admin-side-nav-label">{translate key="AdminSectionUsers"}</div>
<a class="admin-side-nav-link {if $AdminCurrentScript == 'manage_users.php'}active{/if}"
    href="{$Path}admin/manage_users.php">{translate key="ManageUsers"}</a>
<a class="admin-side-nav-link {if $AdminCurrentScript == 'manage_groups.php'}active{/if}"
    href="{$Path}admin/manage_groups.php">{translate key="ManageGroups"}</a>

<div class="admin-side-nav-label">{translate key="AdminSectionSchedules"}</div>
<a class="admin-side-nav-link {if $AdminCurrentScript == 'manage_schedules.php'}active{/if}"
    href="{$Path}admin/manage_schedules.php">{translate key="ManageSchedules"}</a>

<div class="admin-side-nav-label">{translate key="AdminSectionNotifications"}</div>
<a class="admin-side-nav-link {if $AdminCurrentScript == 'manage_email_templates.php'}active{/if}"
    href="{$Path}admin/manage_email_templates.php">{translate key="ManageEmailTemplates"}</a>

<div class="admin-side-nav-label">{translate key="AdminSectionMaintenance"}</div>
<a class="admin-side-nav-link {if $AdminCurrentScript == 'manage_attributes.php'}active{/if}"
    href="{$Path}admin/manage_attributes.php">{translate key="CustomAttributes"}</a>
{if isset($EnableConfigurationPage) && $EnableConfigurationPage}
    <a class="admin-side-nav-link {if $AdminCurrentScript == 'manage_configuration.php'}active{/if}"
        href="{$Path}admin/manage_configuration.php">{translate key="ManageConfiguration"}</a>
{/if}
<a class="admin-side-nav-link {if $AdminCurrentScript == 'ics_import.php'}active{/if}"
    href="{$Path}admin/ics_import.php">{translate key="ImportICS"}</a>
<a class="admin-side-nav-link {if $AdminCurrentScript == 'server_settings.php'}active{/if}"
    href="{$Path}admin/server_settings.php">{translate key="ServerSettings"}</a>
{if isset($ShowNewVersion) && $ShowNewVersion}
    <a class="admin-side-nav-link new-version" href="https://github.com/LibreBooking/librebooking/releases">{translate key=WhatsNew}</a>
{/if}
<a class="admin-side-nav-link admin-side-nav-link-danger {if $AdminCurrentScript == 'data_cleanup.php'}active{/if}"
    href="{$Path}admin/data_cleanup.php">
    <i class="bi bi-exclamation-triangle-fill me-1"></i>{translate key="DataCleanup"}</a>

{* CoAsia: hidden — see the equivalent note that used to live in globalheader.tpl's old
   #navAdminCenterDropdown block (removed when this nav moved out to its own panel). None of
   these have real data in the dev DB yet: blackouts/quotas are zero rows, the 3 "accessories"
   are LibreBooking's own unattached demo placeholders, announcements are zero rows. Remove
   `{if false}` to restore if CoAsia starts using one of these — Blackouts would join
   開放時間與規則, Announcements would join 通知與信件, the rest would join 預約管理. *}
{if false}
    <a class="admin-side-nav-link" href="{$Path}admin/manage_blackouts.php">{translate key="ManageBlackouts"}</a>
    <a class="admin-side-nav-link" href="{$Path}admin/manage_quotas.php">{translate key="ManageQuotas"}</a>
    <a class="admin-side-nav-link" href="{$Path}admin/manage_accessories.php">{translate key="ManageAccessories"}</a>
    <a class="admin-side-nav-link" href="{$Path}admin/manage_announcements.php">{translate key="ManageAnnouncements"}</a>
    {if isset($PaymentsEnabled) && $PaymentsEnabled}
        <a class="admin-side-nav-link" href="{$Path}admin/manage_payments.php">{translate key="ManagePayments"}</a>
    {/if}
{/if}
