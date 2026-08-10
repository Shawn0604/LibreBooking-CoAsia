{include file='globalheader.tpl'}

<div id="server-settings-page" class="admin-page">
	{include file='Admin/admin_page_shell.tpl' TitleKey='ServerSettings'}

	<div class="d-flex justify-content-between align-items-center mb-2">
		<div class="text-muted small" id="diagLastCheckedAll"></div>
		<button type="button" class="btn btn-outline-secondary btn-sm" id="diagRecheckAll">
			<i class="bi bi-arrow-clockwise"></i> {translate key=DiagnosticRecheckAll}
		</button>
	</div>

	<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3 mb-4">
		<div class="col">
			<div class="card shadow-sm h-100 diag-card" id="diagCard-checkAd" data-check="checkAd">
				<div class="card-body">
					<div class="d-flex align-items-center gap-2 mb-2">
						<i class="bi bi-diagram-3-fill"></i>
						<span class="fw-bold">{translate key=AdminAdSyncStatus}</span>
					</div>
					<span class="admin-badge admin-badge-ended diag-status">{translate key=DiagnosticStatusChecking}</span>
					<div class="text-muted small mt-2 diag-checkedat"></div>
					<div class="small mt-2 diag-message d-none"></div>
				</div>
			</div>
		</div>
		<div class="col">
			<div class="card shadow-sm h-100 diag-card" id="diagCard-checkSmtp" data-check="checkSmtp">
				<div class="card-body">
					<div class="d-flex align-items-center gap-2 mb-2">
						<i class="bi bi-envelope-fill"></i>
						<span class="fw-bold">{translate key=DiagnosticSmtpTitle}</span>
					</div>
					<span class="admin-badge admin-badge-ended diag-status">{translate key=DiagnosticStatusChecking}</span>
					<div class="text-muted small mt-2 diag-checkedat"></div>
					<div class="small mt-2 diag-message d-none"></div>
				</div>
			</div>
		</div>
		<div class="col">
			<div class="card shadow-sm h-100 diag-card" id="diagCard-checkLine" data-check="checkLine">
				<div class="card-body">
					<div class="d-flex align-items-center gap-2 mb-2">
						<i class="bi bi-chat-dots-fill"></i>
						<span class="fw-bold">{translate key=DiagnosticLineTitle}</span>
					</div>
					<span class="admin-badge admin-badge-ended diag-status">{translate key=DiagnosticStatusChecking}</span>
					<div class="text-muted small mt-2 diag-checkedat"></div>
					<div class="text-muted small diag-lastwebhook"></div>
					<div class="small mt-2 diag-message d-none"></div>
				</div>
			</div>
		</div>
		<div class="col">
			<div class="card shadow-sm h-100 diag-card" id="diagCard-checkFiles" data-check="checkFiles">
				<div class="card-body">
					<div class="d-flex align-items-center gap-2 mb-2">
						<i class="bi bi-folder-check"></i>
						<span class="fw-bold">{translate key=DiagnosticFilesTitle}</span>
					</div>
					<span class="admin-badge admin-badge-ended diag-status">{translate key=DiagnosticStatusChecking}</span>
					<div class="text-muted small mt-2 diag-checkedat"></div>
					<div class="small mt-2 diag-message d-none"></div>
				</div>
			</div>
		</div>
	</div>

	<h2 class="fs-5 mb-2">{translate key=DiagnosticDocsHeading}</h2>
	<div class="admin-entry-grid">
		<a class="admin-entry-card" href="{$Path}admin/system_architecture.php">
			<i class="bi bi-diagram-2-fill"></i>
			<span>{translate key="DiagnosticArchitecture"}</span>
		</a>
		<a class="admin-entry-card" href="{$Path}admin/operations_doc.php">
			<i class="bi bi-file-text-fill"></i>
			<span>{translate key="DiagnosticOpsDoc"}</span>
		</a>
	</div>
</div>

{include file="javascript-includes.tpl"}
{jsfile src="admin/server-settings.js"}
<script>
	$(function () {
		var settings = new ServerSettingsDiagnostics({
			checkUrl: '{$smarty.server.SCRIPT_NAME}',
			messages: {
				ok: '{translate key=DiagnosticStatusOk|escape:javascript}',
				error: '{translate key=DiagnosticStatusError|escape:javascript}',
				unconfigured: '{translate key=DiagnosticStatusUnconfigured|escape:javascript}',
				checking: '{translate key=DiagnosticStatusChecking|escape:javascript}',
				lastChecked: '{translate key=DiagnosticLastChecked|escape:javascript}',
				lastWebhook: '{translate key=DiagnosticLastWebhookReceived|escape:javascript}',
				neverReceived: '{translate key=DiagnosticNeverReceived|escape:javascript}'
			}
		});
		settings.init();
	});
</script>
{include file='globalfooter.tpl'}
