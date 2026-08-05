<div id="reservationParticipation" class="rp-participation">
	<div class="participant-search" id="rpParticipantSearch">
		<div class="participant-search-input-wrap">
			<i class="bi bi-search"></i>
			<input type="text" id="rpParticipantSearchInput" placeholder="{translate key='SearchParticipants'}" autocomplete="off">
			<span class="badge bg-secondary rp-participant-badge" id="participantBadge">0</span>
		</div>
		<div class="participant-search-results d-none" id="rpParticipantSearchResults"></div>
	</div>

	<div class="rp-chip-list" id="rpParticipantChipList"></div>

	{* participantList[]/guestParticipationList[] are what Pages/Ajax/ReservationSavePage.php::
	   GetParticipants()/GetParticipatingGuests() and ReservationUpdatePresenter actually read —
	   the picker above (participant-picker.js) only keeps in-memory state + renders chips, so
	   this hidden container is kept in sync with it on every add/remove (see create.tpl's
	   rpParticipantPicker instantiation) to give the classic form's ajaxSubmit something with
	   the right name to serialize. *}
	<div id="rpParticipantHiddenInputs"></div>

	<details class="rp-optional-participants" id="rpOptionalParticipantsDetails">
		<summary class="rp-optional-toggle">
			<i class="bi bi-chevron-right rp-optional-chevron"></i>
			<span>{translate key='OptionalParticipants'}</span>
			<span class="badge bg-secondary rp-participant-badge" id="inviteeBadge">0</span>
		</summary>
		<div class="rp-optional-body">
			<div class="participant-search" id="rpInviteeSearch">
				<div class="participant-search-input-wrap">
					<i class="bi bi-search"></i>
					<input type="text" id="rpInviteeSearchInput" placeholder="{translate key='SearchParticipants'}" autocomplete="off">
				</div>
				<div class="participant-search-results d-none" id="rpInviteeSearchResults"></div>
			</div>

			<div class="rp-chip-list" id="rpInviteeChipList"></div>

			{* invitationList[]/guestInvitationList[] — same idea as rpParticipantHiddenInputs
			   above, but for the optional side (GetInvitees()/GetInvitedGuests()). *}
			<div id="rpInviteeHiddenInputs"></div>
		</div>
	</details>

	{* Hidden for now per explicit request — the checkbox (and its submitted value) still work
	   exactly as before, just not shown to the user yet. *}
	<div id="allowParticipation" class="form-check mt-2 d-none">
		<input class="form-check-input" type="checkbox" {if $AllowParticipantsToJoin}checked="checked" {/if}
			{formname key=ALLOW_PARTICIPATION} id="allowParticipationCheckbox">
		<label class="form-check-label" for="allowParticipationCheckbox">{translate key=AllowParticipantsToJoin}</label>
	</div>
</div>
