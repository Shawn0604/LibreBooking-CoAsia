<?php

/**
 * Lists every non-empty LB_* line in the effective .env (prod: bind-mounted from `app.env`)
 * next to what `config.php` would otherwise provide, and the value the application actually
 * ends up using. Its job is to make "I changed config.php but nothing happened" a five-second
 * check instead of a multi-hour investigation.
 *
 * Why this exists: `Configuration::GetKey()` consults the `LB_<KEY>` environment variable
 * FIRST and, if it holds any non-empty string, uses it and ignores `config.php` entirely
 * (`lib/Config/Configuration.php` -> `ConfigKeysMeta::envKeyForConfig()`). A prod `app.env`
 * copied wholesale from `.env.example` therefore silently shadows almost every setting.
 *
 * Run it inside the `app` container:
 *   php scripts/audit-app-env.php                # every non-empty LB_* override
 *   php scripts/audit-app-env.php reservation    # filter by key/section substring
 *   php scripts/audit-app-env.php --shadowing    # only rows where app.env != config.php
 *
 * or from the host via the wrapper: scripts/audit-app-env.sh [filter]
 *
 * Exit code is non-zero when at least one SHADOWING row is found, so it can gate a deploy.
 */

define('ROOT_DIR', dirname(__DIR__) . '/');
require_once(ROOT_DIR . 'Pages/Page.php');

$filter = null;
$onlyShadowing = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--shadowing') {
        $onlyShadowing = true;
    } elseif ($arg !== '') {
        $filter = strtolower($arg);
    }
}

$rawConfig = require ROOT_DIR . 'config/config.php';
$settings = $rawConfig['settings'] ?? [];

/** Value in config.php for a canonical key, honouring the section-bucket layout. */
$rawValueFor = static function (ConfigKey $ck) use ($settings) {
    $fullKey = $ck->key;
    $section = $ck->section;

    if (!empty($section) && str_starts_with($fullKey, $section . '.')) {
        $sub = substr($fullKey, strlen($section) + 1);
        if (array_key_exists($section, $settings) && is_array($settings[$section])
            && array_key_exists($sub, $settings[$section])) {
            return ['found' => true, 'value' => $settings[$section][$sub]];
        }
        // some keys live flat at the top level even though they have a section
        if (array_key_exists($fullKey, $settings)) {
            return ['found' => true, 'value' => $settings[$fullKey]];
        }
        return ['found' => false, 'value' => null];
    }

    if (array_key_exists($fullKey, $settings)) {
        return ['found' => true, 'value' => $settings[$fullKey]];
    }
    return ['found' => false, 'value' => null];
};

$asText = static function ($v): string {
    if (is_bool($v)) {
        return $v ? 'true' : 'false';
    }
    if ($v === null) {
        return 'null';
    }
    return "'" . (string)$v . "'";
};

/** Loose equality between an env string and a config.php scalar, per the value's type. */
$sameValue = static function ($envRaw, $confVal, string $type): bool {
    $env = trim((string)$envRaw, " \t\"'");
    if ($type === ConfigSettingType::Boolean) {
        $b = in_array(strtolower($env), ['true', '1'], true);
        return $b === (bool)$confVal;
    }
    if ($type === ConfigSettingType::Integer) {
        return (int)$env === (int)$confVal;
    }
    return $env === trim((string)$confVal, " \t\"'");
};

$rows = [];
$shadowing = 0;

foreach (ConfigKeys::all() as $ck) {
    $envKey = ConfigKeysMeta::envKeyForConfig($ck);
    if ($envKey === null) {
        continue;
    }

    $envVal = getenv($envKey);
    if ($envVal === false || $envVal === '') {
        continue; // empty / unset -> config.php wins, nothing to audit
    }

    if ($filter !== null
        && !str_contains(strtolower($ck->key), $filter)
        && !str_contains(strtolower($envKey), $filter)) {
        continue;
    }

    $raw = $rawValueFor($ck);
    $effective = Configuration::Instance()->GetKey($ck);

    $isShadowing = $raw['found'] && !$sameValue($envVal, $raw['value'], $ck->type);
    if ($isShadowing) {
        $shadowing++;
    }
    if ($onlyShadowing && !$isShadowing) {
        continue;
    }

    $rows[] = [
        'flag' => $isShadowing ? 'SHADOWS' : ($raw['found'] ? 'same' : 'env-only'),
        'env' => "$envKey=$envVal",
        'config' => $raw['found'] ? $asText($raw['value']) : '(not in config.php)',
        'effective' => $asText($effective),
        'key' => $ck->key,
    ];
}

usort($rows, static fn ($a, $b) => [$a['flag'] !== 'SHADOWS', $a['key']] <=> [$b['flag'] !== 'SHADOWS', $b['key']]);

if (empty($rows)) {
    echo "No matching non-empty LB_* overrides in the effective .env.\n";
    exit(0);
}

$w = ['flag' => 8, 'env' => 46, 'config' => 24, 'effective' => 18];
printf("%-{$w['flag']}s %-{$w['env']}s %-{$w['config']}s %-{$w['effective']}s %s\n", 'FLAG', 'app.env (override)', 'config.php', 'effective', 'key');
printf("%s\n", str_repeat('-', 130));
foreach ($rows as $r) {
    printf(
        "%-{$w['flag']}s %-{$w['env']}s %-{$w['config']}s %-{$w['effective']}s %s\n",
        $r['flag'],
        mb_strimwidth($r['env'], 0, $w['env'] - 1, '…'),
        mb_strimwidth($r['config'], 0, $w['config'] - 1, '…'),
        mb_strimwidth($r['effective'], 0, $w['effective'] - 1, '…'),
        $r['key']
    );
}

printf("\n%d override(s) shown; %d SHADOW config.php.\n", count($rows), $shadowing);
if ($shadowing > 0) {
    echo "\nSHADOWS = app.env is overriding a different value set in config.php. If that\n";
    echo "override wasn't deliberate, blank or delete the line in app.env so config.php wins,\n";
    echo "then `docker compose -f docker-compose.prod.yml restart app`.\n";
}

exit($shadowing > 0 ? 1 : 0);
