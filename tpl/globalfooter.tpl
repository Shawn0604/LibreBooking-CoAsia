	</div><!-- close main-->
	{if !isset($HideNavBar) || $HideNavBar == false}
		</div><!-- close app-content -->
	</div><!-- close app-shell -->
	{else}
		</div><!-- close app-content bare -->
	{/if}
	<div id="button-up" class="bg-primary rounded-circle text-white">
		<i class="bi bi-chevron-double-up" aria-hidden="true"></i>
	</div>

	<div class="toast-container position-fixed bottom-0 end-0 p-3">
		<div id="clipboardCopyToast" class="toast align-items-center bg-primary text-white border-0 d-none" role="alert"
			aria-live="assertive" aria-atomic="true">
			<div class="d-flex">
				<div class="toast-body">
					<i class="bi bi-check-circle-fill me-2"></i>{translate key=UrlCopiedToClipboard}
				</div>
				<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
					aria-label="{translate key=Close}"></button>
			</div>
		</div>
		<div id="defaultSetToast" class="toast align-items-center bg-primary text-white border-0 d-none" role="alert"
			aria-live="assertive" aria-atomic="true">
			<div class="d-flex">
				<div class="toast-body">
					<i class="bi bi-check-circle-fill me-2"></i>{translate key=DefaultScheduleSet}
				</div>
				<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
					aria-label="{translate key=Close}"></button>
			</div>
		</div>
		<div id="resourcePickerDefaultSetToast" class="toast align-items-center bg-primary text-white border-0 d-none" role="alert"
			aria-live="assertive" aria-atomic="true">
			<div class="d-flex">
				<div class="toast-body">
					<i class="bi bi-check-circle-fill me-2"></i>{translate key=DefaultResourceSet}
				</div>
				<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
					aria-label="{translate key=Close}"></button>
			</div>
		</div>
		<div id="notificationPreferencesSavedToast" class="toast align-items-center bg-primary text-white border-0 d-none" role="alert"
			aria-live="assertive" aria-atomic="true">
			<div class="d-flex">
				<div class="toast-body">
					<i class="bi bi-check-circle-fill me-2"></i>{translate key=YourSettingsWereUpdated}
				</div>
				<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
					aria-label="{translate key=Close}"></button>
			</div>
		</div>
		<div id="lineTestNotificationToast" class="toast align-items-center bg-primary text-white border-0 d-none" role="alert"
			aria-live="assertive" aria-atomic="true">
			<div class="d-flex">
				<div class="toast-body">
					<i class="bi bi-check-circle-fill me-2"></i>{translate key=LineTestNotificationSent}
				</div>
				<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
					aria-label="{translate key=Close}"></button>
			</div>
		</div>
		<div id="wecomTestNotificationToast" class="toast align-items-center bg-primary text-white border-0 d-none" role="alert"
			aria-live="assertive" aria-atomic="true">
			<div class="d-flex">
				<div class="toast-body">
					<i class="bi bi-check-circle-fill me-2"></i>{translate key=WeComTestNotificationSent}
				</div>
				<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
					aria-label="{translate key=Close}"></button>
			</div>
		</div>
	</div>

	<script type="text/javascript">
		init();
	</script>

	{if !empty($GoogleAnalyticsTrackingId)}
		<!-- Google tag (gtag.js) - Google Analytics -->
		<script async src="https://www.googletagmanager.com/gtag/js?id={$GoogleAnalyticsTrackingId}"></script>
		{literal}
			<script>
				window.dataLayer = window.dataLayer || [];
				function gtag(){dataLayer.push(arguments);}
				gtag('js', new Date());
			{/literal}
			gtag('config', '{$GoogleAnalyticsTrackingId}');
		</script>
	{/if}

	</body>

</html>
