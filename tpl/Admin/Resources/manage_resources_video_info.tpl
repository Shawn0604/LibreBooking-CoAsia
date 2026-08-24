{* Read-only display of a video-conference resource's meeting info + host credentials, used
   both inline in manage_resource_drawer_content.tpl and as the AJAX refresh fragment returned
   by ManageResourcesPresenter::UpdateVideoConferenceInfo() (see Web/scripts/admin/resource.js).
   Editing happens in the single combined #videoConferenceInfoDialog (manage_resources.tpl),
   not here. $resource, $id, $attributeList, $hostCredentials must already be assigned by the
   caller. The attribute's Label() must stay the literal 'video_url' etc. -- renaming it would
   break ReservationEmailTemplateContext's label match. *}
{foreach from=$attributeList item=attribute}
	{if $attribute->AppliesToEntity($id)}
		{assign var=videoLabelKey value=''}
		{if $attribute->Label() == 'video_url'}
			{assign var=videoLabelKey value='VideoLink'}
		{elseif $attribute->Label() == 'video_meeting_id'}
			{assign var=videoLabelKey value='MeetingId'}
		{elseif $attribute->Label() == 'video_passcode'}
			{assign var=videoLabelKey value='Passcode'}
		{/if}
		{if $videoLabelKey != ''}
			<div class="mt-2">
				<label class="inline fw-bold">{translate key=$videoLabelKey}</label>
				<div>{assign var=attrValue value=$resource->GetAttributeValue($attribute->Id())}{if $attrValue != ''}{$attrValue}{else}-{/if}</div>
			</div>
		{/if}
	{/if}
{/foreach}
<div class="mt-2">
	<label class="inline fw-bold">{translate key=VideoConferenceHostAccount}</label>
	<div>{if $hostCredentials}{$hostCredentials->HostAccount}{else}-{/if}</div>
</div>
<div class="mt-2">
	<label class="inline fw-bold">{translate key=VideoConferenceHostPassword}</label>
	<div>{if $hostCredentials}{$hostCredentials->HostPassword}{else}-{/if}</div>
</div>
