<?php

/**
 * Value object for one entry in the simplified timezone picker: a real IANA zone paired with
 * an optional translation key for its region label (e.g. "Asia/Taipei" / "TzTaipei", which
 * resolves through lang/*.php to "Beijing, Singapore, Taipei" / "北京、新加坡、台北").
 */
class TimezoneOption
{
    public string $Zone;
    public ?string $LabelKey;

    public function __construct(string $zone, ?string $labelKey)
    {
        $this->Zone = $zone;
        $this->LabelKey = $labelKey;
    }
}

/**
 * A curated, one-entry-per-UTC-offset alternative to dumping all ~400 DateTimeZone::listIdentifiers()
 * city names into a <select> (see config/timezones.php / $GLOBALS['APP_TIMEZONES'], still used
 * as-is by schedule/system-config timezone pickers that weren't part of this simplification).
 *
 * Every stored/selected value is still a real IANA identifier -- only the picker list and its
 * display label are simplified, so Date/DateRange/etc. keep working with legitimate zones and
 * DST is handled correctly (FormatLabel() computes the GMT offset live from the zone's actual
 * current rule rather than a hardcoded number, so e.g. America/New_York correctly reads
 * "GMT-05:00" in winter and "GMT-04:00" in summer without any extra bookkeeping here).
 *
 * Each region label is a handful of well-known cities sharing that offset (e.g. "Beijing,
 * Singapore, Taipei"), not just the one representative city the picked IANA zone happens to be
 * named after -- translated via lang/*.php (Tz* keys) like every other user-facing string, so
 * it reads correctly in all three enabled UI languages, not just English.
 */
class SimplifiedTimezones
{
    public const DEFAULT_ZONE = 'Asia/Taipei';

    // West to East by STANDARD (non-DST) offset -- a fixed authored order, not re-sorted by the
    // live offset computed in FormatLabel(), so the list does not reshuffle itself as different
    // regions enter/exit daylight saving at different times of year.
    private const ZONES = [
        ['Etc/GMT+12', 'TzDateLineWest'],
        ['Pacific/Pago_Pago', 'TzSamoa'],
        ['Pacific/Honolulu', 'TzHawaii'],
        ['America/Anchorage', 'TzAlaska'],
        ['America/Los_Angeles', 'TzPacific'],
        ['America/Denver', 'TzMountain'],
        ['America/Chicago', 'TzCentral'],
        ['America/New_York', 'TzEastern'],
        ['America/Halifax', 'TzAtlanticCanada'],
        ['America/Sao_Paulo', 'TzBrasilia'],
        ['Atlantic/Azores', 'TzAzores'],
        ['UTC', 'TzLondon'],
        ['Europe/Paris', 'TzCentralEurope'],
        ['Europe/Athens', 'TzEasternEurope'],
        ['Europe/Moscow', 'TzMoscow'],
        ['Asia/Tehran', 'TzTehran'],
        ['Asia/Dubai', 'TzDubai'],
        ['Asia/Kabul', 'TzKabul'],
        ['Asia/Karachi', 'TzKarachi'],
        ['Asia/Kolkata', 'TzIndia'],
        ['Asia/Kathmandu', 'TzKathmandu'],
        ['Asia/Dhaka', 'TzDhaka'],
        ['Asia/Yangon', 'TzYangon'],
        ['Asia/Bangkok', 'TzBangkok'],
        [self::DEFAULT_ZONE, 'TzTaipei'],
        ['Asia/Seoul', 'TzSeoul'],
        ['Australia/Adelaide', 'TzAdelaide'],
        ['Australia/Sydney', 'TzSydney'],
        ['Pacific/Noumea', 'TzNewCaledonia'],
        ['Pacific/Auckland', 'TzAuckland'],
        ['Pacific/Tongatapu', 'TzTonga'],
        ['Pacific/Kiritimati', 'TzKiritimati'],
    ];

    /**
     * @param string|null $currentValue An existing stored value (editing a specific user) that
     *   should stay selected/visible even if it is not one of the curated zones above -- e.g.
     *   legacy data, or a zone that behaves identically to a listed one under a different name.
     *   Saving the form again without touching this field must not silently change it.
     * @return TimezoneOption[]
     */
    public static function GetOptions($currentValue = null)
    {
        $zones = self::ZONES;

        if (!empty($currentValue) && !in_array($currentValue, array_column($zones, 0), true)) {
            $zones[] = [$currentValue, null];
        }

        $options = [];
        foreach ($zones as $zone) {
            $options[] = new TimezoneOption($zone[0], $zone[1]);
        }

        return $options;
    }

    /**
     * "(GMT+08:00) Beijing, Singapore, Taipei" / "(GMT+00:00) ..." / falls back to the bare
     * zone name if it cannot be resolved (e.g. legacy data that is no longer a recognized
     * identifier) or has no curated label key (an appended $currentValue from GetOptions()).
     */
    public static function FormatLabel($zone, $labelKey)
    {
        $label = $labelKey !== null ? Resources::GetInstance()->GetString($labelKey) : null;

        try {
            $offsetSeconds = (new DateTimeZone($zone))->getOffset(new DateTime('now', new DateTimeZone('UTC')));
        } catch (Exception $e) {
            return $label !== null ? "$zone ($label)" : $zone;
        }

        $sign = $offsetSeconds < 0 ? '-' : '+';
        $absMinutes = intdiv(abs($offsetSeconds), 60);
        $gmt = sprintf('(GMT%s%02d:%02d)', $sign, intdiv($absMinutes, 60), $absMinutes % 60);

        return $label !== null ? "$gmt $label" : $gmt;
    }
}
