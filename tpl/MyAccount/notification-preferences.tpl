{include file='globalheader.tpl' }

<div class="page-notification-preferences">
	{if !$EmailEnabled}
		<div class="alert alert-danger text-center">
			<i class="bi bi-exclamation-triangle-fill fs-5"></i> {translate key=EmailDisabled}
		</div>
	{else}
		<div id="notification-preferences-box" class="default-box card shadow col- col-sm-8 mx-auto">

			<form id="notification-preferences-form" method="post" action="{$smarty.server.SCRIPT_NAME}">
				<div class="card-body mx-3">
					<h1 class="text-center border-bottom mb-3">{translate key=NotificationPreferences}</h1>
					{if $PreferencesUpdated}
						<div class="success alert alert-success">
							<i class="bi bi-check-circle-fill"></i> {translate key=YourSettingsWereUpdated}
						</div>
					{/if}
					<div class="row gy-3">
						<div class="notification-row col-12 col-sm-6">
							<div class="notification-type">{translate key=ReservationCreatedPreference}</div>
							<div class="form-check form-switch">
								<input class="form-check-input" type="checkbox" role="switch"
									id="{ReservationEvent::Created}" name="{ReservationEvent::Created}"
									{if $Created}checked="checked" {/if}>
								<label class="form-check-label"
									for="{ReservationEvent::Created}">{translate key=PreferenceSendEmail}</label>
							</div>
						</div>

						<div class="notification-row col-12 col-sm-6">
							<div class="notification-type">{translate key=ReservationUpdatedPreference}</div>
							<div class="form-check form-switch">
								<input class="form-check-input" type="checkbox" role="switch"
									id="{ReservationEvent::Updated}" name="{ReservationEvent::Updated}"
									{if $Updated}checked="checked" {/if}>
								<label class="form-check-label"
									for="{ReservationEvent::Updated}">{translate key=PreferenceSendEmail}</label>
							</div>
						</div>

						<div class="notification-row col-12 col-sm-6">
							<div class="notification-type">
								{translate key=ReservationDeletedPreference}
							</div>
							<div class="form-check form-switch">
								<input class="form-check-input" type="checkbox" role="switch"
									id="{ReservationEvent::Deleted}" name="{ReservationEvent::Deleted}"
									{if $Deleted}checked="checked" {/if}>
								<label class="form-check-label"
									for="{ReservationEvent::Deleted}">{translate key=PreferenceSendEmail}</label>
							</div>

						</div>

						<div class="notification-row alt col-12 col-sm-6">
							<div class="notification-type">
								{translate key=ReservationApprovalPreference}
							</div>
							<div class="form-check form-switch">
								<input class="form-check-input" type="checkbox" role="switch"
									id="{ReservationEvent::Approved}" name="{ReservationEvent::Approved}"
									{if $Approved}checked="checked" {/if}>
								<label class="form-check-label"
									for="{ReservationEvent::Approved}">{translate key=PreferenceSendEmail}</label>
							</div>
						</div>

						<div class="notification-row col-12 col-sm-6">
							<div class="notification-type">
								{translate key=ReservationParticipationActivityPreference}
							</div>
							<div class="form-check form-switch">
								<input class="form-check-input" type="checkbox" role="switch"
									id="{ReservationEvent::ParticipationChanged}"
									name="{ReservationEvent::ParticipationChanged}"
									{if $ParticipantChanged}checked="checked" {/if}>
								<label class="form-check-label"
									for="{ReservationEvent::ParticipationChanged}">{translate key=PreferenceSendEmail}</label>
							</div>
						</div>

						<div class="notification-row-alt col-12 col-sm-6">
							<div class="notification-type">
								{translate key=ReservationSeriesEndingPreference}
							</div>
							<div class="form-check form-switch">
								<input class="form-check-input" type="checkbox" role="switch"
									id="{ReservationEvent::SeriesEnding}" name="{ReservationEvent::SeriesEnding}"
									{if $SeriesEnding}checked="checked" {/if}>
								<label class="form-check-label"
									for="{ReservationEvent::SeriesEnding}">{translate key=PreferenceSendEmail}</label>
							</div>
						</div>

					</div>
					<div class="form-group d-grid mt-3">
						<button type="submit" class="btn btn-primary update prompt" name="{Actions::SAVE}">
							{translate key='Update'}
						</button>
					</div>

					{if $LineEnabled}
						<div id="line-notification-box" class="mt-4 pt-3 border-top">
							<h2 class="fs-5">{translate key=LineNotifications}</h2>
							{if $LineLinked}
								<div class="text-success mb-2">
									<i class="bi bi-check-circle-fill"></i> {translate key=LineLinked}
								</div>
								<button type="submit" class="btn btn-outline-secondary btn-sm" name="lineUnlink" value="1">
									{translate key=LineUnlink}
								</button>
							{else}
								<p>{translate key=LineLinkInstructions}</p>
								<div class="d-flex align-items-center gap-3 flex-wrap">
									<span class="fs-3 fw-bold font-monospace">{$LineLinkCode}</span>
									<a class="btn btn-success btn-sm" href="{$LineFriendUrl}" target="_blank" rel="noopener">
										{translate key=LineAddFriend}
									</a>
								</div>
							{/if}
						</div>
					{/if}
				</div>
			</form>

		</div>
	{/if}

</div>

{include file="javascript-includes.tpl"}
{include file='globalfooter.tpl'}