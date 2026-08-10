{include file='globalheader.tpl'}

<div id="operations-doc-page" class="admin-page">
	{include file='Admin/admin_page_shell.tpl' TitleKey='DiagnosticOpsDoc' DescriptionKey='DiagnosticOpsDocDescription'}

	<div class="card shadow-sm">
		<div class="card-body">
			{if $DocContent}
				<pre style="white-space: pre-wrap; word-break: break-word; font-size: 13px;">{$DocContent|escape:'html'}</pre>
			{else}
				<div class="admin-empty-state">
					<i class="bi bi-file-earmark-x"></i>
					<p>COASIA-DEPLOYMENT.md not found.</p>
				</div>
			{/if}
		</div>
	</div>
</div>

{include file="javascript-includes.tpl"}
{include file='globalfooter.tpl'}
