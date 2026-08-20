<?php

/**
 * Periodic reconciliation between AD account state and LibreBooking account state.
 *
 * ActiveDirectory is a bind-based integration (plugins/Authentication/ActiveDirectory) -- AD never
 * pushes an event to LibreBooking when an account is locked, disabled, or deleted. This job is the
 * only way "AD says this employee is gone" ever becomes "LibreBooking deactivates the account"
 * without a human noticing and doing it manually. Scoped to accounts flagged is_ad_managed=1 (set
 * by ActiveDirectory::Login() on every successful AD sync) and NOT is_local_auth_only=1 (the
 * emergency-admin exemption, which must never be auto-deactivated by this job).
 *
 * Cron Example (not wired up anywhere in this repo's deployment config -- see COASIA-DEPLOYMENT.md
 * for how the other Jobs/*.php scripts here get scheduled on the VM). Runs on the quarter-hour
 * (0, 15, 30, 45) rather than every.15.minutes step syntax, only to avoid a "*" + "/" sequence
 * that would otherwise prematurely close this very comment block:
 *   0,15,30,45 * * * * /usr/bin/env php -f ${WWW_DIR}/librebooking/Jobs/ad-reconcile-account-status.php -- --apply
 *
 * Usage:
 *   php Jobs/ad-reconcile-account-status.php --dry-run
 *   php Jobs/ad-reconcile-account-status.php --apply
 *
 * --dry-run only checks AD and reports which accounts WOULD be deactivated -- writes nothing.
 * --apply actually deactivates. Exactly one of the two must be given; there is no default, on
 * purpose -- a bug in the AD status check must never silently mass-deactivate real employee
 * accounts because nobody remembered to pass a flag. Run --dry-run first and review the report
 * before ever running --apply against production.
 */

define('ROOT_DIR', __DIR__ . '/../');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Authentication/namespace.php');
require_once(ROOT_DIR . 'plugins/Authentication/ActiveDirectory/namespace.php');
require_once(ROOT_DIR . 'Jobs/JobCop.php');

JobCop::EnsureCommandLine();

function main($argv)
{
    $options = ParseArgs($argv);
    if ($options['dry-run'] === $options['apply']) {
        fwrite(STDERR, "Specify exactly one of --dry-run or --apply\n");
        exit(1);
    }

    Log::Debug('Running ad-reconcile-account-status.php (%s)', $options['apply'] ? 'apply' : 'dry-run');

    try {
        $userRepository = new UserRepository();
        $ldap = new AdLdapWrapper(new ActiveDirectoryOptions());

        if (!$ldap->Connect()) {
            fwrite(STDERR, "Could not connect to ActiveDirectory LDAP server -- aborting without checking any accounts.\n");
            exit(1);
        }

        $users = FindAdManagedActiveUsers($userRepository);
        echo 'Checking ' . count($users) . " AD-managed active account(s) against AD...\n";

        [$toDeactivate, $errors] = CheckAccountStatuses($ldap, $users);

        PrintReport($toDeactivate, $errors);

        if ($options['apply']) {
            ApplyDeactivations($userRepository, $toDeactivate);
        } else {
            echo "\nDry run only -- no accounts were deactivated. Re-run with --apply once you've reviewed this report.\n";
        }
    } catch (Throwable $ex) {
        Log::Error('Error running ad-reconcile-account-status.php: %s', $ex);
        fwrite(STDERR, 'Error: ' . $ex->getMessage() . "\n");
        exit(1);
    }

    Log::Debug('Finished running ad-reconcile-account-status.php');
}

function ParseArgs($argv)
{
    $options = ['dry-run' => false, 'apply' => false];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--dry-run') {
            $options['dry-run'] = true;
        } elseif ($arg === '--apply') {
            $options['apply'] = true;
        }
    }
    return $options;
}

/**
 * @param IUserRepository $userRepository
 * @return UserItemView[]
 */
function FindAdManagedActiveUsers($userRepository)
{
    $filter = new SqlFilterFreeForm(sprintf(
        '`%s` = 1 AND `%s` = 0',
        ColumnNames::IS_AD_MANAGED,
        ColumnNames::IS_LOCAL_AUTH_ONLY
    ));
    // null pageNumber/pageSize -- no pagination, this job needs the full set every run.
    $results = $userRepository->GetList(null, null, null, null, $filter, AccountStatus::ACTIVE);
    return $results->Results();
}

/**
 * Each user gets its own try/catch (unlike Jobs/sendreminders.php/autorelease.php's single
 * outer try/catch for the whole run) -- one account's LDAP lookup failing (e.g. a stale/renamed
 * username) must not abort checking the rest, following the same per-item error isolation
 * Jobs/import-coasia-employees.php::ApplyImportPlan() already uses.
 *
 * @param IActiveDirectory $ldap
 * @param UserItemView[] $users
 * @return array{0: array, 1: array} [$toDeactivate, $errors]
 */
function CheckAccountStatuses($ldap, $users)
{
    $toDeactivate = [];
    $errors = [];
    foreach ($users as $user) {
        try {
            $status = $ldap->GetAccountStatus($user->Username);
            if ($status !== AdAccountStatus::ACTIVE) {
                $toDeactivate[] = ['user' => $user, 'status' => $status];
            }
        } catch (Throwable $ex) {
            Log::Error('ad-reconcile-account-status: failed to check %s: %s', $user->Username, $ex);
            $errors[] = ['username' => $user->Username, 'message' => $ex->getMessage()];
        }
    }
    return [$toDeactivate, $errors];
}

function PrintReport($toDeactivate, $errors)
{
    if (empty($toDeactivate)) {
        echo "No accounts need to be deactivated.\n";
    } else {
        echo "Accounts to deactivate:\n";
        foreach ($toDeactivate as $entry) {
            echo "  - {$entry['user']->Username} (id={$entry['user']->Id}, AD status={$entry['status']})\n";
        }
    }

    if (!empty($errors)) {
        echo "\nSkipped (LDAP lookup failed, left untouched):\n";
        foreach ($errors as $error) {
            echo "  - {$error['username']}: {$error['message']}\n";
        }
    }
}

/**
 * @param IUserRepository $userRepository
 * @param array $toDeactivate
 */
function ApplyDeactivations($userRepository, $toDeactivate)
{
    $deactivated = 0;
    foreach ($toDeactivate as $entry) {
        try {
            // Reload fresh rather than reusing the UserItemView -- Deactivate() needs a full
            // User domain object, and this also naturally re-checks StatusId() at write time in
            // case an admin already deactivated this same account manually since the scan above.
            $user = $userRepository->LoadById($entry['user']->Id);
            if ($user->StatusId() != AccountStatus::ACTIVE) {
                continue;
            }
            $user->Deactivate();
            $userRepository->Update($user);
            $deactivated++;
        } catch (Throwable $ex) {
            Log::Error('ad-reconcile-account-status: failed to deactivate %s: %s', $entry['user']->Username, $ex);
        }
    }
    echo "\nDeactivated {$deactivated} account(s).\n";
}

main($argv);
