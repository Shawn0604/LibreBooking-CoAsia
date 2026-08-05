// Shared participant search+chip picker — originally built for the Overview page's Quick
// Create modal, now also used by the classic reservation form (tpl/Reservation/create.tpl,
// tpl/Reservation/participation.tpl) so both share the identical debounced-search/dropdown/
// removable-chip behavior, endpoint, and visual style rather than drifting into two
// near-duplicate implementations. Searches ajax/overview_fullcalendar_participant_search.php
// (Chinese/Korean/English name, username/employee ID, email) and stores each internal result's
// real Id (users.user_id), never an email.
//
// Also supports external (non-user) attendees: when config.allowExternal is set and the search
// box holds a syntactically complete email address that doesn't already belong to a selected/
// excluded/internal-search-matched user, an "invite this email" row is offered. Picking it adds
// an entry with type:'external' carrying the email instead of a user id — the caller (create.tpl,
// overview_fullcalendar.js) is responsible for routing type:'internal' entries to the
// participant/invitee id list and type:'external' entries to the guest email list when building
// the submit payload.
//
// config: {
//   input, results, chipList: selectors (required)
//   chipsRow, wrapper: selectors (optional — wrapper defaults to chipList's own closest
//     .participant-search-ish container isn't assumed; pass it explicitly)
//   searchUrl: full search endpoint including any trailing '?term=' (required)
//   noMatchesText: string shown when a search returns zero results
//   allowExternal: bool — offer "invite this email" for external attendees
//   inviteEmailLabel: string with a literal '{{email}}' token, e.g. 'Invite {{email}} (external)'
//   externalBadgeText: string shown on external attendee chips, e.g. 'External'
//   excludeIds: number[] — internal user ids that may never be added (e.g. the reservation owner)
//   excludeEmails: string[] — emails that may never be added as external attendees (e.g. the
//     reservation owner's own email, and/or every currently-selected internal user's email so
//     someone can't be added twice under two different identities)
//   conflictsWith: optional function() -> {id, type, email}[] — current selections of a paired
//     picker (e.g. the "optional participants" picker when this is the "required" one) so the
//     same person can't end up in both lists at once
//   onChange: optional callback(participants[]) fired after every add/remove/reset
// }
var PARTICIPANT_AVATAR_PALETTE = [
    { bg: '#dbeafe', fg: '#1e40af' },
    { bg: '#dcfce7', fg: '#166534' },
    { bg: '#ede9fe', fg: '#5b21b6' },
    { bg: '#fef3c7', fg: '#92400e' },
    { bg: '#fce7f3', fg: '#9d174d' },
    { bg: '#e0f2fe', fg: '#075985' },
];

var PARTICIPANT_EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function participantAvatarColors(id) {
    return PARTICIPANT_AVATAR_PALETTE[Math.abs(typeof id === 'number' ? id : 0) % PARTICIPANT_AVATAR_PALETTE.length];
}

function participantInitials(name) {
    var source = (name || '').trim();
    if (!source) {
        return '?';
    }
    var parts = source.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
        return (parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase();
    }
    return source.slice(0, 2).toUpperCase();
}

function createParticipantPicker(config) {
    var participants = []; // {id, label, type: 'internal'|'external', email?}[] — label is what the chip shows
    var searchTimer = null;

    function fireChange() {
        if (typeof config.onChange === 'function') {
            config.onChange(participants.slice());
        }
    }

    function excludedIds() {
        return config.excludeIds || [];
    }

    function excludedEmails() {
        return (config.excludeEmails || []).map(function (e) { return e.toLowerCase(); });
    }

    function peerParticipants() {
        return typeof config.conflictsWith === 'function' ? (config.conflictsWith() || []) : [];
    }

    function isDuplicateInternal(userId) {
        var here = participants.some(function (p) { return p.type !== 'external' && p.id === userId; });
        var peer = peerParticipants().some(function (p) { return p.type !== 'external' && p.id === userId; });
        return here || peer;
    }

    function isDuplicateExternal(emailLower) {
        var here = participants.some(function (p) { return p.type === 'external' && p.email.toLowerCase() === emailLower; });
        var peer = peerParticipants().some(function (p) { return p.type === 'external' && (p.email || '').toLowerCase() === emailLower; });
        return here || peer;
    }

    function render() {
        var $list = $(config.chipList).empty();
        participants.forEach(function (p) {
            var $chip = $('<span class="reservation-chip"></span>');
            if (p.type === 'external') {
                $chip.addClass('reservation-chip-external');
                $('<span class="reservation-chip-ext-badge"></span>').text(config.externalBadgeText || 'External').appendTo($chip);
            }
            $('<span class="reservation-chip-text"></span>').text(p.label).appendTo($chip);
            $('<button type="button" class="btn-close btn-close-white" style="font-size:8px" aria-label="Remove"></button>')
                .on('click', function () {
                    participants = participants.filter(function (x) { return x.id !== p.id; });
                    render();
                    fireChange();
                })
                .appendTo($chip);
            $list.append($chip);
        });
        $(config.chipsRow || config.chipList).toggleClass('d-none', participants.length === 0);
    }

    function closeResults() {
        $(config.results).addClass('d-none').empty();
    }

    function add(user) {
        if (excludedIds().indexOf(user.Id) !== -1) {
            return;
        }
        if (isDuplicateInternal(user.Id)) {
            return;
        }
        var label = (user.ChineseName ? user.ChineseName + ' ' : '') + (user.EnglishName || user.Username || '');
        participants.push({ id: user.Id, label: label.trim() || user.Email, type: 'internal', email: user.Email || '' });
        render();
        fireChange();
        closeResults();
        $(config.input).val('').trigger('focus');
    }

    function addExternal(rawEmail) {
        var email = $.trim(rawEmail);
        if (!PARTICIPANT_EMAIL_RE.test(email)) {
            return false;
        }
        var key = email.toLowerCase();
        if (excludedEmails().indexOf(key) !== -1 || isDuplicateExternal(key)) {
            return false;
        }
        participants.push({ id: 'ext:' + key, label: email, type: 'external', email: email });
        render();
        fireChange();
        closeResults();
        $(config.input).val('').trigger('focus');
        return true;
    }

    function renderResults(results) {
        var $panel = $(config.results).empty();
        var selectedIds = participants.filter(function (p) { return p.type !== 'external'; }).map(function (p) { return p.id; });
        var blockedIds = excludedIds().concat(selectedIds).concat(
            peerParticipants().filter(function (p) { return p.type !== 'external'; }).map(function (p) { return p.id; })
        );
        var visible = results.filter(function (u) { return blockedIds.indexOf(u.Id) === -1; });

        var term = $.trim($(config.input).val());
        var showInvite = false;
        if (config.allowExternal && PARTICIPANT_EMAIL_RE.test(term)) {
            var key = term.toLowerCase();
            var matchesInternal = results.some(function (u) { return (u.Email || '').toLowerCase() === key; });
            showInvite = !matchesInternal && excludedEmails().indexOf(key) === -1 && !isDuplicateExternal(key);
        }

        if (showInvite) {
            var inviteText = (config.inviteEmailLabel || 'Invite {{email}}').replace('{{email}}', term);
            var $invite = $('<button type="button" class="participant-search-item participant-search-invite"></button>');
            $('<span class="participant-search-item-avatar participant-search-invite-icon"></span>')
                .html('<i class="bi bi-envelope-plus"></i>')
                .appendTo($invite);
            $('<span class="participant-search-item-name"></span>').text(inviteText).appendTo($invite);
            $invite.on('click', function () { addExternal(term); }).appendTo($panel);
        }

        if (visible.length === 0 && !showInvite) {
            $('<div class="participant-search-empty"></div>').text(config.noMatchesText || '').appendTo($panel);
        } else {
            visible.forEach(function (u) {
                var colors = participantAvatarColors(u.Id);
                var $item = $('<button type="button" class="participant-search-item"></button>');
                $('<span class="participant-search-item-avatar"></span>')
                    .text(participantInitials(u.EnglishName || u.Username))
                    .css({ backgroundColor: colors.bg, color: colors.fg })
                    .appendTo($item);
                var $name = $('<span class="participant-search-item-name"></span>');
                if (u.ChineseName) {
                    $('<span class="participant-search-item-chinese"></span>').text(u.ChineseName).appendTo($name);
                }
                $('<span class="participant-search-item-english"></span>').text(u.EnglishName || u.Username).appendTo($name);
                $name.appendTo($item);
                $('<span class="participant-search-item-dept"></span>').text(u.Organization || '').appendTo($item);
                $('<span class="participant-search-item-email"></span>').text(u.Email || '').appendTo($item);
                $item.on('click', function () { add(u); }).appendTo($panel);
            });
        }
        $panel.removeClass('d-none');
    }

    function runSearch(term) {
        fetch(config.searchUrl + encodeURIComponent(term), { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (results) {
                // Stale-response guard: only render if the input still holds what we
                // searched for — a slower earlier request finishing after a faster later
                // one must not clobber the results the user is currently looking at.
                if ($(config.input).val().trim() === term) {
                    renderResults(results || []);
                }
            })
            .catch(function () {
                // Silent failure is acceptable here — user can just keep typing or retry.
            });
    }

    $(config.input).on('input', function () {
        clearTimeout(searchTimer);
        var term = $(this).val().trim();
        if (term.length === 0) {
            closeResults();
            return;
        }
        searchTimer = setTimeout(function () { runSearch(term); }, 250);
    });

    $(config.input).on('focus', function () {
        var term = $(this).val().trim();
        if (term.length > 0) {
            runSearch(term);
        }
    });

    // Typing a complete external email never adds it by itself — only Enter (or clicking the
    // "invite this email" row rendered above) does. This is the one case where the picker adds
    // something that never came back from the internal-user search, so it's gated behind an
    // explicit confirmation rather than happening automatically as a side effect of typing.
    $(config.input).on('keydown', function (e) {
        var isEnter = e.key === 'Enter' || e.keyCode === 13;
        if (!isEnter || !config.allowExternal) {
            return;
        }
        var term = $.trim($(this).val());
        if (!PARTICIPANT_EMAIL_RE.test(term)) {
            return;
        }
        e.preventDefault();
        addExternal(term);
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest(config.wrapper).length) {
            closeResults();
        }
    });

    return {
        reset: function (initial) {
            participants = (initial || []).map(function (p) {
                return {
                    id: p.id,
                    label: p.label,
                    type: p.type || 'internal',
                    email: p.email || (p.type === 'external' ? p.label : ''),
                };
            });
            render();
            $(config.input).val('');
            closeResults();
            fireChange();
        },
        getParticipants: function () {
            return participants.slice();
        },
    };
}
