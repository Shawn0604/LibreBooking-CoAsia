<?php

/**
 * CoAsia employee pre-provisioning import.
 *
 * Reads the HR/AD "사용자목록" (user list) .xls export and pre-provisions a LibreBooking
 * `users` row for every employee, so they can be found and invited as reservation
 * participants before they have ever logged in themselves. When an employee later logs in
 * for the first time via the ActiveDirectory plugin (plugins/Authentication/ActiveDirectory),
 * Registration::Synchronize() matches them by username/email and updates the row already
 * created here in place -- it never creates a second row for the same username/email
 * (Queries::CHECK_USER_EXISTENCE matches on username OR email).
 *
 * The source file has no Email or Office/location column (confirmed by inspecting the
 * actual export, not assumed from a generic template) -- see the derivation rules below.
 *
 * Usage:
 *   php Jobs/import-coasia-employees.php --file=<path to .xls> --dry-run
 *   php Jobs/import-coasia-employees.php --file=<path to .xls> --apply [--exclude=id1,id2,...]
 *
 * --dry-run only reads data (including read-only DB lookups to classify new-vs-existing)
 * and never writes. --apply performs the writes. Exactly one of the two must be given.
 * --exclude takes a comma-separated list of AD IDs (the "ID" column) to skip entirely --
 * useful for the service/system accounts the dry-run report flags for manual review.
 */

define('ROOT_DIR', __DIR__ . '/../');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Authentication/PasswordEncryption.php');
require_once(ROOT_DIR . 'Pages/Pages.php');
require_once(ROOT_DIR . 'vendor/autoload.php');
require_once(ROOT_DIR . 'Jobs/JobCop.php');

JobCop::EnsureCommandLine();

const EMAIL_DOMAIN = 'coasia.com';
const DEFAULT_TIMEZONE = 'Asia/Taipei';
const DEFAULT_LANGUAGE = 'zh_tw';

const ATTR_EMPLOYEE_ID = 'Employee ID';
const ATTR_CHINESE_NAME = 'Chinese Name';
const ATTR_KOREAN_NAME = 'Korean Name';
const ATTR_DEPT_PATHS = 'Department Paths';
const ATTR_AD_MANAGED = 'AD Managed Account';

const EXPECTED_HEADERS = [
    '號碼' => 'No',
    '名字' => 'Name',
    'ID' => 'Id',
    'Department path' => 'DeptPath',
    '所屬' => 'Affiliation',
    '職位' => 'Title',
    '人員編號' => 'EmployeeNo',
];

function main($argv)
{
    $options = ParseArgs($argv);

    if (empty($options['file'])) {
        fwrite(STDERR, "Missing required --file=<path to .xls>\n");
        exit(1);
    }
    if ($options['dry-run'] === $options['apply']) {
        fwrite(STDERR, "Specify exactly one of --dry-run or --apply\n");
        exit(1);
    }
    if (!file_exists($options['file'])) {
        fwrite(STDERR, "File not found: {$options['file']}\n");
        exit(1);
    }

    Log::Debug('Running import-coasia-employees.php (%s)', $options['apply'] ? 'apply' : 'dry-run');

    try {
        [$headerMap, $rows] = ReadSpreadsheet($options['file']);
        $people = ConsolidateRows($headerMap, $rows);
        $plan = BuildImportPlan($people, $options['exclude']);

        PrintReport($rows, $people, $plan);

        if ($options['apply']) {
            ApplyImportPlan($plan);
            PrintApplyResults($plan);
        } else {
            echo "\nDry run only -- no data was written. Re-run with --apply once you've reviewed this report.\n";
        }
    } catch (Throwable $ex) {
        Log::Error('Error running import-coasia-employees.php: %s', $ex);
        fwrite(STDERR, 'Error: ' . $ex->getMessage() . "\n");
        exit(1);
    }

    Log::Debug('Finished running import-coasia-employees.php');
}

function ParseArgs($argv)
{
    $options = ['file' => null, 'dry-run' => false, 'apply' => false, 'exclude' => []];
    foreach (array_slice($argv, 1) as $arg) {
        if (strpos($arg, '--file=') === 0) {
            $options['file'] = substr($arg, strlen('--file='));
        } elseif ($arg === '--dry-run') {
            $options['dry-run'] = true;
        } elseif ($arg === '--apply') {
            $options['apply'] = true;
        } elseif (strpos($arg, '--exclude=') === 0) {
            $list = substr($arg, strlen('--exclude='));
            $options['exclude'] = array_filter(array_map('trim', explode(',', $list)));
        }
    }
    return $options;
}

/**
 * @return array{0: array<string,int>, 1: array<int, array<string,string>>}
 */
function ReadSpreadsheet($path)
{
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
    $sheet = $spreadsheet->getActiveSheet();
    $data = $sheet->toArray(null, true, true, false);

    if (empty($data)) {
        throw new Exception('Spreadsheet is empty');
    }

    $headerRow = array_shift($data);
    $headerMap = []; // logical field name => column index
    foreach ($headerRow as $colIndex => $label) {
        $label = trim((string) $label);
        if (isset(EXPECTED_HEADERS[$label])) {
            $headerMap[EXPECTED_HEADERS[$label]] = $colIndex;
        }
    }
    // "Department path"'s neighbouring column (dept code) has no header text in the source
    // file at all -- it's not used by this importer, only Department path (full breadcrumb)
    // and Affiliation (leaf) are, so it's intentionally left unmapped.

    $required = ['Name', 'Id', 'DeptPath', 'Affiliation', 'Title', 'EmployeeNo'];
    $missing = array_diff($required, array_keys($headerMap));
    if (!empty($missing)) {
        throw new Exception(
            'Spreadsheet headers do not match the expected CoAsia export format. Missing: ' . implode(', ', $missing) .
            '. Found headers: ' . implode(' | ', array_map('strval', $headerRow))
        );
    }

    $rows = [];
    foreach ($data as $rowIndex => $row) {
        // toArray() pads short rows with nulls; skip fully-empty trailing rows.
        if (implode('', array_map('strval', $row)) === '') {
            continue;
        }
        $record = [];
        foreach ($headerMap as $field => $colIndex) {
            $record[$field] = trim((string) ($row[$colIndex] ?? ''));
        }
        $rows[] = $record;
    }

    return [$headerMap, $rows];
}

/**
 * The source export uses a "Dummy" department root as a catch-all bucket for system/shared
 * accounts (cn_scan, erpuser, hr, ...) mixed in with a handful of real people who simply
 * aren't assigned a department in AD yet. Per explicit instruction, no "Dummy" department is
 * created and every person whose *only* department membership(s) are under "Dummy" is
 * excluded from this run entirely (not created, not updated) rather than guessed at.
 */
function IsAllDummyDepartment($deptPaths)
{
    if (empty($deptPaths)) {
        return false;
    }
    foreach ($deptPaths as $path) {
        $root = trim(explode('>', $path)[0]);
        if (strcasecmp($root, 'Dummy') !== 0) {
            return false;
        }
    }
    return true;
}

function ContainsHangul($text)
{
    return preg_match('/[\x{AC00}-\x{D7A3}]/u', $text) === 1;
}

function ContainsHan($text)
{
    return preg_match('/[\x{4E00}-\x{9FFF}]/u', $text) === 1;
}

/**
 * Name column format is inconsistent in the source file: usually "NativeName(EnglishName)",
 * sometimes a plain English-only name with no parentheses, sometimes a plain native-script
 * name with no parentheses and no English form at all.
 *
 * @return array{fname: string, lname: string, chineseName: string, koreanName: string, englishNameMissing: bool}
 */
function ParseName($rawName, $fallbackId)
{
    $rawName = trim($rawName);
    $native = '';
    $english = '';

    if (preg_match('/^(.+?)\((.+)\)$/u', $rawName, $m)) {
        $native = trim($m[1]);
        $english = trim($m[2]);
    } elseif (ContainsHangul($rawName) || ContainsHan($rawName)) {
        $native = $rawName;
    } else {
        $english = $rawName;
    }

    $englishNameMissing = ($english === '');
    if ($englishNameMissing) {
        // Still need something usable for fname/lname/participant search -- fall back to the
        // native name, then to the AD id, rather than leaving the account nameless.
        $english = $native !== '' ? $native : $fallbackId;
    }

    $parts = preg_split('/\s+/', $english, 2);
    $fname = $parts[0];
    $lname = $parts[1] ?? '';

    $chineseName = '';
    $koreanName = '';
    if ($native !== '') {
        if (ContainsHangul($native)) {
            $koreanName = $native;
        } else {
            $chineseName = $native;
        }
    }

    return [
        'fname' => $fname,
        'lname' => $lname,
        'chineseName' => $chineseName,
        'koreanName' => $koreanName,
        'englishNameMissing' => $englishNameMissing,
    ];
}

/**
 * Groups the one-row-per-department-membership export into one record per distinct AD ID,
 * concatenating every distinct Department path the person appears under.
 */
function ConsolidateRows($headerMap, $rows)
{
    $people = []; // lowercase id => person record
    $missingId = [];
    $missingName = [];

    foreach ($rows as $row) {
        $id = $row['Id'];
        $name = $row['Name'];

        if ($id === '') {
            $missingId[] = $row;
            continue;
        }
        if ($name === '') {
            $missingName[] = $row;
            continue;
        }

        $key = strtolower($id);
        if (!isset($people[$key])) {
            $people[$key] = [
                'id' => $id,
                'name' => $name,
                'affiliation' => '',
                'title' => '',
                'employeeNo' => '',
                'deptPaths' => [],
                'rowCount' => 0,
            ];
        }

        $people[$key]['rowCount']++;
        if ($row['DeptPath'] !== '' && !in_array($row['DeptPath'], $people[$key]['deptPaths'], true)) {
            $people[$key]['deptPaths'][] = $row['DeptPath'];
        }
        if ($people[$key]['affiliation'] === '' && $row['Affiliation'] !== '') {
            $people[$key]['affiliation'] = $row['Affiliation'];
        }
        if ($people[$key]['title'] === '' && $row['Title'] !== '') {
            $people[$key]['title'] = $row['Title'];
        }
        if ($people[$key]['employeeNo'] === '' && $row['EmployeeNo'] !== '') {
            $people[$key]['employeeNo'] = $row['EmployeeNo'];
        }
    }

    return [
        'people' => $people,
        'missingId' => $missingId,
        'missingName' => $missingName,
    ];
}

/**
 * Builds the create/update/skip plan. Runs the same read-only DB lookups in both dry-run and
 * apply mode, so the dry-run report reflects reality (new-vs-existing counts, conflicts).
 */
function BuildImportPlan($consolidated, $excludeIds)
{
    $userRepository = new UserRepository();
    $attributeRepository = new AttributeRepository();
    $attrIdByLabel = [];
    foreach ($attributeRepository->GetByCategory(CustomAttributeCategory::USER) as $def) {
        $attrIdByLabel[$def->Label()] = $def->Id();
    }
    foreach ([ATTR_EMPLOYEE_ID, ATTR_CHINESE_NAME, ATTR_KOREAN_NAME, ATTR_DEPT_PATHS, ATTR_AD_MANAGED] as $label) {
        if (!isset($attrIdByLabel[$label])) {
            throw new Exception("Custom attribute '$label' not found -- run database_schema/upgrades/4.2 first");
        }
    }

    $excludeSet = array_flip(array_map('strtolower', $excludeIds));

    $toCreate = [];
    $toUpdate = [];
    $conflicts = [];
    $excluded = [];
    $excludedDummyDept = [];
    $likelyServiceAccounts = [];

    // Duplicate employee-number check across distinct people (post-consolidation).
    $employeeNoOwners = [];
    foreach ($consolidated['people'] as $key => $p) {
        if ($p['employeeNo'] !== '') {
            $employeeNoOwners[$p['employeeNo']][] = $p['id'];
        }
    }
    $duplicateEmployeeNos = array_filter($employeeNoOwners, fn($ids) => count($ids) > 1);

    foreach ($consolidated['people'] as $key => $p) {
        if (IsAllDummyDepartment($p['deptPaths'])) {
            $excludedDummyDept[] = $p['id'];
            continue;
        }

        if (strcasecmp($p['name'], $p['id']) === 0) {
            $likelyServiceAccounts[] = $p['id'];
        }

        if (isset($excludeSet[$key])) {
            $excluded[] = $p['id'];
            continue;
        }

        $username = $p['id'];
        $email = strtolower($p['id']) . '@' . EMAIL_DOMAIN;
        $nameParts = ParseName($p['name'], $p['id']);

        $entry = [
            'key' => $key,
            'username' => $username,
            'email' => $email,
            'fname' => $nameParts['fname'],
            'lname' => $nameParts['lname'],
            'chineseName' => $nameParts['chineseName'],
            'koreanName' => $nameParts['koreanName'],
            'englishNameMissing' => $nameParts['englishNameMissing'],
            'affiliation' => $p['affiliation'],
            'title' => $p['title'],
            'employeeNo' => $p['employeeNo'],
            'deptPaths' => $p['deptPaths'],
            'rowCount' => $p['rowCount'],
            'attrIdByLabel' => $attrIdByLabel,
        ];

        $existingId = $userRepository->UserExists($email, $username);
        if ($existingId) {
            $existingUser = $userRepository->LoadById($existingId);
            $usernameMatches = strcasecmp($existingUser->Username(), $username) === 0;
            $emailMatches = strcasecmp($existingUser->EmailAddress(), $email) === 0;

            if (!$usernameMatches || !$emailMatches) {
                $entry['existingUserId'] = $existingId;
                $entry['existingUsername'] = $existingUser->Username();
                $entry['existingEmail'] = $existingUser->EmailAddress();
                $entry['reason'] = !$usernameMatches ? 'username mismatch' : 'email mismatch';
                $conflicts[] = $entry;
                continue;
            }

            $entry['existingUser'] = $existingUser;
            $toUpdate[] = $entry;
        } else {
            $toCreate[] = $entry;
        }
    }

    return [
        'toCreate' => $toCreate,
        'toUpdate' => $toUpdate,
        'conflicts' => $conflicts,
        'excluded' => $excluded,
        'excludedDummyDept' => $excludedDummyDept,
        'likelyServiceAccounts' => $likelyServiceAccounts,
        'duplicateEmployeeNos' => $duplicateEmployeeNos,
        'missingId' => $consolidated['missingId'],
        'missingName' => $consolidated['missingName'],
        'results' => ['created' => 0, 'updated' => 0, 'errors' => []],
    ];
}

function BuildCustomAttributeValues($attrIdByLabel, $entry)
{
    $deptPathsJoined = implode("\n", $entry['deptPaths']);
    return [
        new AttributeValue($attrIdByLabel[ATTR_EMPLOYEE_ID], $entry['employeeNo']),
        new AttributeValue($attrIdByLabel[ATTR_CHINESE_NAME], $entry['chineseName']),
        new AttributeValue($attrIdByLabel[ATTR_KOREAN_NAME], $entry['koreanName']),
        new AttributeValue($attrIdByLabel[ATTR_DEPT_PATHS], $deptPathsJoined),
        new AttributeValue($attrIdByLabel[ATTR_AD_MANAGED], '1'),
    ];
}

function ApplyImportPlan(&$plan)
{
    $userRepository = new UserRepository();
    $passwordEncryption = new PasswordEncryption();

    foreach ($plan['toCreate'] as $entry) {
        try {
            $randomPassword = bin2hex(random_bytes(32));
            $encrypted = $passwordEncryption->EncryptPassword($randomPassword);

            $user = User::Create(
                $entry['fname'],
                $entry['lname'],
                $entry['email'],
                $entry['username'],
                DEFAULT_LANGUAGE,
                DEFAULT_TIMEZONE,
                $encrypted->EncryptedPassword(),
                $encrypted->Salt()
            );
            $user->ChangeAttributes(null, $entry['affiliation'], $entry['title']);
            $user->ChangeCustomAttributes(BuildCustomAttributeValues($entry['attrIdByLabel'], $entry));

            $userRepository->Add($user);
            $plan['results']['created']++;
        } catch (Throwable $ex) {
            Log::Error('Failed to create user %s: %s', $entry['username'], $ex);
            $plan['results']['errors'][] = ['username' => $entry['username'], 'action' => 'create', 'message' => $ex->getMessage()];
        }
    }

    foreach ($plan['toUpdate'] as $entry) {
        try {
            /** @var User $user */
            $user = $entry['existingUser'];
            $user->ChangeName($entry['fname'], $entry['lname']);
            $existingPhone = $user->GetAttribute(UserAttribute::Phone);
            $user->ChangeAttributes($existingPhone, $entry['affiliation'], $entry['title']);
            $user->ChangeCustomAttributes(BuildCustomAttributeValues($entry['attrIdByLabel'], $entry));

            $userRepository->Update($user);
            $plan['results']['updated']++;
        } catch (Throwable $ex) {
            Log::Error('Failed to update user %s: %s', $entry['username'], $ex);
            $plan['results']['errors'][] = ['username' => $entry['username'], 'action' => 'update', 'message' => $ex->getMessage()];
        }
    }
}

function PrintReport($rows, $consolidated, $plan)
{
    $totalRows = count($rows);
    $distinctPeople = count($consolidated['people']);
    $duplicateRowsCollapsed = 0;
    foreach ($consolidated['people'] as $p) {
        $duplicateRowsCollapsed += max(0, $p['rowCount'] - 1);
    }

    echo "=== CoAsia Employee Import -- Dry Run Report ===\n\n";
    printf("Excel 總筆數 (total rows):                 %d\n", $totalRows);
    printf("去重後人數 (distinct people by ID):          %d  (%d duplicate department-membership rows collapsed)\n", $distinctPeople, $duplicateRowsCollapsed);
    printf("可新增數 (new accounts to create):           %d\n", count($plan['toCreate']));
    printf("已存在／更新數 (existing accounts to update): %d\n", count($plan['toUpdate']));
    printf("排除數 (--exclude'd):                        %d\n", count($plan['excluded']));
    printf("排除數 (Dummy 部門，不建立):                  %d\n", count($plan['excludedDummyDept']));
    printf("缺少必要欄位 - 缺少 ID:                       %d\n", count($plan['missingId']));
    printf("缺少必要欄位 - 缺少姓名:                      %d\n", count($plan['missingName']));
    printf("與現有帳號衝突 (conflicts, skipped):          %d\n", count($plan['conflicts']));
    printf("重複員工編號 (duplicate employee numbers):    %d\n", count($plan['duplicateEmployeeNos']));

    if (!empty($plan['conflicts'])) {
        echo "\n--- 衝突明細 (matched an existing account by email OR username, but not both -- skipped, needs manual review) ---\n";
        foreach ($plan['conflicts'] as $c) {
            printf(
                "  Excel ID=%-16s computed username=%-16s computed email=%-28s | existing user_id=%d username=%s email=%s (%s)\n",
                $c['username'],
                $c['username'],
                $c['email'],
                $c['existingUserId'],
                $c['existingUsername'],
                $c['existingEmail'],
                $c['reason']
            );
        }
    }

    if (!empty($plan['excludedDummyDept'])) {
        echo "\n--- Dummy 部門帳號 (已排除，不建立/不更新) ---\n";
        foreach ($plan['excludedDummyDept'] as $id) {
            echo "  $id\n";
        }
    }

    if (!empty($plan['duplicateEmployeeNos'])) {
        echo "\n--- 重複員工編號明細 ---\n";
        foreach ($plan['duplicateEmployeeNos'] as $employeeNo => $ids) {
            printf("  %s -> %s\n", $employeeNo, implode(', ', $ids));
        }
    }

    if (!empty($plan['likelyServiceAccounts'])) {
        echo "\n--- 疑似系統/服務帳號 (Name 欄位與 ID 欄位相同，仍會匯入；如需排除請用 --exclude=" . implode(',', $plan['likelyServiceAccounts']) . ") ---\n";
        foreach ($plan['likelyServiceAccounts'] as $id) {
            echo "  $id\n";
        }
    }

    $missingEnglish = array_filter(array_merge($plan['toCreate'], $plan['toUpdate']), fn($e) => $e['englishNameMissing']);
    if (!empty($missingEnglish)) {
        echo "\n--- 缺少英文姓名，改用原始姓名/ID 作為顯示名稱 (" . count($missingEnglish) . " 筆) ---\n";
        foreach ($missingEnglish as $e) {
            printf("  %s -> fname=%s lname=%s\n", $e['username'], $e['fname'], $e['lname']);
        }
    }

    if (!empty($plan['missingId'])) {
        echo "\n--- 缺少 ID 的原始資料列 (無法匯入) ---\n";
        foreach ($plan['missingId'] as $row) {
            printf("  Name=%s DeptPath=%s\n", $row['Name'], $row['DeptPath']);
        }
    }

    if (!empty($plan['missingName'])) {
        echo "\n--- 缺少姓名的原始資料列 (無法匯入) ---\n";
        foreach ($plan['missingName'] as $row) {
            printf("  Id=%s DeptPath=%s\n", $row['Id'], $row['DeptPath']);
        }
    }
}

function PrintApplyResults($plan)
{
    echo "\n=== Apply Results ===\n";
    printf("新增 (created):  %d\n", $plan['results']['created']);
    printf("更新 (updated):  %d\n", $plan['results']['updated']);
    printf("錯誤 (errors):   %d\n", count($plan['results']['errors']));
    foreach ($plan['results']['errors'] as $err) {
        printf("  [%s] %s: %s\n", $err['action'], $err['username'], $err['message']);
    }
}

main($argv);
