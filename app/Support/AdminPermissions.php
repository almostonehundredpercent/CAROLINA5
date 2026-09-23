<?php

namespace App\Support;

use App\Models\User;

/**
 * The single source of truth for staff access. Keep route checks, navigation,
 * and controller decisions aligned by asking this class rather than checking a
 * role string in individual screens.
 */
final class AdminPermissions
{
    private const ABILITIES = [
        'dashboard' => ['admin', 'viewer'],
        'frontdesk' => ['admin', 'front_desk'],
        'bookings' => ['admin', 'front_desk'],
        'guests' => ['admin', 'front_desk'],
        'payments' => ['admin', 'front_desk'],
        'reviews' => ['admin', 'front_desk'],
        'rooms' => ['admin', 'front_desk', 'housekeeping', 'viewer'],
        // Front desk needs to mark a room ready, cleaning, or under
        // maintenance as part of an arrival/departure workflow. They cannot
        // edit the room catalogue or staff accounts.
        'room_operations' => ['admin', 'front_desk', 'housekeeping'],
        'reports' => ['admin'],
        'activity' => ['admin'],
        'staff' => ['admin'],
        'exports' => ['admin', 'front_desk'],
    ];

    public static function role(User $user): string
    {
        return $user->isAdmin() ? 'admin' : ($user->staff_role ?: 'guest');
    }

    public static function allows(User $user, string $ability): bool
    {
        return in_array(self::role($user), self::ABILITIES[$ability] ?? [], true);
    }

    public static function landingRoute(User $user): string
    {
        return match (self::role($user)) {
            // Start people where their shift begins. The overview remains
            // available, but daily work should not be hidden behind it.
            'admin', 'front_desk' => 'admin.frontdesk',
            'viewer' => 'admin.dashboard',
            'housekeeping' => 'admin.rooms',
            default => 'home',
        };
    }

    public static function navigation(User $user): array
    {
        return [
            ['label' => 'Today', 'route' => 'admin.frontdesk', 'icon' => '◷', 'ability' => 'frontdesk', 'group' => 'primary'],
            ['label' => 'Arrivals', 'route' => 'admin.arrivals', 'icon' => '↗', 'ability' => 'frontdesk', 'group' => 'primary'],
            ['label' => 'Bookings', 'route' => 'admin.bookings', 'icon' => '▤', 'ability' => 'bookings', 'group' => 'primary'],
            ['label' => 'Rooms', 'route' => 'admin.rooms', 'icon' => '⌂', 'ability' => 'rooms', 'group' => 'primary'],
            ['label' => 'Guests', 'route' => 'admin.guests', 'icon' => '♙', 'ability' => 'guests', 'group' => 'primary'],
            ['label' => 'Overview', 'route' => 'admin.dashboard', 'icon' => '▦', 'ability' => 'dashboard', 'group' => 'more'],
            ['label' => 'Reports', 'route' => 'admin.reports', 'icon' => '⌁', 'ability' => 'reports', 'group' => 'more'],
            ['label' => 'Activity log', 'route' => 'admin.activity', 'icon' => '◷', 'ability' => 'activity', 'group' => 'more'],
            ['label' => 'Staff access', 'route' => 'admin.staff', 'icon' => '♙', 'ability' => 'staff', 'group' => 'more'],
        ];
    }
}
