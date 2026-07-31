<?php

require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');

/**
 * Participant search backing the Quick Create / Reservation Drawer "新增參與者" search box
 * (tpl/Overview/overview_fullcalendar.tpl, Web/scripts/overview_fullcalendar.js's
 * createParticipantPicker()). A dedicated CoAsia-specific endpoint rather than reusing the
 * shared ajax/autocomplete.php (Pages/Ajax/AutoCompletePage.php) — that one only matches
 * fname/lname/email/username, but this needs to also match the CoAsia-only "Chinese Name" /
 * "Korean Name" / "Employee ID" custom attributes (see database_schema/upgrades/4.2) and
 * return richer fields (Chinese name, department) for display, and doing that inside the
 * shared endpoint would risk changing behavior for every other caller of it (the classic
 * reservation form, admin pages, etc).
 *
 * Raw SQL (AdHocCommand) rather than the UserRepository/SqlFilter machinery, since matching
 * against pivoted custom-attribute values needs a GROUP BY/HAVING shape that doesn't fit the
 * existing filter builder, and this is a narrow, self-contained query.
 */
class OverviewFullCalendarParticipantSearchPage extends SecurePage
{
    public function __construct()
    {
        parent::__construct('', 1);
    }

    public function PageLoad()
    {
        $term = trim((string) $this->GetQuerystring('term'));

        $results = [];
        if ($term !== '') {
            $results = $this->Search($term);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($results);
        exit;
    }

    private function Search($term)
    {
        $userSession = ServiceLocator::GetServer()->GetUserSession();
        $statusFilter = ($userSession->IsAdmin || $userSession->IsGroupAdmin) ? AccountStatus::ALL : AccountStatus::ACTIVE;

        $sql = 'SELECT u.user_id, u.fname, u.lname, u.email, u.username, u.organization,
                MAX(CASE WHEN ca.display_label = \'Chinese Name\' THEN cav.attribute_value END) AS chinese_name,
                MAX(CASE WHEN ca.display_label = \'Korean Name\' THEN cav.attribute_value END) AS korean_name,
                MAX(CASE WHEN ca.display_label = \'Employee ID\' THEN cav.attribute_value END) AS employee_id
            FROM users u
            LEFT JOIN custom_attribute_values cav ON cav.entity_id = u.user_id AND cav.attribute_category = 2
            LEFT JOIN custom_attributes ca ON ca.custom_attribute_id = cav.custom_attribute_id
            WHERE (@status = 0 OR u.status_id = @status)
            GROUP BY u.user_id, u.fname, u.lname, u.email, u.username, u.organization
            HAVING (
                u.fname LIKE @term OR u.lname LIKE @term OR u.username LIKE @term OR u.email LIKE @term
                OR chinese_name LIKE @term OR korean_name LIKE @term OR employee_id LIKE @term
            )
            ORDER BY u.lname, u.fname
            LIMIT 20';

        $command = new AdHocCommand($sql);
        $command->AddParameter(new Parameter('@status', $statusFilter));
        $command->AddParameter(new Parameter('@term', '%' . $term . '%'));

        $reader = ServiceLocator::GetDatabase()->Query($command);
        $results = [];
        while ($row = $reader->GetRow()) {
            $fname = trim((string) ($row[ColumnNames::FIRST_NAME] ?? ''));
            $lname = trim((string) ($row[ColumnNames::LAST_NAME] ?? ''));
            $englishName = trim($fname . ' ' . $lname);

            // html_entity_decode(): the CoAsia employee import's source HR export had some
            // department names pre-escaped (e.g. "FA&amp;Risk Management" stored verbatim in
            // `organization`) -- decoded here at read time so this new search UI displays
            // "FA&Risk Management" correctly without touching the stored data or re-running
            // the import.
            $results[] = [
                'Id' => (int) $row[ColumnNames::USER_ID],
                'Username' => $row[ColumnNames::USERNAME],
                'Email' => $row[ColumnNames::EMAIL],
                'EnglishName' => $englishName !== '' ? $englishName : $row[ColumnNames::USERNAME],
                'ChineseName' => $row['chinese_name'] ?? '',
                'KoreanName' => $row['korean_name'] ?? '',
                'Organization' => html_entity_decode($row[ColumnNames::ORGANIZATION] ?? '', ENT_QUOTES),
            ];
        }
        $reader->Free();

        return $results;
    }
}
