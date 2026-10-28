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
        'dashboard' => ['organization_admin', 'viewer'],
        'frontdesk' => ['organization_admin', 'front_desk'],
        'bookings' => ['organization_admin', 'front_desk'],
        'guests' => ['organization_admin', 'front_desk'],
        'payments' => ['organization_admin', 'front_desk'],
        'reviews' => ['organization_admin', 'front_desk'],
        'rooms' => ['organization_admin', 'front_desk', 'housekeeping', 'viewer'],
        // Front desk needs to mark a room ready, cleaning, or under
        // maintenance as part of an arrival/departure workflow. They cannot
        // edit the room catalogue or staff accounts.
        'room_operations' => ['organization_admin', 'front_desk', 'housekeeping'],
        'reports' => ['organization_admin'],
        'promos' => ['organization_admin'],
        'decision_support' => ['organization_admin'],
        'activity' => ['organization_admin'],
        'staff' => ['organization_admin'],
        'elevate_staff' => ['organization_admin'],
        'exports' => ['organization_admin', 'front_desk'],
    ];

    public static function role(User $user): string
    {
        if ($user->isAdmin()) {
            return 'organization_admin';
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
            'organization_admin', 'front_desk' => 'admin.frontdesk',
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
            ['label' => 'Decision support', 'route' => 'admin.decision-support', 'icon' => '◈', 'ability' => 'decision_support', 'group' => 'more'],
            ['label' => 'Promo codes', 'route' => 'admin.promos', 'icon' => '%', 'ability' => 'promos', 'group' => 'more'],
            ['label' => 'Activity log', 'route' => 'admin.activity', 'icon' => '◷', 'ability' => 'activity', 'group' => 'more'],
            ['label' => 'Staff access', 'route' => 'admin.staff', 'icon' => '♙', 'ability' => 'staff', 'group' => 'more'],
        ];
    }
}
