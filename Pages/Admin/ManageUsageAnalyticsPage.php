<?php

require_once(ROOT_DIR . 'Pages/Admin/AdminPage.php');
require_once(ROOT_DIR . 'Domain/Values/ResourceLevel.php');
require_once(ROOT_DIR . 'Domain/Values/ReservationStatus.php');
require_once(ROOT_DIR . 'Domain/Values/ResourceLocation.php');

/**
 * "會議室使用分析" -- aggregate usage stats over a date range (reservation count/hours, average
 * duration, popular hour-of-day, per-room and per-city ranking), with an Excel export of the
 * same numbers. Deliberately raw SQL with no new repository abstraction, same rationale as
 * ManageOverviewPage: read-only, each query is a one-off aggregate shape not reused elsewhere.
 */
class ManageUsageAnalyticsPage extends AdminPage
{
    public function __construct()
    {
        parent::__construct('AdminUsageAnalytics');
    }

    public function PageLoad()
    {
        $db = ServiceLocator::GetDatabase();
        $timezone = ServiceLocator::GetServer()->GetUserSession()->Timezone;

        $startDateString = $this->GetQuerystring(QueryStringKeys::START_DATE);
        $endDateString = $this->GetQuerystring(QueryStringKeys::END_DATE);
        $location = trim((string) $this->GetQuerystring(QueryStringKeys::LOCATION));
        $resourceIdString = $this->GetQuerystring(QueryStringKeys::RESOURCE_ID);
        $resourceId = !empty($resourceIdString) ? (int) $resourceIdString : null;

        $startDate = !empty($startDateString)
            ? Date::Parse($startDateString, $timezone)
            : Date::Now()->AddDays(-30)->ToTimezone($timezone)->GetDate();
        // Filters are inclusive end dates picked from a date-only picker (e.g. "2026/08/04"), so
        // the upper SQL bound has to be midnight of the *next* day to include all of that day's
        // reservations, not exclude everything on it.
        $endDateExclusive = !empty($endDateString)
            ? Date::Parse($endDateString, $timezone)->AddDays(1)
            : Date::Now()->ToTimezone($timezone)->GetDate()->AddDays(1);

        $summary = $this->GetSummary($db, $startDate, $endDateExclusive, $location, $resourceId);
        $hourly = $this->GetHourlyBreakdown($db, $startDate, $endDateExclusive, $location, $resourceId);
        $byResource = $this->GetUsageByResource($db, $startDate, $endDateExclusive, $location, $resourceId);
        $byLocation = $this->GetUsageByLocation($db, $startDate, $endDateExclusive, $location, $resourceId);

        if ($this->GetQuerystring(QueryStringKeys::DATA_REQUEST) == 'export') {
            $this->ExportExcel($summary, $hourly, $byResource, $byLocation, $startDate, $endDateExclusive->AddDays(-1));
            return;
        }

        $this->Set('StartDate', $startDate);
        $this->Set('EndDate', $endDateExclusive->AddDays(-1));
        $this->Set('LocationFilter', $location);
        $this->Set('ResourceIdFilter', $resourceId);
        $this->Set('Locations', $this->GetLocations($db));
        $this->Set('ResourceOptions', $this->GetResourceOptions($db));
        $this->Set('Summary', $summary);
        $this->Set('HourlyBreakdown', $hourly);
        $this->Set('UsageByResource', $byResource);
        $this->Set('UsageByLocation', $byLocation);
        $this->Set('Timezone', $timezone);

        $url = $this->server->GetUrl();
        $exportUrl = BookedStringHelper::Contains($url, '?') ? $url . '&dr=export' : $this->server->GetRequestUri() . '?dr=export';
        $this->Set('ExportUrl', $exportUrl);

        $this->Display('UsageAnalytics/manage_usage_analytics.tpl');
    }

    /**
     * @return array{sql: string, params: Parameter[]}
     */
    private function BuildFilter($location, $resourceId)
    {
        $sql = '';
        $params = [];

        if (!empty($location)) {
            $sql .= ' AND r.location = @location';
            $params[] = new Parameter('@location', $location);
        }

        if (!empty($resourceId)) {
            $sql .= ' AND r.resource_id = @resourceId';
            $params[] = new Parameter('@resourceId', $resourceId);
        }

        return ['sql' => $sql, 'params' => $params];
    }

    private function BaseParams($startDate, $endDateExclusive)
    {
        return [
            new Parameter('@primaryLevel', ResourceLevel::Primary),
            new Parameter('@deletedStatus', ReservationStatus::Deleted),
            new Parameter('@startDate', $startDate->ToDatabase()),
            new Parameter('@endDate', $endDateExclusive->ToDatabase()),
        ];
    }

    private function AddParams($command, $params)
    {
        foreach ($params as $param) {
            $command->AddParameter($param);
        }
    }

    private function GetSummary($db, $startDate, $endDateExclusive, $location, $resourceId)
    {
        $filter = $this->BuildFilter($location, $resourceId);
        $command = new SqlCommand(
            'SELECT COUNT(*) AS total_count,
                    COALESCE(SUM(TIMESTAMPDIFF(MINUTE, ri.start_date, ri.end_date)), 0) AS total_minutes
             FROM reservation_instances ri
             INNER JOIN reservation_series rs ON rs.series_id = ri.series_id
             INNER JOIN reservation_resources rr ON rr.series_id = rs.series_id AND rr.resource_level_id = @primaryLevel
             INNER JOIN resources r ON r.resource_id = rr.resource_id
             WHERE rs.status_id != @deletedStatus AND ri.start_date >= @startDate AND ri.start_date < @endDate' . $filter['sql']
        );
        $this->AddParams($command, array_merge($this->BaseParams($startDate, $endDateExclusive), $filter['params']));

        $reader = $db->Query($command);
        $row = $reader->GetRow();
        $reader->Free();

        $totalCount = (int) ($row['total_count'] ?? 0);
        $totalMinutes = (int) ($row['total_minutes'] ?? 0);

        return [
            'totalCount' => $totalCount,
            'totalMinutes' => $totalMinutes,
            'totalHours' => round($totalMinutes / 60, 1),
            'averageMinutes' => $totalCount > 0 ? (int) round($totalMinutes / $totalCount) : 0,
        ];
    }

    /**
     * @return list<array{hour: int, count: int}> always 24 entries, hour 0-23, zero-filled
     */
    private function GetHourlyBreakdown($db, $startDate, $endDateExclusive, $location, $resourceId)
    {
        $filter = $this->BuildFilter($location, $resourceId);
        $command = new SqlCommand(
            'SELECT HOUR(ri.start_date) AS hour, COUNT(*) AS total_count
             FROM reservation_instances ri
             INNER JOIN reservation_series rs ON rs.series_id = ri.series_id
             INNER JOIN reservation_resources rr ON rr.series_id = rs.series_id AND rr.resource_level_id = @primaryLevel
             INNER JOIN resources r ON r.resource_id = rr.resource_id
             WHERE rs.status_id != @deletedStatus AND ri.start_date >= @startDate AND ri.start_date < @endDate' . $filter['sql'] . '
             GROUP BY HOUR(ri.start_date)'
        );
        $this->AddParams($command, array_merge($this->BaseParams($startDate, $endDateExclusive), $filter['params']));

        $reader = $db->Query($command);
        $counts = array_fill(0, 24, 0);
        while ($row = $reader->GetRow()) {
            $counts[(int) $row['hour']] = (int) $row['total_count'];
        }
        $reader->Free();

        $result = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $result[] = ['hour' => $hour, 'label' => sprintf('%02d:00', $hour), 'count' => $counts[$hour]];
        }
        return $result;
    }

    /**
     * @return list<array{resourceId: int, name: string, location: string, count: int, minutes: int, hours: float}>
     */
    private function GetUsageByResource($db, $startDate, $endDateExclusive, $location, $resourceId)
    {
        $filter = $this->BuildFilter($location, $resourceId);
        $command = new SqlCommand(
            'SELECT r.resource_id, r.name, r.location, COUNT(*) AS total_count,
                    COALESCE(SUM(TIMESTAMPDIFF(MINUTE, ri.start_date, ri.end_date)), 0) AS total_minutes
             FROM reservation_instances ri
             INNER JOIN reservation_series rs ON rs.series_id = ri.series_id
             INNER JOIN reservation_resources rr ON rr.series_id = rs.series_id AND rr.resource_level_id = @primaryLevel
             INNER JOIN resources r ON r.resource_id = rr.resource_id
             WHERE rs.status_id != @deletedStatus AND ri.start_date >= @startDate AND ri.start_date < @endDate' . $filter['sql'] . '
             GROUP BY r.resource_id, r.name, r.location
             ORDER BY total_count DESC, total_minutes DESC'
        );
        $this->AddParams($command, array_merge($this->BaseParams($startDate, $endDateExclusive), $filter['params']));

        $reader = $db->Query($command);
        $rows = [];
        while ($row = $reader->GetRow()) {
            $minutes = (int) $row['total_minutes'];
            $rows[] = [
                'resourceId' => (int) $row['resource_id'],
                'name' => $row['name'],
                'location' => $row['location'],
                'count' => (int) $row['total_count'],
                'minutes' => $minutes,
                'hours' => round($minutes / 60, 1),
            ];
        }
        $reader->Free();

        return $rows;
    }

    /**
     * @return list<array{location: string, count: int, minutes: int, hours: float}>
     */
    private function GetUsageByLocation($db, $startDate, $endDateExclusive, $location, $resourceId)
    {
        $filter = $this->BuildFilter($location, $resourceId);
        $command = new SqlCommand(
            "SELECT COALESCE(NULLIF(TRIM(r.location), ''), '__unassigned__') AS bucket_location,
                    COUNT(*) AS total_count,
                    COALESCE(SUM(TIMESTAMPDIFF(MINUTE, ri.start_date, ri.end_date)), 0) AS total_minutes
             FROM reservation_instances ri
             INNER JOIN reservation_series rs ON rs.series_id = ri.series_id
             INNER JOIN reservation_resources rr ON rr.series_id = rs.series_id AND rr.resource_level_id = @primaryLevel
             INNER JOIN resources r ON r.resource_id = rr.resource_id
             WHERE rs.status_id != @deletedStatus AND ri.start_date >= @startDate AND ri.start_date < @endDate" . $filter['sql'] . '
             GROUP BY bucket_location
             ORDER BY total_count DESC, total_minutes DESC'
        );
        $this->AddParams($command, array_merge($this->BaseParams($startDate, $endDateExclusive), $filter['params']));

        $reader = $db->Query($command);
        $rows = [];
        while ($row = $reader->GetRow()) {
            $minutes = (int) $row['total_minutes'];
            $rows[] = [
                'location' => $row['bucket_location'],
                'count' => (int) $row['total_count'],
                'minutes' => $minutes,
                'hours' => round($minutes / 60, 1),
            ];
        }
        $reader->Free();

        return $rows;
    }

    /**
     * @return list<string> the fixed 4-region whitelist (Domain/Values/ResourceLocation.php),
     *   for the 城市 filter dropdown. Was previously `SELECT DISTINCT location FROM
     *   resources` -- a single typo in the old free-text field could silently add a stray 5th
     *   "region" here; the whitelist write-path validation added alongside this (Manage
     *   Resources' drawer/bulk-edit/CSV import) makes that no longer possible, so this can
     *   just be the fixed list directly instead of re-deriving it from live data every load.
     */
    private function GetLocations($db)
    {
        return ResourceLocation::All();
    }

    /**
     * @return list<array{id: int, name: string}> for the 會議室 filter dropdown, 依地區排序
     *   (台北→深圳→上海→跨地區，同地區內依名稱排序) -- ResourceLocation::SortRank() sorts in PHP
     *   rather than `ORDER BY location`, since MySQL's default collation wouldn't necessarily
     *   sort the 4 region strings in that intended order.
     */
    private function GetResourceOptions($db)
    {
        $reader = $db->Query(new SqlCommand('SELECT resource_id, name, location FROM resources'));
        $resources = [];
        while ($row = $reader->GetRow()) {
            $resources[] = ['id' => (int) $row['resource_id'], 'name' => $row['name'], 'location' => $row['location']];
        }
        $reader->Free();

        usort($resources, function ($a, $b) {
            $rankDiff = ResourceLocation::SortRank($a['location']) <=> ResourceLocation::SortRank($b['location']);
            return $rankDiff !== 0 ? $rankDiff : strcasecmp($a['name'], $b['name']);
        });

        return $resources;
    }

    private function ExportExcel($summary, $hourly, $byResource, $byLocation, $startDate, $endDate)
    {
        $resources = Resources::GetInstance();
        $timezone = ServiceLocator::GetServer()->GetUserSession()->Timezone;
        $dateFormat = $resources->GetDateFormat('general_date');
        $rangeLabel = $startDate->ToTimezone($timezone)->Format($dateFormat) . ' - ' . $endDate->ToTimezone($timezone)->Format($dateFormat);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle(mb_substr($resources->GetString('AdminUsageAnalyticsSummary'), 0, 31));
        $summarySheet->fromArray([
            [$resources->GetString('AdminUsageAnalyticsDateRange'), $rangeLabel],
            [$resources->GetString('AdminUsageAnalyticsTotalReservations'), $summary['totalCount']],
            [$resources->GetString('AdminUsageAnalyticsTotalHours'), $summary['totalHours']],
            [$resources->GetString('AdminUsageAnalyticsAverageDuration') . ' (' . $resources->GetString('minutes') . ')', $summary['averageMinutes']],
        ]);

        $hourlySheet = $spreadsheet->createSheet();
        $hourlySheet->setTitle(mb_substr($resources->GetString('AdminUsageAnalyticsPopularHours'), 0, 31));
        $hourlySheet->fromArray(array_merge(
            [[$resources->GetString('AdminUsageAnalyticsHour'), $resources->GetString('AdminUsageAnalyticsTotalReservations')]],
            array_map(function ($row) {
                return [sprintf('%02d:00', $row['hour']), $row['count']];
            }, $hourly)
        ));

        $resourceSheet = $spreadsheet->createSheet();
        $resourceSheet->setTitle(mb_substr($resources->GetString('AdminUsageAnalyticsByResource'), 0, 31));
        $resourceSheet->fromArray(array_merge(
            [[$resources->GetString('Resource'), $resources->GetString('Location'), $resources->GetString('AdminUsageAnalyticsTotalReservations'), $resources->GetString('AdminUsageAnalyticsTotalHours')]],
            array_map(function ($row) {
                return [$row['name'], $row['location'], $row['count'], $row['hours']];
            }, $byResource)
        ));

        $locationSheet = $spreadsheet->createSheet();
        $locationSheet->setTitle(mb_substr($resources->GetString('AdminUsageAnalyticsByLocation'), 0, 31));
        $locationSheet->fromArray(array_merge(
            [[$resources->GetString('Location'), $resources->GetString('AdminUsageAnalyticsTotalReservations'), $resources->GetString('AdminUsageAnalyticsTotalHours')]],
            array_map(function ($row) use ($resources) {
                $label = $row['location'] === '__unassigned__' ? $resources->GetString('AdminUsageAnalyticsUnassignedLocation') : $row['location'];
                return [$label, $row['count'], $row['hours']];
            }, $byLocation)
        ));

        $spreadsheet->setActiveSheetIndex(0);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="usage-analytics.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
    }
}
