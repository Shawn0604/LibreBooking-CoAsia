/**
 * Minimal client-side pager for the new `.admin-clean-table` lists (預約管理/會議室管理/
 * 開放時間管理), replacing DataTables now that its accordion/Buttons/x-editable chrome is gone.
 * Every filtered row is still rendered server-side in one response, same as before -- this just
 * shows/hides <tr> by page instead of DataTables doing it.
 *
 * AdminTablePaginate({ tableId: 'reservationList', containerId: 'reservationPager', pageSize: 20 })
 *   -> { goToPage(n), refresh() }
 */
function AdminTablePaginate(options) {
  var pageSize = options.pageSize || 20;
  var $table = $('#' + options.tableId);
  var $container = options.containerId ? $('#' + options.containerId) : null;
  var $rows = [];
  var currentPage = 1;

  function totalPages() {
    return Math.max(1, Math.ceil($rows.length / pageSize));
  }

  function renderRows() {
    var start = (currentPage - 1) * pageSize;
    var end = start + pageSize;
    $rows.each(function (i) {
      $(this).toggleClass('d-none', !(i >= start && i < end));
    });
  }

  function renderControls() {
    if (!$container) {
      return;
    }
    $container.empty();

    var pages = totalPages();
    if (pages <= 1 || $rows.length === 0) {
      return;
    }

    var $prev = $('<button type="button" class="ghost-btn admin-pager-btn"></button>')
      .html('<i class="bi bi-chevron-left"></i>')
      .prop('disabled', currentPage === 1)
      .on('click', function () {
        goToPage(currentPage - 1);
      });

    var $next = $('<button type="button" class="ghost-btn admin-pager-btn"></button>')
      .html('<i class="bi bi-chevron-right"></i>')
      .prop('disabled', currentPage === pages)
      .on('click', function () {
        goToPage(currentPage + 1);
      });

    var $status = $('<span class="admin-pager-status"></span>').text(currentPage + ' / ' + pages);

    $container.append($prev, $status, $next);
  }

  function render() {
    renderRows();
    renderControls();
  }

  function goToPage(page) {
    currentPage = Math.min(Math.max(1, page), totalPages());
    render();
  }

  function refresh() {
    // .admin-search-hidden is a separate marker from the d-none this module itself toggles below --
    // sitepages combining this pager with a client-side text filter (e.g. 使用者管理) mark
    // non-matching rows with that class first, so refresh() paginates only over what's left.
    $rows = $table.find('> tbody > tr').not('.admin-search-hidden');
    currentPage = 1;
    render();
  }

  refresh();

  return { goToPage: goToPage, refresh: refresh };
}
