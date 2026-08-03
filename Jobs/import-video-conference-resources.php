<?php

/**
 * One-time import of the 5 existing CoAsia video conference resources' connection info
 * (join URL, meeting ID, meeting passcode) and shared host login credentials from a local INI
 * file into the app's own storage -- the video_url/video_meeting_id/video_passcode custom
 * attributes (plain, same as any other resource attribute) and the encrypted
 * `resource_video_conference_host` table (VideoConferenceHostRepository) respectively.
 *
 * Security: the INI file's host_account/host_password/meeting_password values are never
 * printed, logged, or written anywhere except straight into the encrypted host-credential
 * column via VideoConferenceCredentialEncryption. The dry-run/apply report below only ever
 * shows resource names and a masked "(set)"/"(empty)" indicator for sensitive fields --
 * never the values themselves.
 *
 * Usage:
 *   php Jobs/import-video-conference-resources.php --file=<path to .ini> --dry-run
 *   php Jobs/import-video-conference-resources.php --file=<path to .ini> --apply
 *
 * INI format (one section per resource, matched to an existing `resources` row by exact
 * `resource_name` match -- this script never creates a resource, only updates one that
 * already exists):
 *   [some_key]
 *   resource_name="Zoom TW"
 *   meeting_url="https://..."
 *   meeting_id="..."
 *   meeting_password=""
 *   host_account="..."
 *   host_password="..."
 */

define('ROOT_DIR', __DIR__ . '/../');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'Pages/Pages.php');
require_once(ROOT_DIR . 'Jobs/JobCop.php');

JobCop::EnsureCommandLine();

const ATTR_VIDEO_URL = 'video_url';
const ATTR_VIDEO_MEETING_ID = 'video_meeting_id';
const ATTR_VIDEO_PASSCODE = 'video_passcode';

function main($argv)
{
    $options = ParseArgs($argv);

    if (empty($options['file'])) {
        fwrite(STDERR, "Missing required --file=<path to .ini>\n");
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

    Log::Debug('Running import-video-conference-resources.php (%s)', $options['apply'] ? 'apply' : 'dry-run');

    try {
        $sections = parse_ini_file($options['file'], true, INI_SCANNER_RAW);
        if ($sections === false) {
            throw new Exception('Failed to parse INI file');
        }

        $plan = BuildImportPlan($sections);
        PrintReport($plan);

        if ($options['apply']) {
            ApplyImportPlan($plan);
            PrintApplyResults($plan);
        } else {
            echo "\nDry run only -- no data was written. Re-run with --apply once you've reviewed this report.\n";
        }
    } catch (Throwable $ex) {
        Log::Error('Error running import-video-conference-resources.php: %s', $ex);
        fwrite(STDERR, 'Error: ' . $ex->getMessage() . "\n");
        exit(1);
    }

    Log::Debug('Finished running import-video-conference-resources.php');
}

function ParseArgs($argv)
{
    $options = ['file' => null, 'dry-run' => false, 'apply' => false];
    foreach (array_slice($argv, 1) as $arg) {
        if (strpos($arg, '--file=') === 0) {
            $options['file'] = substr($arg, strlen('--file='));
        } elseif ($arg === '--dry-run') {
            $options['dry-run'] = true;
        } elseif ($arg === '--apply') {
            $options['apply'] = true;
        }
    }
    return $options;
}

function BuildImportPlan($sections)
{
    $resourceRepository = new ResourceRepository();
    $attributeRepository = new AttributeRepository();

    $attrIdByLabel = [];
    foreach ($attributeRepository->GetByCategory(CustomAttributeCategory::RESOURCE) as $def) {
        $attrIdByLabel[$def->Label()] = $def->Id();
    }
    foreach ([ATTR_VIDEO_URL, ATTR_VIDEO_MEETING_ID, ATTR_VIDEO_PASSCODE] as $label) {
        if (!isset($attrIdByLabel[$label])) {
            throw new Exception("Custom attribute '$label' not found -- run database_schema/upgrades/4.3 first");
        }
    }

    $matched = [];
    $notFound = [];

    foreach ($sections as $key => $entry) {
        $resourceName = trim($entry['resource_name'] ?? '');
        if ($resourceName === '') {
            $notFound[] = ['key' => $key, 'reason' => 'missing resource_name in INI section'];
            continue;
        }

        $resource = $resourceRepository->LoadByName($resourceName);
        if (empty($resource)) {
            $notFound[] = ['key' => $key, 'resourceName' => $resourceName, 'reason' => 'no resource with this exact name exists'];
            continue;
        }

        $matched[] = [
            'key' => $key,
            'resourceId' => $resource->GetId(),
            'resourceName' => $resourceName,
            'meetingUrl' => trim($entry['meeting_url'] ?? ''),
            'meetingId' => trim($entry['meeting_id'] ?? ''),
            'meetingPassword' => (string) ($entry['meeting_password'] ?? ''),
            'hostAccount' => trim($entry['host_account'] ?? ''),
            'hostPassword' => (string) ($entry['host_password'] ?? ''),
        ];
    }

    return [
        'attrIdByLabel' => $attrIdByLabel,
        'matched' => $matched,
        'notFound' => $notFound,
        'results' => ['updated' => 0, 'errors' => []],
    ];
}

function ApplyImportPlan(&$plan)
{
    $resourceRepository = new ResourceRepository();
    $hostRepository = new VideoConferenceHostRepository();
    $attrIdByLabel = $plan['attrIdByLabel'];

    foreach ($plan['matched'] as $entry) {
        try {
            $resource = $resourceRepository->LoadById($entry['resourceId']);
            $resource->ChangeAttributes([
                new AttributeValue($attrIdByLabel[ATTR_VIDEO_URL], $entry['meetingUrl']),
                new AttributeValue($attrIdByLabel[ATTR_VIDEO_MEETING_ID], $entry['meetingId']),
                new AttributeValue($attrIdByLabel[ATTR_VIDEO_PASSCODE], $entry['meetingPassword']),
            ]);
            $resourceRepository->Update($resource);

            if (!empty($entry['hostAccount']) || !empty($entry['hostPassword'])) {
                $hostRepository->Save($entry['resourceId'], $entry['hostAccount'], $entry['hostPassword']);
            }

            $plan['results']['updated']++;
        } catch (Throwable $ex) {
            Log::Error('Failed to import video conference resource %s: %s', $entry['resourceName'], $ex);
            $plan['results']['errors'][] = ['resourceName' => $entry['resourceName'], 'message' => $ex->getMessage()];
        }
    }
}

function Masked($value)
{
    return $value !== '' ? '(set)' : '(empty)';
}

function PrintReport($plan)
{
    echo "=== CoAsia Video Conference Resource Import -- Dry Run Report ===\n\n";
    printf("符合的資源 (matched resources):  %d\n", count($plan['matched']));
    printf("找不到對應資源 (not found):       %d\n", count($plan['notFound']));

    if (!empty($plan['matched'])) {
        echo "\n--- 將更新的資源 (values are masked -- never printed in full) ---\n";
        foreach ($plan['matched'] as $entry) {
            printf(
                "  [%s] resource_id=%d meeting_url=%s meeting_id=%s meeting_password=%s host_account=%s host_password=%s\n",
                $entry['resourceName'],
                $entry['resourceId'],
                Masked($entry['meetingUrl']),
                Masked($entry['meetingId']),
                Masked($entry['meetingPassword']),
                Masked($entry['hostAccount']),
                Masked($entry['hostPassword'])
            );
        }
    }

    if (!empty($plan['notFound'])) {
        echo "\n--- 找不到對應資源明細 ---\n";
        foreach ($plan['notFound'] as $entry) {
            printf("  [%s] %s (%s)\n", $entry['key'], $entry['resourceName'] ?? '?', $entry['reason']);
        }
    }
}

function PrintApplyResults($plan)
{
    echo "\n=== Apply Results ===\n";
    printf("已更新 (updated):  %d\n", $plan['results']['updated']);
    printf("錯誤 (errors):     %d\n", count($plan['results']['errors']));
    foreach ($plan['results']['errors'] as $err) {
        printf("  %s: %s\n", $err['resourceName'], $err['message']);
    }
}

main($argv);
