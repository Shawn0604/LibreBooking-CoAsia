// Auto-save for tpl/MyAccount/notification-preferences.tpl — every control on this page saves
// itself immediately via ajax/notification_preferences_save.php (and ajax/line_test_notification.php
// for the test button) instead of a classic form POST + full-page reload.
$(function () {
    var opts = window.notificationPreferencesOpts;
    if (!opts) {
        return;
    }

    var errorHideTimer = null;

    function showError(message) {
        clearTimeout(errorHideTimer);
        $('#npErrorText').text(message || opts.translations.networkError);
        $('#npError').removeClass('d-none');
        errorHideTimer = setTimeout(function () {
            $('#npError').addClass('d-none');
        }, 6000);
    }

    function post(url, body) {
        body.set('CSRF_TOKEN', opts.csrfToken);
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin',
        }).then(function (res) {
            return res.json().catch(function () { return {}; });
        });
    }

    // The unbound-state card (QR/code/加好友) is only ever built server-side in the .tpl —
    // duplicating that markup in JS just to render it after an unlink isn't worth it for an
    // action a user takes rarely. A toggle flip, by contrast, never changes which state
    // (bound/unbound) the LINE card is in, so it only ever needs to sync the two switches.
    function applyToggleState(state) {
        if (typeof state.emailNotificationsEnabled === 'boolean') {
            $('#emailNotificationsEnabled').prop('checked', state.emailNotificationsEnabled);
        }
        if (typeof state.lineNotificationsEnabled === 'boolean') {
            $('#lineNotificationsEnabled').prop('checked', state.lineNotificationsEnabled);
        }
        if (typeof state.wecomNotificationsEnabled === 'boolean') {
            $('#wecomNotificationsEnabled').prop('checked', state.wecomNotificationsEnabled);
        }
    }

    function saveToggle(action, checkboxEl) {
        var $checkbox = $(checkboxEl).prop('disabled', true);
        var body = new URLSearchParams();
        body.set('action', action);
        body.set('enabled', checkboxEl.checked ? '1' : '0');

        post(opts.saveUrl, body)
            .then(function (json) {
                if (json && json.success) {
                    applyToggleState(json);
                    window.showToastById('notificationPreferencesSavedToast');
                } else {
                    checkboxEl.checked = !checkboxEl.checked;
                    showError((json && json.errors && json.errors[0]) || null);
                }
            })
            .catch(function () {
                checkboxEl.checked = !checkboxEl.checked;
                showError();
            })
            .finally(function () {
                $checkbox.prop('disabled', false);
            });
    }

    $('#emailNotificationsEnabled').on('change', function () {
        saveToggle('email', this);
    });

    $('#lineNotificationsEnabled').on('change', function () {
        saveToggle('lineNotifications', this);
    });

    $('#wecomNotificationsEnabled').on('change', function () {
        saveToggle('wecomNotifications', this);
    });

    $('#lineUnlinkBtn').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        var body = new URLSearchParams();
        body.set('action', 'lineUnlink');

        post(opts.saveUrl, body)
            .then(function (json) {
                if (json && json.success) {
                    // Reload to show the now-unbound state (fresh QR/link code) — the simplest
                    // correct way to swap the whole LINE card's markup without hand-duplicating
                    // the .tpl's unbound-state HTML here.
                    window.location.reload();
                } else {
                    showError((json && json.errors && json.errors[0]) || null);
                    $btn.prop('disabled', false);
                }
            })
            .catch(function () {
                showError();
                $btn.prop('disabled', false);
            });
    });

    // "會議開始前提醒" — a personal setting applied to every reservation this user creates.
    // The user only chooses on/off + a fixed lead time; the reminder job decides recipients
    // and channels. No free-text minute entry — the <select> values are the whitelist the
    // server (NotificationPreferencesService) also enforces.
    if ($('#reservationStartReminderEnabled').length) {
        var $reminderSelect = $('#reservationStartReminderMinutes');
        var syncReminderFields = function () {
            var on = $('#reservationStartReminderEnabled').is(':checked');
            $reminderSelect.prop('disabled', !on);
            $('#reservationStartReminderFields').toggleClass('np-row-disabled', !on);
        };
        var saveReminder = function () {
            var on = $('#reservationStartReminderEnabled').is(':checked');
            var body = new URLSearchParams();
            body.set('action', 'reservationStartReminder');
            body.set('enabled', on ? '1' : '0');
            body.set('minutes', String(parseInt($reminderSelect.val(), 10) || 15));
            post(opts.saveUrl, body)
                .then(function (json) {
                    if (json && json.success) {
                        window.showToastById('notificationPreferencesSavedToast');
                    } else {
                        showError((json && json.errors && json.errors[0]) || null);
                    }
                })
                .catch(function () {
                    showError();
                });
        };
        $('#reservationStartReminderEnabled').on('change', function () {
            syncReminderFields();
            saveReminder();
        });
        $reminderSelect.on('change', function () {
            if ($('#reservationStartReminderEnabled').is(':checked')) {
                saveReminder();
            }
        });
        syncReminderFields();
    }

    $('#lineTestNotificationBtn').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        var body = new URLSearchParams();

        post(opts.testUrl, body)
            .then(function (json) {
                if (json && json.success) {
                    window.showToastById('lineTestNotificationToast');
                } else {
                    showError((json && json.errors && json.errors[0]) || null);
                }
            })
            .catch(function () {
                showError();
            })
            .finally(function () {
                $btn.prop('disabled', false);
            });
    });

    $('#wecomTestNotificationBtn').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        var body = new URLSearchParams();

        post(opts.wecomTestUrl, body)
            .then(function (json) {
                if (json && json.success) {
                    window.showToastById('wecomTestNotificationToast');
                } else {
                    showError((json && json.errors && json.errors[0]) || null);
                }
            })
            .catch(function () {
                showError();
            })
            .finally(function () {
                $btn.prop('disabled', false);
            });
    });
});
