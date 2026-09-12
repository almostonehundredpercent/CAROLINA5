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
        'room_operations' => ['admin', 'housekeeping'],
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
            'admin', 'viewer' => 'admin.dashboard',
            'front_desk' => 'admin.frontdesk',
            'housekeeping' => 'admin.rooms',
            default => 'home',
        };
    }

    public static function navigation(User $user): array
    {
        return [
            ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => '▦', 'ability' => 'dashboard'],
            ['label' => 'Today', 'route' => 'admin.frontdesk', 'icon' => '◷', 'ability' => 'frontdesk'],
            ['label' => 'Bookings', 'route' => 'admin.bookings', 'icon' => '▤', 'ability' => 'bookings'],
            ['label' => 'Guests', 'route' => 'admin.guests', 'icon' => '♙', 'ability' => 'guests'],
            ['label' => 'Rooms', 'route' => 'admin.rooms', 'icon' => '⌂', 'ability' => 'rooms'],
            ['label' => 'Reports', 'route' => 'admin.reports', 'icon' => '⌁', 'ability' => 'reports'],
            ['label' => 'Activity log', 'route' => 'admin.activity', 'icon' => '◷', 'ability' => 'activity'],
            ['label' => 'Staff access', 'route' => 'admin.staff', 'icon' => '♙', 'ability' => 'staff'],
        ];
    }
}
