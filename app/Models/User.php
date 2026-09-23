<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    public function sendEmailVerificationNotification()
    {
        $this->notify(new \App\Notifications\QueuedVerifyEmail());
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token)
    {
        $this->notify(new \App\Notifications\QueuedResetPassword($token));
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'staff_role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Check if user is an admin
     */
    public function isAdmin(): bool
    {
        return $this->is_admin ?? false;
    }

    public function hasStaffAccess(): bool
    {
        return $this->isAdmin() || in_array($this->staff_role, ['front_desk', 'housekeeping', 'viewer'], true);
    }

    public function canManageBookings(): bool
    {
        return $this->isAdmin() || $this->staff_role === 'front_desk';
    }

    public function canManageRooms(): bool
    {
        return $this->isAdmin() || in_array($this->staff_role, ['front_desk', 'housekeeping'], true);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
