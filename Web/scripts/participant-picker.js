// Shared participant search+chip picker — originally built for the Overview page's Quick
// Create modal, now also used by the classic reservation form (tpl/Reservation/create.tpl)
// so both share the identical debounced-search/dropdown/removable-chip behavior, endpoint,
// and visual style rather than drifting into two near-duplicate implementations. Searches
// ajax/overview_fullcalendar_participant_search.php (Chinese/Korean/English name, username/
// employee ID, email) and stores each result's real Id (users.user_id), never an email.
//
// config: {
//   input, results, chipList: selectors (required)
//   chipsRow, wrapper: selectors (optional — wrapper defaults to chipList's own closest
//     .participant-search-ish container isn't assumed; pass it explicitly)
//   searchUrl: full search endpoint including any trailing '?term=' (required)
//   noMatchesText: string shown when a search returns zero results
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

function participantAvatarColors(id) {
    return PARTICIPANT_AVATAR_PALETTE[Math.abs(id) % PARTICIPANT_AVATAR_PALETTE.length];
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
    var participants = []; // {id, label}[] — label is what the chip shows
    var searchTimer = null;

    function fireChange() {
        if (typeof config.onChange === 'function') {
            config.onChange(participants.slice());
        }
    }

    function render() {
        var $list = $(config.chipList).empty();
        participants.forEach(function (p) {
            var $chip = $('<span class="reservation-chip"></span>').text(p.label);
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
        if (participants.some(function (p) { return p.id === user.Id; })) {
            return;
        }
        var label = (user.ChineseName ? user.ChineseName + ' ' : '') + (user.EnglishName || user.Username || '');
        participants.push({ id: user.Id, label: label.trim() || user.Email });
        render();
        fireChange();
        closeResults();
        $(config.input).val('').trigger('focus');
    }

    function renderResults(results) {
        var $panel = $(config.results).empty();
        var selectedIds = participants.map(function (p) { return p.id; });
        var visible = results.filter(function (u) { return selectedIds.indexOf(u.Id) === -1; });

        if (visible.length === 0) {
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

    $(document).on('click', function (e) {
        if (!$(e.target).closest(config.wrapper).length) {
            closeResults();
        }
    });

    return {
        reset: function (initial) {
            participants = (initial || []).slice();
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
