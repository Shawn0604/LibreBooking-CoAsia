{* Shared destructive-action confirm dialog, driven by Web/scripts/admin/admin-confirm.js
   (AdminConfirm.show({title, message, confirmLabel, onConfirm})). One instance per page,
   included from admin_page_shell.tpl so every migrated admin page has it for free -- replaces
   the old per-page delete modals (manage_reservations.tpl's #deleteInstanceDialog/
   #deleteSeriesDialog, manage_resources.tpl's #deletePrompt, etc.) and bare window.confirm()
   calls with one consistent red/two-step pattern. *}

<div class="modal fade admin-drawer-confirm" id="adminConfirmDialog" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adminConfirmTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="adminConfirmMessage" class="mb-0"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{translate key=Cancel}</button>
                <button type="button" class="btn btn-danger" id="adminConfirmButton"></button>
            </div>
        </div>
    </div>
</div>
