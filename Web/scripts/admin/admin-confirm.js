/**
 * Shared destructive-action confirm dialog. Markup lives once per page in
 * tpl/Admin/admin_confirm_dialog.tpl (included via admin_page_shell.tpl).
 *
 * AdminConfirm.show({
 *   title: 'string',
 *   message: 'string',
 *   confirmLabel: 'string',
 *   onConfirm: function () { ... }
 * });
 */
var AdminConfirm = (function () {
  var onConfirmCallback = null;

  function dialogElement() {
    return document.getElementById('adminConfirmDialog');
  }

  function hide() {
    var el = dialogElement();
    if (el && window.bootstrap) {
      bootstrap.Modal.getOrCreateInstance(el).hide();
    }
  }

  function show(options) {
    var el = dialogElement();
    if (!el) {
      // No shared dialog on this page for some reason -- fall back rather than silently no-op.
      if (window.confirm(options.message) && typeof options.onConfirm === 'function') {
        options.onConfirm();
      }
      return;
    }

    document.getElementById('adminConfirmTitle').textContent = options.title || '';
    document.getElementById('adminConfirmMessage').textContent = options.message || '';
    document.getElementById('adminConfirmButton').textContent = options.confirmLabel || '';
    onConfirmCallback = options.onConfirm;

    bootstrap.Modal.getOrCreateInstance(el).show();
  }

  document.addEventListener('DOMContentLoaded', function () {
    var button = document.getElementById('adminConfirmButton');
    if (!button) {
      return;
    }
    button.addEventListener('click', function () {
      var callback = onConfirmCallback;
      onConfirmCallback = null;
      hide();
      if (typeof callback === 'function') {
        callback();
      }
    });
  });

  return { show: show };
})();
