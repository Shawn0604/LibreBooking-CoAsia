{* Shared body for every reservation status email (created/pending/updated/cancelled/approved).
   Included (not duplicated) from lang/en_us/ReservationCreated.tpl (created, updated, approved —
   see ReservationCreatedEmail/ReservationUpdatedEmail/ReservationApprovedEmail's GetTemplateName())
   and lang/en_us/ReservationDeleted.tpl (cancelled), driven entirely by the $Status var each of
   those PHP classes sets via PopulateTemplate(). One template, one visual system, instead of four
   near-duplicate files drifting apart over time.

   Chinese text is hardcoded directly (not routed through {translate}) for the same reason the
   rest of this email already does that — see COASIA-CHANGES.md §2: guest recipients have no
   account language preference and always fall back to the system default, so this is the only
   way to guarantee every recipient sees Chinese regardless of their own account's language. *}

{if $Status == 'pending'}
    {assign var='StatusLabel' value='等待審核'}
    {assign var='StatusBg' value='#fbeee0'}
    {assign var='StatusColor' value='#a5562f'}
{elseif $Status == 'updated'}
    {assign var='StatusLabel' value='預約已更新'}
    {assign var='StatusBg' value='#e8eef7'}
    {assign var='StatusColor' value='#1a3f73'}
{elseif $Status == 'cancelled'}
    {assign var='StatusLabel' value='預約已取消'}
    {assign var='StatusBg' value='#fbe9e7'}
    {assign var='StatusColor' value='#c5221f'}
{elseif $Status == 'approved'}
    {assign var='StatusLabel' value='審核通過'}
    {assign var='StatusBg' value='#e6f4ea'}
    {assign var='StatusColor' value='#1e7e34'}
{else}
    {assign var='StatusLabel' value='預約成功'}
    {assign var='StatusBg' value='#e6f4ea'}
    {assign var='StatusColor' value='#1e7e34'}
{/if}

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f2f6;padding:24px 0;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<tr><td align="center">
<table role="presentation" class="coasia-email-card" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;background-color:#ffffff;border-radius:12px;overflow:hidden;">
	<tr>
		<td class="coasia-email-pad" style="background-color:#1a3f73;padding:20px 28px;">
			<span style="color:#ffffff;font-size:17px;font-weight:700;">CoAsia Booking</span>
		</td>
	</tr>
	<tr>
		<td class="coasia-email-pad" style="padding:28px;color:#1c2333;">

			<table role="presentation" cellpadding="0" cellspacing="0" style="margin-bottom:18px;">
				<tr>
					<td style="background-color:{$StatusBg};border-radius:100px;padding:6px 16px;">
						<span style="color:{$StatusColor};font-size:12.5px;font-weight:700;letter-spacing:0.02em;">{$StatusLabel}</span>
					</td>
				</tr>
			</table>

			<p style="margin:0 0 22px 0;font-size:19px;font-weight:700;color:#1c2333;">{if $Title}{$Title}{else}會議室預約{/if}</p>

			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.9;">
				<tr>
					<td width="80" valign="top" style="color:#6c7488;">時間</td>
					<td>{formatdate date=$StartDate key=reservation_email} － {formatdate date=$EndDate key=reservation_email}</td>
				</tr>
				<tr>
					<td valign="top" style="color:#6c7488;">會議室</td>
					<td>{foreach from=$Resources item=resource name=resourceLoop}{$resource.name|escape}{if $resource.location} ({$resource.location|escape}){/if}{if !$smarty.foreach.resourceLoop.last}、{/if}{/foreach}</td>
				</tr>
				<tr>
					<td valign="top" style="color:#6c7488;">預約人</td>
					<td>{$UserName|escape}{if !empty($CreatedBy)}（由 {$CreatedBy} 代為預約）{/if}</td>
				</tr>
				{if $Description}
				<tr>
					<td valign="top" style="color:#6c7488;">備註</td>
					<td>{$Description|nl2br}</td>
				</tr>
				{/if}
			</table>

			{if $VideoConferenceResources|default:array()|count > 0}
				{foreach from=$VideoConferenceResources item=video}
					<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px;background-color:#e8eef7;border-left:4px solid #1a3f73;border-radius:4px;">
						<tr><td style="padding:14px 16px;font-size:14px;line-height:1.9;">
							<strong style="color:#1a3f73;">視訊會議連結</strong><br/>
							<a href="{$video->Url|escape}" style="color:#1a3f73;word-break:break-all;">{$video->Url|escape}</a><br/>
							{if $video->MeetingId}會議 ID：{$video->MeetingId|escape}<br/>{/if}
							{if $video->Passcode}密碼：{$video->Passcode|escape}<br/>{/if}
						</td></tr>
					</table>
				{/foreach}
			{/if}

			{if $Status == 'cancelled'}
				{if !empty($DeleteReason)}
					<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px;">
						<tr>
							<td width="80" valign="top" style="color:#6c7488;font-size:14px;">取消原因</td>
							<td style="font-size:14px;">{$DeleteReason|nl2br}</td>
						</tr>
					</table>
				{/if}
			{/if}

			{if $Status == 'approved' && !empty($ApprovedBy)}
				<p style="margin:20px 0 0 0;font-size:13px;color:#6c7488;">核准人：{$ApprovedBy}</p>
			{/if}

			{if $Status == 'updated' && $Changes|default:array()|count > 0}
				<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px;background-color:#f6f7fb;border-radius:8px;">
					<tr><td style="padding:14px 16px;">
						<p style="margin:0 0 8px 0;font-size:13px;font-weight:700;color:#1c2333;">變更內容</p>
						<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13.5px;line-height:1.8;">
							{foreach from=$Changes item=change}
								<tr>
									<td width="80" valign="top" style="color:#6c7488;">{$change.label}</td>
									<td>
										<span style="color:#9aa1b3;text-decoration:line-through;">{$change.before}</span>
										&nbsp;→&nbsp;
										<span style="color:#1a3f73;font-weight:600;">{$change.after}</span>
									</td>
								</tr>
							{/foreach}
						</table>
					</td></tr>
				</table>
			{/if}

			{if $Status == 'pending'}
				<p style="margin:20px 0 0 0;font-size:13px;color:#a5562f;">＊此預約需經審核，核准前狀態為待審核，審核結果將另行通知。</p>
			{/if}

			{if $CheckInEnabled}
				<p style="margin:20px 0 0 0;font-size:13px;color:#6c7488;">
					此預約需要報到／退房。
					{if $AutoReleaseMinutes != null}若未在開始時間後 {$AutoReleaseMinutes} 分鐘內報到，此預約將自動取消。{/if}
				</p>
			{/if}

			{if count($RepeatRanges) gt 0}
				<p style="margin:20px 0 4px 0;font-size:14px;"><strong>重複規則（共 {$RepeatRanges|default:array()|count} 次）：</strong></p>
				<p style="margin:0;font-size:14px;color:#555555;line-height:1.8;">
					{foreach from=$RepeatRanges item=date name=dates}
						{formatdate date=$date->GetBegin()}{if !$date->IsSameDate()} － {formatdate date=$date->GetEnd()}{/if}<br/>
					{/foreach}
				</p>
			{/if}

			{if $Participants|default:array()|count + $ParticipatingGuests|default:array()|count > 0}
				<p style="margin:20px 0 4px 0;font-size:14px;"><strong>參與者：</strong></p>
				<p style="margin:0;font-size:14px;color:#555555;line-height:1.8;">
					{foreach from=$Participants item=user}{$user->FullName()}<br/>{/foreach}
					{foreach from=$ParticipatingGuests item=email}{$email}<br/>{/foreach}
				</p>
			{/if}

			{if $Invitees|default:array()|count + $InvitedGuests|default:array()|count > 0}
				<p style="margin:20px 0 4px 0;font-size:14px;"><strong>受邀者：</strong></p>
				<p style="margin:0;font-size:14px;color:#555555;line-height:1.8;">
					{foreach from=$Invitees item=user}{$user->FullName()}<br/>{/foreach}
					{foreach from=$InvitedGuests item=email}{$email}<br/>{/foreach}
				</p>
			{/if}

			{if $Accessories|default:array()|count > 0}
				<p style="margin:20px 0 4px 0;font-size:14px;"><strong>設備／附件：</strong></p>
				<p style="margin:0;font-size:14px;color:#555555;line-height:1.8;">
					{foreach from=$Accessories item=accessory}({$accessory->QuantityReserved}) {$accessory->Name}<br/>{/foreach}
				</p>
			{/if}

			{if $CreditsCurrent > 0}
				<p style="margin:20px 0 0 0;font-size:13px;color:#6c7488;">
					此預約需要 {$CreditsCurrent} 點數。{if $CreditsCurrent != $CreditsTotal}此系列預約總共需要 {$CreditsTotal} 點數。{/if}
				</p>
			{/if}

			<p style="margin:24px 0 0 0;font-size:13px;color:#9aa1b3;">參考編號：{$ReferenceNumber}</p>

			<table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:22px;">
				<tr>
					<td style="border-radius:100px;background-color:#1a3f73;">
						<a href="{$ScriptUrl}/{$ReservationUrl}" style="display:inline-block;padding:11px 22px;color:#ffffff;font-size:13px;font-weight:600;text-decoration:none;">查看預約</a>
					</td>
					{if $Status != 'cancelled' && $VideoConferenceResources|default:array()|count > 0}
						<td width="10"></td>
						<td style="border-radius:100px;background-color:#1e7e34;">
							<a href="{$VideoConferenceResources[0]->Url|escape}" style="display:inline-block;padding:11px 22px;color:#ffffff;font-size:13px;font-weight:600;text-decoration:none;">加入視訊會議</a>
						</td>
					{/if}
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td class="coasia-email-pad" style="background-color:#f6f7fb;padding:14px 28px;font-size:12px;color:#9aa1b3;">此為系統自動通知信件，請勿直接回覆。</td>
	</tr>
</table>
</td></tr>
</table>
