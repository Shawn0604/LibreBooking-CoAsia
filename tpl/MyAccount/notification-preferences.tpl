{include file='globalheader.tpl' cssFiles='css/notification-preferences.css'}

<div class="shell" id="page-notification-preferences">

    <div class="page-head">
        <h1>{translate key=NotificationPreferences}</h1>
    </div>

    <div id="npError" class="alert alert-danger np-alert d-none">
        <i class="bi bi-exclamation-triangle-fill"></i> <span id="npErrorText"></span>
    </div>

    {if !$EmailEnabled}
        <div class="alert alert-danger np-alert">
            <i class="bi bi-exclamation-triangle-fill"></i> {translate key=EmailDisabled}
        </div>
    {else}
        {* No form/submit button anymore — every control here saves itself immediately via
           Web/scripts/notification-preferences.js (fetch to ajax/notification_preferences_save.php),
           showing the shared toast (tpl/globalfooter.tpl#notificationPreferencesSavedToast)
           instead of a full-page reload + banner. *}
        <section class="np-card">
            <h2 class="np-card-title">{translate key=EmailNotifications}</h2>

            <label class="np-row" for="emailNotificationsEnabled">
                <span class="np-row-icon"><i class="bi bi-envelope"></i></span>
                <span class="np-row-text">
                    <span class="np-row-label">{translate key=EmailNotificationsToggleLabel}</span>
                    <span class="np-row-desc">{translate key=EmailNotificationsToggleDescription}</span>
                </span>
                <span class="form-check form-switch np-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="emailNotificationsEnabled"
                        {if $EmailNotificationsEnabled}checked="checked"{/if}>
                    <span class="visually-hidden">{translate key=EmailNotificationsToggleLabel}</span>
                </span>
            </label>
        </section>
    {/if}

    {if $LineEnabled}
        <section class="np-card">
            <h2 class="np-card-title">{translate key=LineNotifications}</h2>

            {if $LineLinked}
                <div class="np-line-status np-line-status-linked">
                    <i class="bi bi-check-circle-fill"></i> {translate key=LineLinked}
                </div>

                <label class="np-row" for="lineNotificationsEnabled">
                    <span class="np-row-icon"><i class="bi bi-bell"></i></span>
                    <span class="np-row-text">
                        <span class="np-row-label">{translate key=LineNotificationsToggleLabel}</span>
                    </span>
                    <span class="form-check form-switch np-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="lineNotificationsEnabled"
                            {if $LineNotificationsEnabled}checked="checked"{/if}>
                        <span class="visually-hidden">{translate key=LineNotificationsToggleLabel}</span>
                    </span>
                </label>

                <div class="np-line-actions">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="lineTestNotificationBtn">
                        <i class="bi bi-send"></i> {translate key=LineTestNotification}
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="lineUnlinkBtn">
                        {translate key=LineUnlink}
                    </button>
                </div>
            {else}
                <div class="np-line-unbound">
                    <img class="np-line-qr" src="{$LineQrDataUri}" alt="{translate key=LineNotifications}" width="180" height="180">
                    <div class="np-line-steps">
                        <a class="btn btn-success btn-sm np-line-add-friend" href="{$LineFriendUrl}" target="_blank" rel="noopener">
                            {translate key=LineAddFriend}
                        </a>
                        <div class="np-line-start-binding">
                            <p class="np-line-start-binding-label">{translate key=LineStartBinding}</p>
                            <p class="np-card-subtitle">{translate key=LineLinkInstructions}</p>
                            <span class="np-line-code">{$LineLinkCode}</span>
                        </div>
                    </div>
                </div>
            {/if}
        </section>
    {/if}

</div>

<script>
    window.notificationPreferencesOpts = {
        saveUrl: '{$ScriptUrl}/ajax/notification_preferences_save.php',
        testUrl: '{$ScriptUrl}/ajax/line_test_notification.php',
        csrfToken: '{$CSRFToken|escape:'javascript'}',
        translations: {
            networkError: '{translate key='NetworkErrorRetry'|escape:'javascript'}'
        }
    };
</script>
{jsfile src="notification-preferences.js"}

{include file="javascript-includes.tpl"}
{include file='globalfooter.tpl'}
