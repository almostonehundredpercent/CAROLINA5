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
        'dashboard' => ['super_admin', 'admin', 'viewer'],
        'frontdesk' => ['super_admin', 'admin', 'front_desk'],
        'bookings' => ['super_admin', 'admin', 'front_desk'],
        'guests' => ['super_admin', 'admin', 'front_desk'],
        'payments' => ['super_admin', 'admin', 'front_desk'],
        'reviews' => ['super_admin', 'admin', 'front_desk'],
        'rooms' => ['super_admin', 'admin', 'front_desk', 'housekeeping', 'viewer'],
        // Front desk needs to mark a room ready, cleaning, or under
        // maintenance as part of an arrival/departure workflow. They cannot
        // edit the room catalogue or staff accounts.
        'room_operations' => ['super_admin', 'admin', 'front_desk', 'housekeeping'],
        'reports' => ['super_admin', 'admin'],
        'promos' => ['super_admin', 'admin'],
        'activity' => ['super_admin', 'admin'],
        'staff' => ['super_admin', 'admin'],
        'elevate_staff' => ['super_admin'],
        'exports' => ['super_admin', 'admin', 'front_desk'],
    ];

    public static function role(User $user): string
    {
        if ($user->isAdmin()) {
            return $user->staff_role === 'super_admin' ? 'super_admin' : 'admin';
        }

        return in_array($user->staff_role, ['front_desk', 'housekeeping', 'viewer'], true)
            ? $user->staff_role
            : 'guest';
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
            'super_admin', 'admin', 'front_desk' => 'admin.frontdesk',
            'viewer' => 'admin.dashboard',
            'housekeeping' => 'admin.rooms',
            default => 'home',
        };
    }

    public static function navigation(User $user): array
    {
        return [
            ['label' => 'Today', 'route' => 'admin.frontdesk', 'icon' => '◷', 'ability' => 'frontdesk', 'group' => 'primary'],
            ['label' => 'Bookings', 'route' => 'admin.bookings', 'icon' => '▤', 'ability' => 'bookings', 'group' => 'primary'],
            ['label' => 'Rooms', 'route' => 'admin.rooms', 'icon' => '⌂', 'ability' => 'rooms', 'group' => 'primary'],
            ['label' => 'Guests', 'route' => 'admin.guests', 'icon' => '♙', 'ability' => 'guests', 'group' => 'primary'],
            ['label' => 'Overview', 'route' => 'admin.dashboard', 'icon' => '▦', 'ability' => 'dashboard', 'group' => 'more'],
            ['label' => 'Reports', 'route' => 'admin.reports', 'icon' => '⌁', 'ability' => 'reports', 'group' => 'more'],
            ['label' => 'Promo codes', 'route' => 'admin.promos', 'icon' => '%', 'ability' => 'promos', 'group' => 'more'],
            ['label' => 'Activity log', 'route' => 'admin.activity', 'icon' => '◷', 'ability' => 'activity', 'group' => 'more'],
            ['label' => 'Staff access', 'route' => 'admin.staff', 'icon' => '♙', 'ability' => 'staff', 'group' => 'more'],
        ];
    }
}
