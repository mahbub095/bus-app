<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Menu keys that may be assigned to an Admin account.
     *
     * @var list<string>
     */
    public const MENU_KEYS = [
        'coach-services', 'bookings', 'cancel-requests', 'stations',
        'buses', 'routes', 'schedules', 'promotions', 'users', 'reports',
        'license', 'site-settings', 'gateways', 'profile',
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'menu_permissions',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'menu_permissions' => 'array',
        ];
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin' || $this->role === 'super_admin';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function hasMenuPermission(string $menu): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (!$this->isAdmin()) {
            return false;
        }

        if (is_null($this->menu_permissions)) {
            return true;
        }

        return in_array($menu, $this->menu_permissions);
    }
}
