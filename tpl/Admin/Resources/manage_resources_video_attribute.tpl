{* Same underlying x-editable mechanism as Admin/InlineAttributeEdit.tpl's single-line-textbox
   case (video_url/video_meeting_id/video_passcode are always plain text custom attributes),
   but with a caller-supplied translated label ($labelKey) instead of the attribute's own
   Label(). The attribute's Label() must stay the literal 'video_url' etc. -- renaming it would
   break ReservationEmailTemplateContext's label match (lib/Email/Messages/ReservationEmailTemplateContext.php)
   -- so this partial supplies a friendlier display label without touching that value.
   $id, $attribute, $labelKey, $value must already be assigned by the caller. *}
<div class="updateCustomAttribute mb-2 d-block">
	<label class="inline fw-bold">{translate key=$labelKey}</label>
	<a class="update changeAttribute link-primary" title="{translate key='Edit'}" href="#">
		<span class="bi bi-pencil-square"></span>
		<span class="visually-hidden">{translate key=Edit}</span>
	</a>
	<div>
		<span class="inlineAttribute update" id="inline{$attribute->Id()}{$id}" data-type="text" data-pk="{$id}"
			data-value="{$value|escape:'html'}" data-name="{FormKeys::ATTRIBUTE_PREFIX}{$attribute->Id()}">
			{if $value != ''}{$value}{else}-{/if}
		</span>
	</div>
</div>
