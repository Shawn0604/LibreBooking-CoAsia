{include file='globalheader.tpl'}

<div id="page-usage-analytics" class="admin-page">
	{capture name=headerActions}
		<a href="{$ExportUrl}" class="btn btn-primary btn-sm" id="export-usage-analytics">
			<i class="bi bi-file-earmark-spreadsheet me-1"></i>{translate key="AdminUsageAnalyticsExportExcel"}
		</a>
	{/capture}
	{include file='Admin/admin_page_shell.tpl' TitleKey='AdminUsageAnalytics' DescriptionKey='AdminUsageAnalyticsDescription' HeaderActions=$smarty.capture.headerActions}

	<div class="admin-page-toolbar">
		<div class="admin-toolbar-row">
			<div class="d-flex align-items-center gap-1">
				<label for="startDate" class="visually-hidden">{translate key='BeginDate'}</label>
				<input id="startDate" type="text" class="form-control form-control-sm" style="width:8.5em"
					placeholder="{translate key='BeginDate'}" />
				<span>&ndash;</span>
				<label for="endDate" class="visually-hidden">{translate key='EndDate'}</label>
				<input id="endDate" type="text" class="form-control form-control-sm" style="width:8.5em"
					placeholder="{translate key='EndDate'}" />
			</div>
			{* Fixed 4-region whitelist (Domain/Values/ResourceLocation.php) -- explicit options
			   (not {html_options} over $Locations) so this reads the fuller "台北實體會議室"-style
			   label everywhere else in the admin center uses for the same 4 regions, even though
			   the underlying value/GetUsageByLocation() bucket key stays the short 台北/深圳/上海/
			   跨地區 string. *}
			<select id="locationFilter" class="form-select form-select-sm admin-toolbar-select" aria-label="{translate key='Location'}">
				<option value="">{translate key='AdminUsageAnalyticsAllLocations'}</option>
				<option value="{ResourceLocation::TAIPEI}" {if $LocationFilter == ResourceLocation::TAIPEI}selected{/if}>{translate key=ResourceLocationTaipei}</option>
				<option value="{ResourceLocation::SHENZHEN}" {if $LocationFilter == ResourceLocation::SHENZHEN}selected{/if}>{translate key=ResourceLocationShenzhen}</option>
				<option value="{ResourceLocation::SHANGHAI}" {if $LocationFilter == ResourceLocation::SHANGHAI}selected{/if}>{translate key=ResourceLocationShanghai}</option>
				<option value="{ResourceLocation::CROSS_REGION}" {if $LocationFilter == ResourceLocation::CROSS_REGION}selected{/if}>{translate key=ResourceLocationCrossRegion}</option>
			</select>
			<select id="resourceFilter" class="form-select form-select-sm admin-toolbar-select" aria-label="{translate key='Resource'}">
				<option value="">{translate key='AllResources'}</option>
				{foreach from=$ResourceOptions item=resourceOption}
					<option value="{$resourceOption.id}" {if $ResourceIdFilter == $resourceOption.id}selected{/if}>{$resourceOption.name}</option>
				{/foreach}
			</select>
			<button type="button" class="btn btn-primary btn-sm" id="applyUsageFilter">
				<i class="bi bi-search me-1"></i>{translate key='Filter'}
			</button>
			<a href="{$smarty.server.SCRIPT_NAME}" class="btn btn-outline-secondary btn-sm">{translate key='Reset'}</a>
		</div>
	</div>

	<div class="admin-stat-cards">
		<div class="admin-card admin-stat-card">
			<i class="bi bi-calendar-check admin-stat-card-icon"></i>
			<div>
				<span class="admin-stat-value">{$Summary.totalCount}</span>
				<span class="admin-stat-label">{translate key="AdminUsageAnalyticsTotalReservations"}</span>
			</div>
		</div>
		<div class="admin-card admin-stat-card">
			<i class="bi bi-clock-history admin-stat-card-icon admin-stat-card-icon-inuse"></i>
			<div>
				<span class="admin-stat-value">{$Summary.totalHours}</span>
				<span class="admin-stat-label">{translate key="AdminUsageAnalyticsTotalHours"}</span>
			</div>
		</div>
		<div class="admin-card admin-stat-card">
			<i class="bi bi-stopwatch admin-stat-card-icon admin-stat-card-icon-available"></i>
			<div>
				<span class="admin-stat-value">{$Summary.averageMinutes}</span>
				<span class="admin-stat-label">{translate key="AdminUsageAnalyticsAverageDuration"}</span>
			</div>
		</div>
	</div>

	<div class="admin-card mb-3">
		<h2 class="admin-card-title">{translate key="AdminUsageAnalyticsPopularHours"}</h2>
		{if $Summary.totalCount > 0}
			<div style="position:relative; height:260px;">
				<canvas id="hourlyChart"></canvas>
			</div>
		{else}
			<div class="admin-empty">{translate key="AdminUsageAnalyticsNoResults"}</div>
		{/if}
	</div>

	<div class="admin-grid admin-grid-2col">
		<div class="admin-card">
			<h2 class="admin-card-title">{translate key="AdminUsageAnalyticsByResource"}</h2>
			{if $UsageByResource|count > 0}
				<div class="table-responsive">
					<table class="table admin-clean-table w-100">
						<thead>
							<tr>
								<th>{translate key="Resource"}</th>
								<th>{translate key="Location"}</th>
								<th class="text-end">{translate key="AdminUsageAnalyticsTotalReservations"}</th>
								<th class="text-end">{translate key="AdminUsageAnalyticsTotalHours"}</th>
							</tr>
						</thead>
						<tbody>
							{foreach from=$UsageByResource item=row}
								<tr>
									<td class="fw-bold">{$row.name}</td>
									<td>
										{if !empty($row.location)}
											{$row.location}
										{else}
											<span class="admin-cell-secondary">{translate key='NoLocationLabel'}</span>
										{/if}
									</td>
									<td class="text-end">{$row.count}</td>
									<td class="text-end">{$row.hours}</td>
								</tr>
							{/foreach}
						</tbody>
					</table>
				</div>
			{else}
				<div class="admin-empty">{translate key="AdminUsageAnalyticsNoResults"}</div>
			{/if}
		</div>

		<div class="admin-card">
			<h2 class="admin-card-title">{translate key="AdminUsageAnalyticsByLocation"}</h2>
			{if $UsageByLocation|count > 0}
				<div class="table-responsive">
					<table class="table admin-clean-table w-100">
						<thead>
							<tr>
								<th>{translate key="Location"}</th>
								<th class="text-end">{translate key="AdminUsageAnalyticsTotalReservations"}</th>
								<th class="text-end">{translate key="AdminUsageAnalyticsTotalHours"}</th>
							</tr>
						</thead>
						<tbody>
							{foreach from=$UsageByLocation item=row}
								<tr>
									<td class="fw-bold">
										{if $row.location == '__unassigned__'}
											{translate key='AdminUsageAnalyticsUnassignedLocation'}
										{else}
											{$row.location}
										{/if}
									</td>
									<td class="text-end">{$row.count}</td>
									<td class="text-end">{$row.hours}</td>
								</tr>
							{/foreach}
						</tbody>
					</table>
				</div>
			{else}
				<div class="admin-empty">{translate key="AdminUsageAnalyticsNoResults"}</div>
			{/if}
		</div>
	</div>

	{control type="DatePickerSetupControl" ControlId="startDate" DefaultDate=$StartDate}
	{control type="DatePickerSetupControl" ControlId="endDate" DefaultDate=$EndDate}

	{include file="javascript-includes.tpl"}
	{vendor_js src="chart.js/4.4.9/js/chart.umd.min.js"}
	<script>
		(function () {
			document.getElementById('applyUsageFilter').addEventListener('click', function () {
				var params = [];
				var sd = document.getElementById('startDate').value;
				var ed = document.getElementById('endDate').value;
				var loc = document.getElementById('locationFilter').value;
				var rid = document.getElementById('resourceFilter').value;
				if (sd) { params.push('{QueryStringKeys::START_DATE}=' + encodeURIComponent(sd)); }
				if (ed) { params.push('{QueryStringKeys::END_DATE}=' + encodeURIComponent(ed)); }
				if (loc) { params.push('{QueryStringKeys::LOCATION}=' + encodeURIComponent(loc)); }
				if (rid) { params.push('{QueryStringKeys::RESOURCE_ID}=' + encodeURIComponent(rid)); }
				window.location = '{$smarty.server.SCRIPT_NAME}' + (params.length ? '?' + params.join('&') : '');
			});

			{if $Summary.totalCount > 0}
				var ctx = document.getElementById('hourlyChart').getContext('2d');
				new Chart(ctx, {
					type: 'bar',
					data: {
						labels: [{foreach from=$HourlyBreakdown item=row}'{$row.label}',{/foreach}],
						datasets: [{
							label: '{translate key="AdminUsageAnalyticsTotalReservations"|escape:javascript}',
							data: [{foreach from=$HourlyBreakdown item=row}{$row.count},{/foreach}],
							backgroundColor: '#1f3159',
							borderRadius: 4,
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						plugins: { legend: { display: false } },
						scales: {
							y: { beginAtZero: true, ticks: { precision: 0 } }
						}
					}
				});
			{/if}
		})();
	</script>
</div>
{include file='globalfooter.tpl'}
