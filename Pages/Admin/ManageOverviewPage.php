<?php

require_once(ROOT_DIR . 'Pages/Admin/AdminPage.php');
require_once(ROOT_DIR . 'Domain/Values/ResourceLevel.php');
require_once(ROOT_DIR . 'Domain/Values/ResourceStatus.php');
require_once(ROOT_DIR . 'Domain/Values/ReservationStatus.php');

/**
 * "管理總覽" — the landing page for 管理中心 (see tpl/globalheader.tpl's #navAdminCenterDropdown).
 * Read-only summary of today's reservations, resource status, recently added users, and a
 * handful of system warnings -- deliberately simple SQL (no new repository abstraction) since
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

        $this->Set('TodaysReservations', $this->GetTodaysReservations($db));
        $this->Set('ResourceStatusCounts', $this->GetResourceStatusCounts($db));
        $this->Set('UnavailableResources', $this->GetUnavailableResources($db));
        $this->Set('RecentUsers', $this->GetRecentUsers($db));
        $this->Set('Warnings', $this->GetWarnings($db));

        $this->Display('Overview/manage_overview.tpl');
    }

    private function GetTodaysReservations($db)
    {
        $command = new SqlCommand(
            'SELECT ri.reference_number, ri.start_date, ri.end_date, rs.title, rs.status_id,
                    r.name AS resource_name, u.fname, u.lname
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

    private function GetResourceStatusCounts($db)
    {
        $reader = $db->Query(new SqlCommand('SELECT status_id, COUNT(*) AS total FROM resources GROUP BY status_id'));
        $counts = [
            'available' => 0,
            'unavailable' => 0,
            'hidden' => 0,
        ];
        while ($row = $reader->GetRow()) {
            switch ((int) $row['status_id']) {
                case ResourceStatus::AVAILABLE:
                    $counts['available'] = (int) $row['total'];
                    break;
                case ResourceStatus::UNAVAILABLE:
                    $counts['unavailable'] = (int) $row['total'];
                    break;
                case ResourceStatus::HIDDEN:
                    $counts['hidden'] = (int) $row['total'];
                    break;
            }
        }
        $reader->Free();

        return $counts;
    }

    private function GetUnavailableResources($db)
    {
        $command = new SqlCommand(
            'SELECT r.name, rsr.description AS reason
             FROM resources r
             LEFT JOIN resource_status_reasons rsr ON rsr.resource_status_reason_id = r.resource_status_reason_id
             WHERE r.status_id = @unavailableStatus
             ORDER BY r.name'
        );
        $command->AddParameter(new Parameter('@unavailableStatus', ResourceStatus::UNAVAILABLE));

        $reader = $db->Query($command);
        $resources = [];
        while ($row = $reader->GetRow()) {
            $resources[] = $row;
        }
        $reader->Free();

        return $resources;
    }

    private function GetRecentUsers($db)
    {
        $reader = $db->Query(new SqlCommand(
            'SELECT fname, lname, email, username, date_created
             FROM users
             ORDER BY date_created DESC
             LIMIT 8'
        ));
        $users = [];
        while ($row = $reader->GetRow()) {
            $users[] = $row;
        }
        $reader->Free();

        return $users;
    }

    /**
     * @return list<array{level: string, message: string}>
     */
    private function GetWarnings($db)
    {
        $warnings = [];

        if (!Configuration::Instance()->GetKey(ConfigKeys::EMAIL_ENABLED, new BooleanConverter())) {
            $warnings[] = ['level' => 'danger', 'message' => Resources::GetInstance()->GetString('AdminWarningEmailDisabled')];
        }

        $lineEnabled = Configuration::Instance()->GetKey(ConfigKeys::LINE_ENABLED, new BooleanConverter());
        if ($lineEnabled && empty(Configuration::Instance()->GetKey(ConfigKeys::LINE_CHANNEL_ACCESS_TOKEN))) {
            $warnings[] = ['level' => 'warning', 'message' => Resources::GetInstance()->GetString('AdminWarningLineMisconfigured')];
        }

        $pendingCommand = new SqlCommand('SELECT COUNT(*) AS total FROM reservation_series WHERE status_id = @pendingStatus');
        $pendingCommand->AddParameter(new Parameter('@pendingStatus', ReservationStatus::Pending));
        $pendingReader = $db->Query($pendingCommand);
        $pendingCount = $pendingReader->GetRow();
        $pendingReader->Free();
        if (!empty($pendingCount) && (int) $pendingCount['total'] > 0) {
            $warnings[] = [
                'level' => 'info',
                'message' => Resources::GetInstance()->GetString('AdminWarningPendingApproval', (int) $pendingCount['total']),
            ];
        }

        return $warnings;
    }
}
