{include file='globalheader.tpl' cssFiles='css/notification-preferences.css'}

<div class="shell" id="page-notification-preferences">

    <div class="page-head">
        <h1>{translate key=NotificationPreferences}</h1>
        <p class="np-page-subtitle">{translate key=NotificationPreferencesIntro}</p>
    </div>

    <div id="npError" class="alert alert-danger np-alert d-none">
        <i class="bi bi-exclamation-triangle-fill"></i> <span id="npErrorText"></span>
    </div>

    {if !$EmailEnabled}
        <div class="alert alert-danger np-alert">
            <i class="bi bi-exclamation-triangle-fill"></i> {translate key=EmailDisabled}
        </div>
    {/if}

    {* No form/submit button — every control saves itself immediately via
       Web/scripts/notification-preferences.js (fetch to ajax/notification_preferences_save.php),
       showing the shared toast (tpl/globalfooter.tpl#notificationPreferencesSavedToast). *}

    {if $EmailEnabled || $LineEnabled || $WeComEnabled}
        {* ---- 預約異動:建立/修改/取消/核准。Email / LINE / 企業微信 是這一種通知的「通知方式」,
           各自一個開關;使用者不需要在別處挑管道,提醒卡片也不再有子開關。企業微信沒有 LINE 那種
           自助綁定,ID 由管理員在後台填,所以只有在管理員設好 ID 後才出現開關。 ---- *}
        <section class="np-card">
            <div class="np-row np-row-heading">
                <span class="np-row-icon"><i class="bi bi-calendar-check"></i></span>
                <span class="np-row-text">
                    <span class="np-row-label np-heading-label">{translate key=NotificationEventsCardTitle}</span>
                    <span class="np-row-desc">{translate key=NotificationEventsCardDescription}</span>
                </span>
            </div>

            <div class="np-methods">
                <p class="np-methods-label">{translate key=NotificationMethods}</p>

                {if $EmailEnabled}
                    <div class="np-method-card np-method-card--email">
                        <label class="np-row np-method-head" for="emailNotificationsEnabled">
                            <span class="np-row-icon"><i class="bi bi-envelope"></i></span>
                            <span class="np-row-text">
                                <span class="np-row-label">Email</span>
                                <span class="np-row-desc">{translate key=EmailNotificationsToggleDescription}</span>
                            </span>
                            <span class="form-check form-switch np-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="emailNotificationsEnabled"
                                    {if $EmailNotificationsEnabled}checked="checked"{/if}>
                                <span class="visually-hidden">Email</span>
                            </span>
                        </label>

                        {* Email has no "binding" to release like LINE/WeCom — the address comes
                           from the account and the toggle above is the on/off. Just a delivery
                           check here. *}
                        <div class="np-method-actions">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="emailTestNotificationBtn">
                                <i class="bi bi-send"></i> {translate key=EmailTestNotification}
                            </button>
                        </div>
                    </div>
                {/if}

                {if $LineEnabled}
                    <div class="np-method-card np-method-card--line">
                        {if $LineLinked}
                            <label class="np-row np-method-head" for="lineNotificationsEnabled">
                                <span class="np-row-icon"><i class="bi bi-chat-dots"></i></span>
                                <span class="np-row-text">
                                    <span class="np-row-label">LINE</span>
                                    <span class="np-row-desc">{translate key=LineNotificationsToggleDescription}（{translate key=LineLinked}）</span>
                                </span>
                                <span class="form-check form-switch np-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="lineNotificationsEnabled"
                                        {if $LineNotificationsEnabled}checked="checked"{/if}>
                                    <span class="visually-hidden">LINE</span>
                                </span>
                            </label>

                            <div class="np-method-actions">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="lineTestNotificationBtn">
                                    <i class="bi bi-send"></i> {translate key=LineTestNotification}
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="lineUnlinkBtn">
                                    {translate key=LineUnlink}
                                </button>
                            </div>
                        {else}
                            <div class="np-row np-method-head np-method-row-static">
                                <span class="np-row-icon"><i class="bi bi-chat-dots"></i></span>
                                <span class="np-row-text">
                                    <span class="np-row-label">LINE</span>
                                    <span class="np-row-desc">{translate key=LineNotificationsToggleDescription}（{translate key=LineNotLinked}）</span>
                                </span>
                            </div>

                            <div class="np-method-bind np-line-unbound">
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
                    </div>
                {/if}

                {if $WeComEnabled}
                    <div class="np-method-card np-method-card--wecom">
                        {if $WeComLinked}
                            <label class="np-row np-method-head" for="wecomNotificationsEnabled">
                                <span class="np-row-icon"><i class="bi bi-wechat"></i></span>
                                <span class="np-row-text">
                                    <span class="np-row-label">{translate key=WeComNotifications}</span>
                                    <span class="np-row-desc">{translate key=WeComNotificationsToggleDescription}（{$WeComUserId|escape}）</span>
                                </span>
                                <span class="form-check form-switch np-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="wecomNotificationsEnabled"
                                        {if $WeComNotificationsEnabled}checked="checked"{/if}>
                                    <span class="visually-hidden">{translate key=WeComNotifications}</span>
                                </span>
                            </label>

                            <div class="np-method-actions">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="wecomTestNotificationBtn">
                                    <i class="bi bi-send"></i> {translate key=WeComTestNotification}
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="wecomUnlinkBtn">
                                    {translate key=WeComUnlink}
                                </button>
                            </div>
                        {else}
                            <div class="np-row np-method-head np-method-row-static">
                                <span class="np-row-icon"><i class="bi bi-wechat"></i></span>
                                <span class="np-row-text">
                                    <span class="np-row-label">{translate key=WeComNotifications}</span>
                                    <span class="np-row-desc">{translate key=WeComNotificationsToggleDescription}（{translate key=WeComNotLinked}）</span>
                                </span>
                            </div>

                            <div class="np-method-bind np-wecom-bind">
                                <label class="np-wecom-bind-label" for="wecomUserIdInput">{translate key=WeComUserId}</label>
                                <div class="np-wecom-bind-row">
                                    <input type="text" id="wecomUserIdInput" class="form-control form-control-sm np-wecom-input"
                                        placeholder="{translate key=WeComUserIdPlaceholder}" autocomplete="off" maxlength="64" spellcheck="false">
                                    <button type="button" class="btn btn-primary btn-sm" id="wecomLinkBtn">
                                        {translate key=WeComSaveId}
                                    </button>
                                </div>
                                <p class="np-wecom-bind-hint">{translate key=WeComUserIdSelfHint}</p>
                            </div>
                        {/if}
                    </div>
                {/if}
            </div>
        </section>
    {/if}

    {if $RemindersEnabled}
        {* "會議開始前提醒" — the user never picks recipients or Email/LINE here; the
           reminder job (Jobs/sendreminders.php -> ReservationReminderSender) fans out to the
           owner + internal attendees over each person's own enabled channels, and to
           external guests over Email only. This card is purely: on/off + how far ahead. *}
        <section class="np-card">
            <h2 class="np-card-title">{translate key=ReservationReminders}</h2>

            <label class="np-row" for="reservationStartReminderEnabled">
                <span class="np-row-icon"><i class="bi bi-alarm"></i></span>
                <span class="np-row-text">
                    <span class="np-row-label">{translate key=StartReminderToggleLabel}</span>
                    <span class="np-row-desc">{translate key=StartReminderToggleDescription}</span>
                </span>
                <span class="form-check form-switch np-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="reservationStartReminderEnabled"
                        {if $ReservationStartReminderMinutes > 0}checked="checked"{/if}>
                    <span class="visually-hidden">{translate key=StartReminderToggleLabel}</span>
                </span>
            </label>

            <div class="np-reminder-detail" id="reservationStartReminderFields">
                <label class="np-reminder-time-label" for="reservationStartReminderMinutes">{translate key=StartReminderTimeLabel}</label>
                <select id="reservationStartReminderMinutes" class="form-select form-select-sm np-reminder-select">
                    <option value="5" {if $ReservationStartReminderMinutes eq 5}selected{/if}>{translate key=ReminderLead5Minutes}</option>
                    <option value="10" {if $ReservationStartReminderMinutes eq 10}selected{/if}>{translate key=ReminderLead10Minutes}</option>
                    <option value="15" {if $ReservationStartReminderMinutes eq 15 or $ReservationStartReminderMinutes lte 0}selected{/if}>{translate key=ReminderLead15Minutes}</option>
                    <option value="30" {if $ReservationStartReminderMinutes eq 30}selected{/if}>{translate key=ReminderLead30Minutes}</option>
                    <option value="60" {if $ReservationStartReminderMinutes eq 60}selected{/if}>{translate key=ReminderLead1Hour}</option>
                    <option value="120" {if $ReservationStartReminderMinutes eq 120}selected{/if}>{translate key=ReminderLead2Hours}</option>
                    <option value="1440" {if $ReservationStartReminderMinutes eq 1440}selected{/if}>{translate key=ReminderLead1Day}</option>
                </select>
                <p class="np-reminder-hint">{translate key=StartReminderRecipientsHint}</p>
            </div>
        </section>
    {/if}

</div>

<script>
    window.notificationPreferencesOpts = {
        saveUrl: '{$ScriptUrl}/ajax/notification_preferences_save.php',
        testUrl: '{$ScriptUrl}/ajax/line_test_notification.php',
        wecomTestUrl: '{$ScriptUrl}/ajax/wecom_test_notification.php',
        emailTestUrl: '{$ScriptUrl}/ajax/email_test_notification.php',
        csrfToken: '{$CSRFToken|escape:'javascript'}',
        translations: {
            networkError: '{translate key='NetworkErrorRetry'|escape:'javascript'}',
            wecomIdRequired: '{translate key='WeComIdRequired'|escape:'javascript'}'
        }
    };
</script>
{jsfile src="notification-preferences.js"}

{include file="javascript-includes.tpl"}
{include file='globalfooter.tpl'}
