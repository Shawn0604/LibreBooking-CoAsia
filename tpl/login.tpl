{include file='globalheader.tpl' HideNavBar=true}

<div id="page-login">

    {if $EnableCaptcha}
        {validation_group class="alert alert-danger"}
        {validator id="captcha" key="CaptchaMustMatch"}
        {/validation_group}
    {/if}

    {if $Announcements|default:array()|count > 0}
        <div id="announcements" class="login-announcements">
            <ul class="list-group list-group-flush">
                {foreach from=$Announcements item=each}
                    <li class="announcement list-group-item">{$each->Text()|sanitize_rich_text}</li>
                {/foreach}
            </ul>
        </div>
    {/if}

    <div class="login-card">
        <form role="form" name="login" id="login" method="post" action="{$smarty.server.SCRIPT_NAME}">

            <div class="login-brand">
                <img src="{$Path}{$LogoUrl}?{$Version}" alt="{$Title}" class="login-logo">
                <h1 class="login-heading">{translate key=RoomBookingSystem}</h1>
                <p class="login-subheading">{translate key=SignInWithCompanyAccount}</p>
            </div>

            {if $ShowLoginError}
                <div id="loginError" class="login-alert">
                    {if !empty($LoginErrorMessage)}
                        {$LoginErrorMessage}
                    {else}
                        {translate key='LoginError'}
                    {/if}
                </div>
            {/if}

            {if $ShowUsernamePrompt}
                <div class="login-field">
                    <label for="email" class="login-label">{translate key=CompanyAccount}</label>
                    <input type="text" required="" class="login-input" id="email" {formname key=EMAIL}
                        placeholder="{translate key=CompanyAccountOrEmailPlaceholder}" />
                </div>
            {/if}

            {if $ShowPasswordPrompt}
                <div class="login-field">
                    <label for="password" class="login-label">{translate key=Password}</label>
                    <div class="login-password-wrap">
                        <input type="password" required="" id="password" {formname key=PASSWORD}
                            class="login-input" value="" />
                        <button type="button" class="login-password-toggle" id="loginPasswordToggle"
                            aria-label="{translate key=Password}" aria-pressed="false">
                            <i class="bi bi-eye" id="loginPasswordToggleIcon"></i>
                        </button>
                    </div>
                </div>
            {/if}

            {if $EnableCaptcha}
                <div class="text-center mb-2">
                    {control type="CaptchaControl"}
                </div>
            {else}
                <input type="hidden" {formname key=CAPTCHA} value="" />
            {/if}

            {if $ShowUsernamePrompt && $ShowPasswordPrompt}
                <div class="login-row">
                    <div class="form-check login-remember">
                        <input class="form-check-input" id="rememberMe" type="checkbox"
                            {formname key=PERSIST_LOGIN}>
                        <label class="form-check-label" for="rememberMe">{translate key=RememberMe}</label>
                    </div>
                </div>

                <button type="submit" class="login-submit" name="{Actions::LOGIN}"
                    value="submit">{translate key='LogIn'}</button>
                <input type="hidden" {formname key=RESUME} value="{$ResumeUrl}" />
            {/if}

            {if $facebookError}
                <p class="text-center my-3">{translate key="FacebookLoginErrorMessage"}</p>
            {/if}
        </form>

        <div class="login-footer">
            <span class="login-footer-brand">CoAsia Internal System</span>
            {if count($Languages) > 1}
                <div class="login-language">
                    <i class="bi bi-globe2"></i>
                    <select {formname key=LANGUAGE} class="login-language-select" id="languageDropDown">
                        {object_html_options options=$Languages key='GetLanguageCode' label='GetDisplayName' selected=$SelectedLanguage}
                    </select>
                </div>
            {/if}
        </div>
    </div>
</div>

{setfocus key='EMAIL'}

{include file="javascript-includes.tpl"}

<script type="text/javascript">
    var url = 'index.php?{QueryStringKeys::LANGUAGE}=';
    $(document).ready(function() {
        $('#languageDropDown').change(function() {
            window.location.href = url + $(this).val();
        });

        var langCode = '{$smarty.cookies.language|escape:"javascript"}';

        if (!langCode) {
            langCode = (navigator.language + "").replace("-", "_").toLowerCase();

            var availableLanguages = [{foreach from=$Languages item=lang}"{$lang->GetLanguageCode()}",{/foreach}];
            if (langCode !== "" && langCode != '{$SelectedLanguage|lower}') {
            if (availableLanguages.indexOf(langCode) !== -1) {
                window.location.href = url + langCode;
            }
        }
    }
    });

    // Purely cosmetic show/hide of the typed password — doesn't touch the value that
    // actually gets submitted, so it has no effect on how the credential is validated.
    var passwordToggle = document.getElementById('loginPasswordToggle');
    var passwordInput = document.getElementById('password');
    var passwordToggleIcon = document.getElementById('loginPasswordToggleIcon');
    if (passwordToggle && passwordInput && passwordToggleIcon) {
        passwordToggle.addEventListener('click', function () {
            var showing = passwordInput.type === 'text';
            passwordInput.type = showing ? 'password' : 'text';
            passwordToggle.setAttribute('aria-pressed', String(!showing));
            passwordToggleIcon.classList.toggle('bi-eye', showing);
            passwordToggleIcon.classList.toggle('bi-eye-slash', !showing);
        });
    }
</script>
{include file='globalfooter.tpl'}
