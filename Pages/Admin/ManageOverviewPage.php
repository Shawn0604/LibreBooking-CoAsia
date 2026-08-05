<?php

require_once(ROOT_DIR . 'Pages/Admin/AdminPage.php');
require_once(ROOT_DIR . 'Domain/Values/ResourceLevel.php');
require_once(ROOT_DIR . 'Domain/Values/ResourceStatus.php');
require_once(ROOT_DIR . 'Domain/Values/ReservationStatus.php');
require_once(ROOT_DIR . 'Domain/Values/AccountStatus.php');

/**
 * "管理總覽" — the landing page for 管理中心 (see tpl/Admin/admin-nav.tpl). Read-only operational
 * dashboard: today's reservation stats, a live per-room status board, a 7-day trend + top-room
 * ranking, and a consolidated "需要處理" list -- deliberately simple SQL (no new repository
 * abstraction) since this page only ever reads, never writes, and each query is a one-off shape
 * not reused elsewhere.
 *
 * Every number here comes from a real query against real tables. Where no real data source
 * exists yet (AD sync runs, an Email/LINE send log), this page says so explicitly (see
 * GetNeedsAttention()'s config-only checks, and the removed AdminOverviewSystemStatusSummary
 * string that used to claim "AD Sync OK · Email OK · LINE OK" with nothing behind any of the
 * three) rather than ever showing an invented "all healthy" status.
 */
class ManageOverviewPage extends AdminPage
{
    public function __construct()
    {
        parent::__construct('AdminOverview');
    }

    public function PageLoad()
    {
        $db = ServiceLocator::GetDatabase();

        $todaysReservations = $this->GetTodaysReservations($db);
        $inProgressCount = 0;
        foreach ($todaysReservations as $reservation) {
            if ($reservation['live_status'] === 'inprogress') {
                $inProgressCount++;
            }
        }

        $liveResourceStatus = $this->GetLiveResourceStatus($db);
        $unavailableCount = 0;
        foreach ($liveResourceStatus as $resource) {
            if ($resource['status'] === 'unavailable') {
                $unavailableCount++;
            }
        }

        $this->Set('TodaysReservations', $todaysReservations);
        $this->Set('TodaysReservationsCount', count($todaysReservations));
        $this->Set('InProgressCount', $inProgressCount);
        $this->Set('StartingSoonCount', $this->GetStartingSoonCount($db));
        $this->Set('UnavailableResourceCount', $unavailableCount);
        $this->Set('LiveResourceStatus', $liveResourceStatus);
        $this->Set('WeeklyTrend', $this->GetWeeklyTrend($db));
        $this->Set('TopResources', $this->GetTopResources($db));
        $this->Set('NeedsAttention', $this->GetNeedsAttention($db));

        $this->Display('Overview/manage_overview.tpl');
    }

    private function GetTodaysReservations($db)
    {
        $command = new SqlCommand(
            'SELECT ri.reference_number, ri.start_date, ri.end_date, rs.title, rs.status_id,
                    r.name AS resource_name, r.location, u.fname, u.lname,
                    CASE
                        WHEN NOW() BETWEEN ri.start_date AND ri.end_date THEN \'inprogress\'
                        WHEN ri.start_date > NOW() THEN \'upcoming\'
                        ELSE \'ended\'
                    END AS live_status
             FROM reservation_instances ri
             INNER JOIN reservation_series rs ON rs.series_id = ri.series_id
             INNER JOIN reservation_resources rr ON rr.series_id = rs.series_id AND rr.resource_level_id = @primaryLevel
             INNER JOIN resources r ON r.resource_id = rr.resource_id
             INNER JOIN users u ON u.user_id = rs.owner_id
             WHERE DATE(ri.start_date) = CURDATE() AND rs.status_id != @deletedStatus
             ORDER BY ri.start_date
             LIMIT 20'
        );
        $command->AddParameter(new Parameter('@primaryLevel', ResourceLevel::Primary));
        $command->AddParameter(new Parameter('@deletedStatus', ReservationStatus::Deleted));

        $reader = $db->Query($command);
        $reservations = [];
        while ($row = $reader->GetRow()) {
            $reservations[] = $row;
        }
        $reader->Free();

        return $reservations;
    }

    /**
     * Reservations that haven't started yet but will within the next hour — a distinct signal
     * from "today's reservations" (which includes everything already ended or hours away) for
     * "what does the front desk need to be ready for right now".
     */
    private function GetStartingSoonCount($db)
    {
        $command = new SqlCommand(
            'SELECT COUNT(*) AS total
             FROM reservation_instances ri
             INNER JOIN reservation_series rs ON rs.series_id = ri.series_id
             WHERE ri.start_date > NOW() AND ri.start_date <= DATE_ADD(NOW(), INTERVAL 1 HOUR)
                   AND rs.status_id != @deletedStatus'
        );
        $command->AddParameter(new Parameter('@deletedStatus', ReservationStatus::Deleted));

        $reader = $db->Query($command);
        $row = $reader->GetRow();
        $reader->Free();

        return !empty($row) ? (int) $row['total'] : 0;
    }

    /**
     * Every AVAILABLE or UNAVAILABLE resource's live status right now: 'unavailable' (with its
     * status reason, if any) when administratively disabled/under maintenance, else 'inuse'
     * (with the current reservation's end time) when a reservation covers this exact moment,
     * else 'free' (with the next upcoming reservation's start time, or null when nothing is
     * scheduled) -- never just an aggregate percentage. Hidden resources are excluded; they
     * aren't meant to be operationally visible at all.
     *
     * @return list<array{id: int, name: string, location: string, status: string, until: ?string, reason: ?string}>
     */
    private function GetLiveResourceStatus($db)
    {
        $resourceCommand = new SqlCommand(
            'SELECT r.resource_id, r.name, r.location, r.status_id, rsr.description AS reason
             FROM resources r
             LEFT JOIN resource_status_reasons rsr ON rsr.resource_status_reason_id = r.resource_status_reason_id
             WHERE r.status_id != @hiddenStatus
             ORDER BY r.location, r.name'
        );
        $resourceCommand->AddParameter(new Parameter('@hiddenStatus', ResourceStatus::HIDDEN));
        $resourceReader = $db->Query($resourceCommand);
        $resources = [];
        while ($row = $resourceReader->GetRow()) {
            $resources[(int) $row['resource_id']] = [
                'id' => (int) $row['resource_id'],
                'name' => $row['name'],
                'location' => trim((string) $row['location']),
                'status_id' => (int) $row['status_id'],
                'reason' => $row['reason'],
            ];
        }
        $resourceReader->Free();

        // Current-or-next reservation per resource in one query: every not-yet-ended
        // reservation, ordered per-resource by start time -- the first row seen for a given
        // resource_id is either the one covering right now (if its own start has already
        // passed) or the next upcoming one. A single resource can't have two overlapping
        // reservations, so "first in this ordering" is unambiguous.
        $reservationCommand = new SqlCommand(
            'SELECT rr.resource_id, ri.start_date, ri.end_date
             FROM reservation_instances ri
             INNER JOIN reservation_series rs ON rs.series_id = ri.series_id
             INNER JOIN reservation_resources rr ON rr.series_id = rs.series_id AND rr.resource_level_id = @primaryLevel
             WHERE ri.end_date > NOW() AND rs.status_id != @deletedStatus
             ORDER BY rr.resource_id, ri.start_date'
        );
        $reservationCommand->AddParameter(new Parameter('@primaryLevel', ResourceLevel::Primary));
        $reservationCommand->AddParameter(new Parameter('@deletedStatus', ReservationStatus::Deleted));
        $reservationReader = $db->Query($reservationCommand);
        $nextByResource = [];
        while ($row = $reservationReader->GetRow()) {
            $resourceId = (int) $row['resource_id'];
            if (!isset($nextByResource[$resourceId])) {
                $nextByResource[$resourceId] = ['start_date' => $row['start_date'], 'end_date' => $row['end_date']];
            }
        }
        $reservationReader->Free();

        $now = Date::Now();
        $result = [];
        foreach ($resources as $resourceId => $resource) {
            if ($resource['status_id'] === ResourceStatus::UNAVAILABLE) {
                $result[] = [
                    'id' => $resource['id'],
                    'name' => $resource['name'],
                    'location' => $resource['location'],
                    'status' => 'unavailable',
                    'until' => null,
                    'reason' => $resource['reason'],
                ];
                continue;
            }

            $upcoming = $nextByResource[$resourceId] ?? null;
            if ($upcoming !== null && Date::Parse($upcoming['start_date'])->LessThanOrEqual($now)) {
                $result[] = [
                    'id' => $resource['id'],
                    'name' => $resource['name'],
                    'location' => $resource['location'],
                    'status' => 'inuse',
                    'until' => $upcoming['end_date'],
                    'reason' => null,
                ];
            } else {
                $result[] = [
                    'id' => $resource['id'],
                    'name' => $resource['name'],
                    'location' => $resource['location'],
                    'status' => 'free',
                    'until' => $upcoming !== null ? $upcoming['start_date'] : null,
                    'reason' => null,
                ];
            }
        }

        return $result;
    }

    /**
     * Daily reservation counts for the last 7 days (today inclusive), zero-filled -- the raw
     * GROUP BY only returns rows for days that actually had a reservation. `barHeight` is
     * pre-computed here (percent of the week's own max, never an unrelated fixed scale) so the
     * template only ever prints a plain number, not an inline expression.
     *
     * @return list<array{date: string, label: string, count: int, barHeight: int}>
     */
    private function GetWeeklyTrend($db)
    {
        $command = new SqlCommand(
            'SELECT DATE(ri.start_date) AS day, COUNT(*) AS total
             FROM reservation_instances ri
             INNER JOIN reservation_series rs ON rs.series_id = ri.series_id
             WHERE ri.start_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                   AND ri.start_date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                   AND rs.status_id != @deletedStatus
             GROUP BY DATE(ri.start_date)'
        );
        $command->AddParameter(new Parameter('@deletedStatus', ReservationStatus::Deleted));
        $reader = $db->Query($command);
        $countsByDay = [];
        while ($row = $reader->GetRow()) {
            $countsByDay[(string) $row['day']] = (int) $row['total'];
        }
        $reader->Free();

        $days = [];
        for ($offset = 6; $offset >= 0; $offset--) {
            $date = Date::Now()->AddDays(-1 * $offset);
            $key = $date->Format('Y-m-d');
            $days[] = [
                'date' => $key,
                'label' => $date->Format('n/j'),
                'count' => $countsByDay[$key] ?? 0,
            ];
        }

        $maxCount = max(1, max(array_column($days, 'count')));
        foreach ($days as &$day) {
            // Minimum 4% so a zero-reservation day still renders a visible sliver of a bar
            // instead of nothing at all.
            $day['barHeight'] = $day['count'] > 0 ? max(4, (int) round($day['count'] / $maxCount * 100)) : 4;
        }
        unset($day);

        return $days;
    }

    /**
     * Top 5 resources by reservation count over the last 7 days -- same query shape as
     * ManageUsageAnalyticsPage::GetUsageByResource(), narrowed to a fixed recent window and
     * ranking instead of a filterable report.
     *
     * @return list<array{id: int, name: string, location: string, count: int}>
     */
    private function GetTopResources($db)
    {
        $command = new SqlCommand(
            'SELECT r.resource_id, r.name, r.location, COUNT(*) AS total_count
             FROM reservation_instances ri
             INNER JOIN reservation_series rs ON rs.series_id = ri.series_id
             INNER JOIN reservation_resources rr ON rr.series_id = rs.series_id AND rr.resource_level_id = @primaryLevel
             INNER JOIN resources r ON r.resource_id = rr.resource_id
             WHERE rs.status_id != @deletedStatus
                   AND ri.start_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                   AND ri.start_date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
             GROUP BY r.resource_id, r.name, r.location
             ORDER BY total_count DESC, r.name
             LIMIT 5'
        );
        $command->AddParameter(new Parameter('@primaryLevel', ResourceLevel::Primary));
        $command->AddParameter(new Parameter('@deletedStatus', ReservationStatus::Deleted));

        $reader = $db->Query($command);
        $resources = [];
        while ($row = $reader->GetRow()) {
            $resources[] = [
                'id' => (int) $row['resource_id'],
                'name' => $row['name'],
                'location' => trim((string) $row['location']),
                'count' => (int) $row['total_count'],
            ];
        }
        $reader->Free();

        return $resources;
    }

    /**
     * @return list<array{level: string, message: string, link: ?string}>
     */
    private function GetNeedsAttention($db)
    {
        $items = [];

        if (!Configuration::Instance()->GetKey(ConfigKeys::EMAIL_ENABLED, new BooleanConverter())) {
            $items[] = [
                'level' => 'danger',
                'message' => Resources::GetInstance()->GetString('AdminWarningEmailDisabled'),
                'link' => 'admin/notification_log.php',
            ];
        }

        $lineEnabled = Configuration::Instance()->GetKey(ConfigKeys::LINE_ENABLED, new BooleanConverter());
        if ($lineEnabled && empty(Configuration::Instance()->GetKey(ConfigKeys::LINE_CHANNEL_ACCESS_TOKEN))) {
            $items[] = [
                'level' => 'warning',
                'message' => Resources::GetInstance()->GetString('AdminWarningLineMisconfigured'),
                'link' => 'admin/notification_log.php',
            ];
        }

        $pendingCommand = new SqlCommand('SELECT COUNT(*) AS total FROM reservation_series WHERE status_id = @pendingStatus');
        $pendingCommand->AddParameter(new Parameter('@pendingStatus', ReservationStatus::Pending));
        $pendingReader = $db->Query($pendingCommand);
        $pendingCount = $pendingReader->GetRow();
        $pendingReader->Free();
        if (!empty($pendingCount) && (int) $pendingCount['total'] > 0) {
            $items[] = [
                'level' => 'info',
                'message' => Resources::GetInstance()->GetString('AdminWarningPendingApproval', (int) $pendingCount['total']),
                'link' => 'admin/manage_reservations.php',
            ];
        }

        $unavailableCommand = new SqlCommand(
            'SELECT r.name, rsr.description AS reason
             FROM resources r
             LEFT JOIN resource_status_reasons rsr ON rsr.resource_status_reason_id = r.resource_status_reason_id
             WHERE r.status_id = @unavailableStatus
             ORDER BY r.name'
        );
        $unavailableCommand->AddParameter(new Parameter('@unavailableStatus', ResourceStatus::UNAVAILABLE));
        $unavailableReader = $db->Query($unavailableCommand);
        while ($row = $unavailableReader->GetRow()) {
            $message = $row['name'];
            if (!empty($row['reason'])) {
                $message .= '（' . $row['reason'] . '）';
            }
            $items[] = ['level' => 'warning', 'message' => $message, 'link' => 'admin/manage_resources.php'];
        }
        $unavailableReader->Free();

        $missingEmailCommand = new SqlCommand(
            "SELECT COUNT(*) AS total FROM users WHERE status_id = @activeStatus AND (email IS NULL OR email = '')"
        );
        $missingEmailCommand->AddParameter(new Parameter('@activeStatus', AccountStatus::ACTIVE));
        $missingEmailReader = $db->Query($missingEmailCommand);
        $missingEmailCount = $missingEmailReader->GetRow();
        $missingEmailReader->Free();
        if (!empty($missingEmailCount) && (int) $missingEmailCount['total'] > 0) {
            $items[] = [
                'level' => 'warning',
                'message' => Resources::GetInstance()->GetString('AdminWarningMissingEmail', (int) $missingEmailCount['total']),
                'link' => 'admin/manage_users.php',
            ];
        }

        return $items;
    }
}
