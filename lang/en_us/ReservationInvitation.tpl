<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f2f4f7;padding:24px 0;font-family:'Segoe UI',Helvetica,Arial,sans-serif;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;">
	<tr>
		<td style="background-color:#2c5aa0;padding:18px 28px;">
			<span style="color:#ffffff;font-size:17px;font-weight:bold;">{$AppTitle}</span>
		</td>
	</tr>
	<tr>
		<td style="padding:28px;color:#333333;">
			<p style="margin:0 0 22px 0;font-size:20px;font-weight:bold;color:#1a1a1a;">
				{if $Deleted}{$UserName} 已刪除一筆預約{else}{$UserName} 已將您加入一筆預約{/if}
			</p>

			{if !empty($DeleteReason)}
				<p style="margin:0 0 20px 0;font-size:14px;"><strong>刪除原因：</strong>{$DeleteReason|nl2br}</p>
			{/if}

			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.9;">
				<tr>
					<td width="72" valign="top" style="color:#888888;">時間</td>
					<td>{formatdate date=$StartDate key=reservation_email} - {formatdate date=$EndDate key=reservation_email}</td>
				</tr>
				{if $Title}
				<tr>
					<td valign="top" style="color:#888888;">標題</td>
					<td>{$Title}</td>
				</tr>
				{/if}
				{if $Description}
				<tr>
					<td valign="top" style="color:#888888;">說明</td>
					<td>{$Description|nl2br}</td>
				</tr>
				{/if}
				<tr>
					<td valign="top" style="color:#888888;">資源</td>
					<td>{foreach from=$ResourceNames item=resourceName name=resourceLoop}{$resourceName|escape}{if !$smarty.foreach.resourceLoop.last}、{/if}{/foreach}</td>
				</tr>
			</table>

			{if $VideoConferenceResources|default:array()|count > 0 && !$Deleted}
				{foreach from=$VideoConferenceResources item=video}
					<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px;background-color:#eef4fc;border-left:4px solid #2c5aa0;border-radius:4px;">
						<tr><td style="padding:14px 16px;font-size:14px;line-height:1.9;">
							<strong style="color:#2c5aa0;">📹 視訊連結</strong><br/>
							連結：<a href="{$video->Url|escape}" style="color:#2c5aa0;">{$video->Url|escape}</a><br/>
							{if $video->MeetingId}會議 ID：{$video->MeetingId|escape}<br/>{/if}
							{if $video->Passcode}密碼：{$video->Passcode|escape}<br/>{/if}
						</td></tr>
					</table>
				{/foreach}
			{/if}

			{if $RequiresApproval && !$Deleted}
				<p style="margin:20px 0 0 0;font-size:13px;color:#b8860b;">＊此預約需經審核，核准前狀態為待審核。</p>
			{/if}

			{if count($RepeatRanges) gt 0}
				<p style="margin:20px 0 4px 0;font-size:14px;"><strong>重複日期（共 {$RepeatRanges|default:array()|count} 次）：</strong></p>
				<p style="margin:0;font-size:14px;color:#555555;line-height:1.8;">
					{foreach from=$RepeatRanges item=date name=dates}
						{formatdate date=$date->GetBegin()}{if !$date->IsSameDate()} - {formatdate date=$date->GetEnd()}{/if}<br/>
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

			{if !$Deleted && !$Updated}
				<table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:22px;">
					<tr>
						<td style="padding-right:14px;font-size:14px;color:#333333;vertical-align:middle;"><strong>是否出席？</strong></td>
						<td style="border-radius:4px;background-color:#2c5aa0;">
							<a href="{$ScriptUrl}/{$AcceptUrl}" style="display:inline-block;padding:8px 16px;color:#ffffff;font-size:13px;text-decoration:none;">是</a>
						</td>
						<td width="10"></td>
						<td style="border-radius:4px;border:1px solid #d0d5dd;">
							<a href="{$ScriptUrl}/{$DeclineUrl}" style="display:inline-block;padding:8px 16px;color:#555555;font-size:13px;text-decoration:none;">否</a>
						</td>
					</tr>
				</table>
			{/if}

			{if !$Deleted}
				<table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:22px;">
					<tr>
						<td style="border-radius:4px;background-color:#2c5aa0;">
							<a href="{$ScriptUrl}/{$ReservationUrl}" style="display:inline-block;padding:10px 18px;color:#ffffff;font-size:13px;text-decoration:none;">查看預約</a>
						</td>
						<td width="10"></td>
						<td style="border-radius:4px;border:1px solid #d0d5dd;">
							<a href="{$ScriptUrl}/{$ICalUrl}" style="display:inline-block;padding:10px 18px;color:#2c5aa0;font-size:13px;text-decoration:none;">加入行事曆</a>
						</td>
						<td width="10"></td>
						<td style="border-radius:4px;border:1px solid #d0d5dd;">
							<a href="{$GoogleCalendarUrl}" target="_blank" rel="nofollow" style="display:inline-block;padding:10px 18px;color:#2c5aa0;font-size:13px;text-decoration:none;">加入 Google 日曆</a>
						</td>
					</tr>
				</table>
			{/if}
		</td>
	</tr>
	<tr>
		<td style="background-color:#f8f9fb;padding:14px 28px;font-size:12px;color:#999999;">此為系統自動通知信件，請勿直接回覆。</td>
	</tr>
</table>
</td></tr>
</table>
