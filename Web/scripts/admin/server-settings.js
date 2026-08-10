function ServerSettingsDiagnostics(opts) {
  var options = opts;

  var statusBadgeClass = {
    ok: 'admin-badge-inprogress',
    error: 'admin-badge-unavailable',
    unconfigured: 'admin-badge-ended',
    checking: 'admin-badge-ended',
  };

  ServerSettingsDiagnostics.prototype.init = function () {
    var self = this;

    $('#diagRecheckAll').on('click', function (e) {
      e.preventDefault();
      self.runAll();
    });

    self.runAll();
  };

  ServerSettingsDiagnostics.prototype.runAll = function () {
    var self = this;
    var checks = $('.diag-card').map(function () {
      return $(this).data('check');
    }).get();

    $.each(checks, function (i, check) {
      self.runCheck(check);
    });
  };

  ServerSettingsDiagnostics.prototype.runCheck = function (check) {
    var card = $('#diagCard-' + check);
    setStatus(card, 'checking', options.messages.checking);
    card.find('.diag-checkedat').text('');
    card.find('.diag-message').addClass('d-none').text('');

    $.getJSON(options.checkUrl + '?dr=' + check)
      .done(function (data) {
        applyResult(card, data);
        $('#diagLastCheckedAll').text(options.messages.lastChecked + '：' + data.checkedAt);
      })
      .fail(function () {
        applyResult(card, { status: 'error', message: '', checkedAt: '' });
      });
  };

  function applyResult(card, data) {
    var text = options.messages[data.status] || data.status;
    setStatus(card, data.status, text);

    if (data.checkedAt) {
      card.find('.diag-checkedat').text(options.messages.lastChecked + '：' + data.checkedAt);
    }

    var messageEl = card.find('.diag-message');
    if (data.message) {
      messageEl.text(data.message).removeClass('d-none');
    } else {
      messageEl.addClass('d-none').text('');
    }

    var webhookEl = card.find('.diag-lastwebhook');
    if (webhookEl.length) {
      webhookEl.text(options.messages.lastWebhook + '：' + (data.lastWebhookAt || options.messages.neverReceived));
    }
  }

  function setStatus(card, status, text) {
    var badge = card.find('.diag-status');
    badge.text(text);
    badge.removeClass('admin-badge-inprogress admin-badge-unavailable admin-badge-ended');
    badge.addClass(statusBadgeClass[status] || 'admin-badge-ended');
  }
}
