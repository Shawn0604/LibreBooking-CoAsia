<?php

/**
 * The fixed 4-region whitelist for `resources`.`location` (CoAsia admin-center 地區
 * classification — Manage Resources, Admin Reservations, Manage Schedules, Overview, Usage
 * Analytics, CSV import/export). Values are the SHORT region label shown directly in table
 * columns and the "地區・會議室" pairing (e.g. "台北 · 深圳 Shenzhen"); the fuller
 * "台北實體會議室"-style phrasing used for filter-dropdown option text lives in lang files
 * only (ResourceLocationTaipei etc. in lang/zh_tw.php), not here.
 *
 * Deliberately a fixed whitelist, not the free-text field this column started as — see
 * database_schema/coasia-resources-location.sql and the write-path validation in
 * ManageResourcesPresenter (ChangeLocation/BulkUpdate/CSV import): a single typo in the old
 * free-text field would otherwise silently create a 5th "region" that only ever shows up as
 * a stray bucket in Usage Analytics.
 */
class ResourceLocation
{
    public const TAIPEI = '台北';
    public const SHENZHEN = '深圳';
    public const SHANGHAI = '上海';
    public const CROSS_REGION = '跨地區';

    /**
     * @return string[]
     */
    public static function All()
    {
        return [self::TAIPEI, self::SHENZHEN, self::SHANGHAI, self::CROSS_REGION];
    }

    /**
     * @param string|null $location
     * @return bool
     */
    public static function IsValid($location)
    {
        return in_array($location, self::All(), true);
    }

    /**
     * Display rank for grouping/sorting lists by region — 台北→深圳→上海→跨地區, matching the
     * Overview FullCalendar resource picker's own group order (OverviewFullCalendarPresenter::
     * GROUP_DISPLAY_ORDER) so a resource's region reads the same way everywhere in the app.
     * Anything not in the whitelist (empty/unset, or a stray pre-whitelist free-text value)
     * sorts last rather than erroring.
     *
     * @param string|null $location
     * @return int
     */
    public static function SortRank($location)
    {
        $rank = array_search($location, self::All(), true);
        return $rank === false ? count(self::All()) : $rank;
    }
}
