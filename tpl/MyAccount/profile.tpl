{include file='globalheader.tpl' Validator=true cssFiles='css/profile.css'}

<div class="shell" id="page-profile">

    <div class="page-head">
        <h1>{translate key=EditProfile}</h1>
    </div>

    <div class="d-none alert alert-success pf-alert" role="alert" id="profileUpdatedMessage">
        <i class="bi bi-check-circle-fill text-success"></i> {translate key=YourProfileWasUpdated}
    </div>

    <div class="validationSummary alert alert-danger d-none pf-alert" id="validationErrors">
        <ul>
            {async_validator id="fname" key="FirstNameRequired"}
            {async_validator id="lname" key="LastNameRequired"}
            {async_validator id="username" key="UserNameRequired"}
            {async_validator id="emailformat" key="ValidEmailRequired"}
            {async_validator id="uniqueemail" key="UniqueEmailRequired"}
            {async_validator id="uniqueusername" key="UniqueUsernameRequired"}
            {async_validator id="phoneRequired" key="PhoneRequired"}
            {async_validator id="positionRequired" key="PositionRequired"}
            {async_validator id="organizationRequired" key="OrganizationRequired"}
            {async_validator id="additionalattributes" key=""}
        </ul>
    </div>

    <form method="post" ajaxAction="{ProfileActions::Update}" id="form-profile" class="was-validated"
        action="{$smarty.server.SCRIPT_NAME}" role="form" data-bv-submitbuttons='button[type="submit"]'
        data-bv-onerror="enableButton" data-bv-onsuccess="enableButton" data-bv-live="enabled">

        <section class="np-card">
            <h2 class="np-card-title">{translate key=Account}</h2>

            <div class="pf-row">
                <span class="pf-row-icon"><i class="bi bi-person-badge"></i></span>
                <div class="pf-row-field">
                    <label class="pf-row-label" for="username">{translate key="Username"}</label>
                    {if $AllowUsernameChange}
                        {textbox name="USERNAME" class="form-control form-control-sm" value="Username" required="required" data-bv-notempty="true" autofocus="autofocus" data-bv-notempty-message="{translate key=UserNameRequired}"}
                    {else}
                        <span class="pf-row-static">{$Username}</span>
                        <input type="hidden" {formname key=USERNAME} value="{$Username}" />
                    {/if}
                </div>
            </div>

            <div class="pf-row">
                <span class="pf-row-icon"><i class="bi bi-envelope"></i></span>
                <div class="pf-row-field">
                    <label class="pf-row-label" for="email">{translate key="Email"}</label>
                    {if $AllowEmailAddressChange}
                        {textbox type="email" name="EMAIL" class="form-control form-control-sm" value="Email" required="required" data-bv-notempty="true" data-bv-notempty-message="{translate key=ValidEmailRequired}"
                        data-bv-emailaddress="true"
                        data-bv-emailaddress-message="{translate key=ValidEmailRequired}" }
                    {else}
                        <span class="pf-row-static">{$Email}</span>
                        <input type="hidden" {formname key=EMAIL} value="{$Email}" />
                    {/if}
                </div>
            </div>
        </section>

        <section class="np-card">
            <h2 class="np-card-title">{translate key=PersonalInformation}</h2>

            <div class="pf-row">
                <span class="pf-row-icon"><i class="bi bi-person"></i></span>
                <div class="pf-row-field">
                    <label class="pf-row-label" for="fname">{translate key="FirstName"}</label>
                    {if $AllowNameChange}
                        {textbox name="FIRST_NAME" class="form-control form-control-sm" value="FirstName" required="required" data-bv-notempty="true" data-bv-notempty-message="{translate key=FirstNameRequired}"}
                    {else}
                        <span class="pf-row-static">{$FirstName}</span>
                        <input type="hidden" {formname key=FIRST_NAME} value="{$FirstName}" />
                    {/if}
                </div>
            </div>

            <div class="pf-row">
                <span class="pf-row-icon"><i class="bi bi-person"></i></span>
                <div class="pf-row-field">
                    <label class="pf-row-label" for="lname">{translate key="LastName"}</label>
                    {if $AllowNameChange}
                        {textbox name="LAST_NAME" class="form-control form-control-sm" value="LastName" required="required" data-bv-notempty="true" data-bv-notempty-message="{translate key=LastNameRequired}"}
                    {else}
                        <span class="pf-row-static">{$LastName}</span>
                        <input type="hidden" {formname key=LAST_NAME} value="{$LastName}" />
                    {/if}
                </div>
            </div>

            <div class="pf-row">
                <span class="pf-row-icon"><i class="bi bi-telephone"></i></span>
                <div class="pf-row-field">
                    <label class="pf-row-label" for="phone">{translate key="Phone"}</label>
                    {if $AllowPhoneChange}
                        <input type="text" id="phone" {formname key="PHONE"} class="form-control form-control-sm"
                            value="{$Phone}" {if $RequirePhone}required="required" data-bv-notempty="true"
                            data-bv-notempty-message="{translate key=PhoneRequired}" {/if} />
                    {else}
                        <span class="pf-row-static">{$Phone}</span>
                        <input type="hidden" {formname key=PHONE} value="{$Phone}" />
                    {/if}
                </div>
            </div>

            <div class="pf-row">
                <span class="pf-row-icon"><i class="bi bi-building"></i></span>
                <div class="pf-row-field">
                    <label class="pf-row-label" for="txtOrganization">{translate key="Organization"}</label>
                    {if $AllowOrganizationChange}
                        <input type="text" id="txtOrganization" {formname key="ORGANIZATION"}
                            class="form-control form-control-sm" value="{$Organization}"
                            {if $RequireOrganization}required="required" data-bv-notempty="true"
                            data-bv-notempty-message="{translate key=OrganizationRequired}" {/if} />
                    {else}
                        <span class="pf-row-static">{$Organization}</span>
                        <input type="hidden" {formname key=ORGANIZATION} value="{$Organization}" />
                    {/if}
                </div>
            </div>

            <div class="pf-row">
                <span class="pf-row-icon"><i class="bi bi-briefcase"></i></span>
                <div class="pf-row-field">
                    <label class="pf-row-label" for="txtPosition">{translate key="Position"}</label>
                    {if $AllowPositionChange}
                        <input type="text" id="txtPosition" {formname key="POSITION"}
                            class="form-control form-control-sm" value="{$Position}"
                            {if $RequirePosition}required="required" data-bv-notempty="true"
                            data-bv-notempty-message="{translate key=PositionRequired}" {/if} />
                    {else}
                        <span class="pf-row-static">{$Position}</span>
                        <input type="hidden" {formname key=POSITION} value="{$Position}" />
                    {/if}
                </div>
            </div>

            {foreach from=$Attributes item=attribute}
                <div class="pf-row pf-row-attribute">
                    {control type="AttributeControl" attribute=$attribute}
                </div>
            {/foreach}
        </section>

        <section class="np-card">
            <h2 class="np-card-title">{translate key=Preferences}</h2>

            <div class="pf-row">
                <span class="pf-row-icon"><i class="bi bi-house"></i></span>
                <div class="pf-row-field">
                    <label class="pf-row-label" for="homepage">{translate key="DefaultPage"}</label>
                    <select {formname key='DEFAULT_HOMEPAGE'} id="homepage" class="form-select form-select-sm">
                        {html_options values=$HomepageValues output=$HomepageOutput selected=$Homepage}
                    </select>
                </div>
            </div>

            <div class="pf-row">
                <span class="pf-row-icon"><i class="bi bi-clock"></i></span>
                <div class="pf-row-field">
                    <label class="pf-row-label" for="timezoneDropDown">{translate key="Timezone"}</label>
                    <select {formname key='TIMEZONE'} class="form-select form-select-sm" id="timezoneDropDown">
                        {html_options values=$TimezoneValues output=$TimezoneOutput selected=$Timezone}
                    </select>
                </div>
            </div>

            {if count($AvailableLanguages) > 1}
                <div class="pf-row">
                    <span class="pf-row-icon"><i class="bi bi-globe-americas"></i></span>
                    <div class="pf-row-field">
                        <label class="pf-row-label" for="languageDropDown">{translate key="ChangeLanguage"}</label>
                        <select id="languageDropDown" class="form-select form-select-sm">
                            {foreach from=$AvailableLanguages item=lang}
                                <option value="{$lang->GetLanguageCode()}" {if $CurrentLanguage == $lang->GetLanguageCode()}selected="selected"{/if}>
                                    {$lang->GetDisplayName()}
                                </option>
                            {/foreach}
                        </select>
                    </div>
                </div>
            {/if}
        </section>

        <div class="pf-submit">
            <button type="submit" class="update btn btn-primary" name="{Actions::SAVE}" id="btnUpdate">
                {translate key='Update'}
            </button>
        </div>

        {csrf_token}
    </form>

    {setfocus key='FIRST_NAME'}

    {include file="javascript-includes.tpl" Validator=true}
    {jsfile src="ajax-helpers.js"}
    {jsfile src="autocomplete.js"}
    {jsfile src="profile.js"}

    <script type="text/javascript">
        function enableButton() {
            $('#form-profile').find('button').removeAttr('disabled');
        }

        $(document).ready(function() {
            var profilePage = new Profile();
            profilePage.init();

            var profileForm = $('#form-profile');

            profileForm
                .on('init.field.bv', function(e, data) {
                    var $parent = data.element.parents('.pf-row-field');
                    var $icon = $parent.find('.form-control-feedback[data-bv-icon-for="' + data.field +
                        '"]');
                    var validators = data.bv.getOptions(data.field).validators;

                    if (validators.notEmpty) {
                        $icon.addClass('bi bi-asterisk').show();
                    }
                })
                .off('success.form.bv')
                .on('success.form.bv', function(e) {
                    e.preventDefault();
                });

            profileForm.bootstrapValidator();

            $('#txtOrganization').orgAutoComplete("ajax/autocomplete.php?type={AutoCompleteType::Organization}");

            {* Saves + reloads immediately on change, same behavior as the old sidebar language
               switcher (tpl/globalfooter.tpl), just triggered from this select instead of a
               list of links. *}
            $('#languageDropDown').on('change', function() {
                var langCode = $(this).val();
                var csrfToken = '{$CSRFToken|escape:'javascript'}';
                $.post('{$Path|escape:'javascript'}ajax/change_language.php', {
                    '{FormKeys::LANGUAGE}': langCode,
                    '{FormKeys::CSRF_TOKEN}': csrfToken
                }, 'json').done(function(data) {
                    if (data && data.success) {
                        window.location.reload();
                    } else {
                        console.error('Language change failed', data);
                    }
                }).fail(function(jqXHR, textStatus, errorThrown) {
                    console.error('Language change request failed', textStatus, errorThrown);
                });
            });
        });
    </script>

    <div class="modal" id="waitModal" tabindex="-1" role="dialog" aria-labelledby="waitModalLabel"
        data-bs-backdrop="static" aria-hidden="true">
        {include file="wait-box.tpl" translateKey='Working'}
    </div>

</div>
{include file='globalfooter.tpl'}
