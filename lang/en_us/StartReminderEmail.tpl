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
			<p style="margin:0 0 22px 0;font-size:20px;font-weight:bold;color:#1a1a1a;">⏰ 您的預約即將開始</p>

			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.9;">
				<tr>
					<td width="72" valign="top" style="color:#888888;">時間</td>
					<td>{formatdate date=$StartDate key=reservation_email} - {formatdate date=$EndDate key=reservation_email}</td>
				</tr>
				<tr>
					<td valign="top" style="color:#888888;">資源</td>
					<td>{$ResourceName}</td>
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
			</table>

			{if $VideoConferenceResources|default:array()|count > 0}
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

			<table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:22px;">
				<tr>
					<td style="border-radius:4px;background-color:#2c5aa0;">
						<a href="{$ScriptUrl}/{$ReservationUrl}" style="display:inline-block;padding:10px 18px;color:#ffffff;font-size:13px;text-decoration:none;">查看預約</a>
					</td>
					<td width="10"></td>
					<td style="border-radius:4px;border:1px solid #d0d5dd;">
						<a href="{$ScriptUrl}/{$ICalUrl}" style="display:inline-block;padding:10px 18px;color:#2c5aa0;font-size:13px;text-decoration:none;">加入行事曆</a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td style="background-color:#f8f9fb;padding:14px 28px;font-size:12px;color:#999999;">此為系統自動通知信件，請勿直接回覆。</td>
	</tr>
</table>
</td></tr>
</table>
