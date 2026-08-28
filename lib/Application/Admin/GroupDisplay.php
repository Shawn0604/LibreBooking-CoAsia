<?php

require_once(ROOT_DIR . 'Domain/Values/RoleLevel.php');

/**
 * Presentation-only overlay for 使用者管理 > 群組.
 *
 * The four seeded admin groups store English internal names (`Application
 * Administrators` …) whose `group_id` and `name` MUST NOT change -- role lookup,
 * AD sync name matching and CSV import all key off them. This helper maps a
 * {@see GroupItemView} to a Chinese display label + a canned purpose + a
 * human permission summary, keyed by the *role level the group grants*, never
 * by the stored string. Custom groups fall through to their stored name.
 *
 * `isReserved` (grants any role, or is the default group) is the single flag the
 * UI and the presenter guards use to forbid rename / delete / default-toggle.
 */
class GroupDisplay
{
    /**
     * @return array{
     *   labelKey: string, storedName: string, purposeKey: string,
     *   permissionSummaryKey: string, isReserved: bool, isDefault: bool,
     *   primaryRoleLevel: int|null, roleLevels: int[]
     * }
     */
    public static function Describe(GroupItemView $group): array
    {
        $roleLevel = self::PrimaryRoleLevel($group);

        $labelKey = '';
        $purposeKey = '';
        $permissionSummaryKey = '';

        switch ($roleLevel) {
            case RoleLevel::APPLICATION_ADMIN:
                $labelKey = 'GroupLabelApplicationAdmin';
                $purposeKey = 'GroupPurposeApplicationAdmin';
                $permissionSummaryKey = 'GroupPermSummaryApplicationAdmin';
                break;
            case RoleLevel::GROUP_ADMIN:
                $labelKey = 'GroupLabelGroupAdmin';
                $purposeKey = 'GroupPurposeGroupAdmin';
                $permissionSummaryKey = 'GroupPermSummaryGroupAdmin';
                break;
            case RoleLevel::RESOURCE_ADMIN:
                $labelKey = 'GroupLabelResourceAdmin';
                $purposeKey = 'GroupPurposeResourceAdmin';
                $permissionSummaryKey = 'GroupPermSummaryResourceAdmin';
                break;
            case RoleLevel::SCHEDULE_ADMIN:
                $labelKey = 'GroupLabelScheduleAdmin';
                $purposeKey = 'GroupPurposeScheduleAdmin';
                $permissionSummaryKey = 'GroupPermSummaryScheduleAdmin';
                break;
            default:
                if ($group->IsDefault()) {
                    $purposeKey = 'GroupPurposeDefault';
                }
        }

        return [
            'labelKey' => $labelKey,
            'storedName' => $group->Name(),
            'purposeKey' => $purposeKey,
            'permissionSummaryKey' => $permissionSummaryKey,
            'isReserved' => self::IsReserved($group),
            'isDefault' => (bool) $group->IsDefault(),
            'primaryRoleLevel' => $roleLevel,
            'roleLevels' => self::RoleLevels($group),
        ];
    }

    /**
     * Highest-privilege role level the group grants, or null for a role-less group.
     * @return int|null
     */
    public static function PrimaryRoleLevel(GroupItemView $group)
    {
        if ($group->IsAdmin()) {
            return RoleLevel::APPLICATION_ADMIN;
        }
        if ($group->IsGroupAdmin()) {
            return RoleLevel::GROUP_ADMIN;
        }
        if ($group->IsResourceAdmin()) {
            return RoleLevel::RESOURCE_ADMIN;
        }
        if ($group->IsScheduleAdmin()) {
            return RoleLevel::SCHEDULE_ADMIN;
        }
        return null;
    }

    /**
     * @return int[]
     */
    public static function RoleLevels(GroupItemView $group): array
    {
        $levels = [];
        if ($group->IsAdmin()) {
            $levels[] = RoleLevel::APPLICATION_ADMIN;
        }
        if ($group->IsGroupAdmin()) {
            $levels[] = RoleLevel::GROUP_ADMIN;
        }
        if ($group->IsResourceAdmin()) {
            $levels[] = RoleLevel::RESOURCE_ADMIN;
        }
        if ($group->IsScheduleAdmin()) {
            $levels[] = RoleLevel::SCHEDULE_ADMIN;
        }
        return $levels;
    }

    /**
     * A reserved group grants a role or is the default group: it must never be
     * renamed, deleted, or have its default flag toggled.
     */
    public static function IsReserved(GroupItemView $group): bool
    {
        return self::PrimaryRoleLevel($group) !== null || (bool) $group->IsDefault();
    }
}
