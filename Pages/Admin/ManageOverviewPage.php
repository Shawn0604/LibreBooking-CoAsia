<?php

require_once(ROOT_DIR . 'Pages/Admin/AdminPage.php');
require_once(ROOT_DIR . 'Domain/Values/ResourceLevel.php');
require_once(ROOT_DIR . 'Domain/Values/ResourceStatus.php');
require_once(ROOT_DIR . 'Domain/Values/ReservationStatus.php');
require_once(ROOT_DIR . 'Domain/Values/AccountStatus.php');

/**
 * "管理總覽" — the landing page for 管理中心 (see tpl/Admin/admin-nav.tpl). Read-only summary of
 * today's reservations, resource status grouped by each resource's `location`, and a
 * consolidated "需要處理" list -- deliberately simple SQL (no new repository abstraction) since
 * this page only ever reads, never writes, and each query is a one-off shape not reused
 * elsewhere.
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
        $roomStatusByLocation = $this->GetRoomStatusByLocation($db, $this->GetInUseResourceIds($db));

        $availableCount = 0;
        $inUseCount = 0;
        foreach ($roomStatusByLocation as $location) {
            $availableCount += $location['available'];
            $inUseCount += $location['inUse'];
        }

        $this->Set('TodaysReservations', $todaysReservations);
        $this->Set('TodaysReservationsCount', count($todaysReservations));
        $this->Set('InUseCount', $inUseCount);
        $this->Set('AvailableCount', $availableCount);
        // No notification send-log table exists yet (LINE/email sends only hit the app log) --
        // null here means "no data source", rendered by the template as 尚未設定 rather than a
        // fabricated 0 that would misleadingly read as "confirmed zero failures".
        $this->Set('NotificationFailureCount', null);
        $this->Set('RoomStatusByLocation', $roomStatusByLocation);
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
     * @return list<int> resource_ids with a reservation covering the current moment
     */
    private function GetInUseResourceIds($db)
    {
        $command = new SqlCommand(
            'SELECT DISTINCT rr.resource_id
             FROM reservation_instances ri
             INNER JOIN reservation_series rs ON rs.series_id = ri.series_id
             INNER JOIN reservation_resources rr ON rr.series_id = rs.series_id AND rr.resource_level_id = @primaryLevel
             WHERE NOW() BETWEEN ri.start_date AND ri.end_date AND rs.status_id != @deletedStatus'
        );
        $command->AddParameter(new Parameter('@primaryLevel', ResourceLevel::Primary));
        $command->AddParameter(new Parameter('@deletedStatus', ReservationStatus::Deleted));

        $reader = $db->Query($command);
        $ids = [];
        while ($row = $reader->GetRow()) {
            $ids[] = (int) $row['resource_id'];
        }
        $reader->Free();

        return $ids;
    }

    /**
     * Groups resources by their free-text `location` column (there is no separate region/area
     * table -- each resource already carries its own city/building as plain text). A resource
     * with no location set is bucketed under an "unassigned" group rather than dropped.
     *
     * @param list<int> $inUseResourceIds
     * @return list<array{location: string, available: int, inUse: int, unavailable: int, hidden: int, total: int}>
     */
    private function GetRoomStatusByLocation($db, $inUseResourceIds)
    {
        $reader = $db->Query(new SqlCommand('SELECT resource_id, location, status_id FROM resources ORDER BY location, name'));

        $inUseLookup = array_flip($inUseResourceIds);
        $groups = [];
        while ($row = $reader->GetRow()) {
            $location = trim((string) $row['location']);
            if ($location === '') {
                $location = '__unassigned__';
            }
            if (!isset($groups[$location])) {
                $groups[$location] = ['location' => $location, 'available' => 0, 'inUse' => 0, 'unavailable' => 0, 'hidden' => 0, 'total' => 0];
            }

            $groups[$location]['total']++;
            $statusId = (int) $row['status_id'];
            $resourceId = (int) $row['resource_id'];

            if ($statusId === ResourceStatus::UNAVAILABLE) {
                $groups[$location]['unavailable']++;
            } elseif ($statusId === ResourceStatus::HIDDEN) {
                $groups[$location]['hidden']++;
            } elseif (isset($inUseLookup[$resourceId])) {
                $groups[$location]['inUse']++;
            } else {
                $groups[$location]['available']++;
            }
        }
        $reader->Free();

        return array_values($groups);
    }

    /**
     * @return list<array{level: string, message: string}>
     */
    private function GetNeedsAttention($db)
    {
        $items = [];

        if (!Configuration::Instance()->GetKey(ConfigKeys::EMAIL_ENABLED, new BooleanConverter())) {
            $items[] = ['level' => 'danger', 'message' => Resources::GetInstance()->GetString('AdminWarningEmailDisabled')];
        }

        $lineEnabled = Configuration::Instance()->GetKey(ConfigKeys::LINE_ENABLED, new BooleanConverter());
        if ($lineEnabled && empty(Configuration::Instance()->GetKey(ConfigKeys::LINE_CHANNEL_ACCESS_TOKEN))) {
            $items[] = ['level' => 'warning', 'message' => Resources::GetInstance()->GetString('AdminWarningLineMisconfigured')];
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
            $items[] = ['level' => 'warning', 'message' => $message];
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
            ];
        }

        return $items;
    }
}
