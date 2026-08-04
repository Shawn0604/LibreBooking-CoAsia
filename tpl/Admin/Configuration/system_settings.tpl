{include file='globalheader.tpl'}

<div id="page-system-settings" class="admin-page">
    {include file='Admin/admin_page_shell.tpl' TitleKey='AdminSystemSettings' DescriptionKey='AdminSystemSettingsDescription'}

    <div class="admin-entry-grid">
        <a class="admin-entry-card" href="{$Path}admin/manage_email_templates.php">
            <i class="bi bi-envelope-fill"></i>
            <span>{translate key=AdminNavEmailTemplates}</span>
        </a>
        <a class="admin-entry-card" href="{$Path}admin/ad_sync_status.php">
            <i class="bi bi-arrow-repeat"></i>
            <span>{translate key=AdminAdSyncStatus}</span>
        </a>
        <a class="admin-entry-card" href="{$Path}admin/manage_attributes.php">
            <i class="bi bi-tags-fill"></i>
            <span>{translate key=CustomAttributes}</span>
        </a>
        <a class="admin-entry-card" href="{$Path}admin/server_settings.php">
            <i class="bi bi-heart-pulse-fill"></i>
            <span>{translate key=ServerSettings}</span>
        </a>
        <a class="admin-entry-card admin-entry-card-danger" href="{$Path}admin/data_cleanup.php">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span>{translate key=DataCleanup}</span>
        </a>
    </div>
</div>

{include file="javascript-includes.tpl"}
{include file='globalfooter.tpl'}
