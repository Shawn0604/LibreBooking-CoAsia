<?php

/**
 * Same resource-color assignment Quick Create's own resource picker uses
 * (Presenters/OverviewFullCalendarPresenter.php::AssignColors(), duplicated again from
 * Presenters/OverviewPresenter.php::AssignColors() before this) — pulled out here rather than
 * copy-pasted a third time so every resource picker in the app (calendar sidebar, Quick Create,
 * the classic reservation form) shows the same dot color for the same resource: the resource's
 * own admin-configured color if it has one, otherwise a stable palette color cycled by list
 * position so resources without a manual color still look visually distinct from each other.
 */
class ResourceColorAssigner
{
    private const FALLBACK_COLORS = [
        '#d50000', '#e67c73', '#f4511e', '#f6bf26', '#33b679', '#0b8043',
        '#039be5', '#3f51b5', '#7986cb', '#8e24aa', '#616161',
    ];

    /**
     * @param $resources array|IBookableResource[]
     * @return array resourceId => ['bg' => string, 'fg' => string]
     */
    public static function Assign($resources)
    {
        $colors = [];
        foreach ($resources as $index => $resource) {
            if ($resource->HasColor()) {
                $colors[$resource->GetId()] = [
                    'bg' => $resource->GetColor(),
                    'fg' => $resource->GetTextColor() ?: '#ffffff',
                ];
            } else {
                $hex = self::FALLBACK_COLORS[$index % count(self::FALLBACK_COLORS)];
                $colors[$resource->GetId()] = [
                    'bg' => $hex,
                    'fg' => '#ffffff',
                ];
            }
        }
        return $colors;
    }
}
