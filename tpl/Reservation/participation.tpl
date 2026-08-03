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

	{* participantList[] is what Pages/Ajax/ReservationSavePage.php::GetParticipants() /
	   ReservationUpdatePresenter actually read — the picker above (participant-picker.js)
	   only keeps in-memory state + renders chips, so this hidden container is kept in sync
	   with it on every add/remove (see create.tpl's rpParticipantPicker instantiation) to
	   give the classic form's ajaxSubmit something with the right name to serialize. *}
	<div id="rpParticipantHiddenInputs"></div>
</div>
