<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'google_sub', 'is_demo', 'timezone', 'notify_daily', 'notify_hour'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    // Role is deliberately not fillable; promotion happens only via the user:promote command.
    protected $attributes = ['role' => 'owner', 'is_demo' => false, 'timezone' => 'UTC', 'notify_daily' => true, 'notify_hour' => 7];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'is_demo' => 'boolean',
            'notify_daily' => 'boolean',
            'notify_hour' => 'integer',
        ];
    }

    public function googleAccount(): HasOne
    {
        return $this->hasOne(GoogleAccount::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function reviews(): HasManyThrough
    {
        return $this->hasManyThrough(Review::class, Location::class);
    }

    public function businessGuideline(): HasOne
    {
        return $this->hasOne(BusinessGuideline::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function toProfile(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'is_demo' => $this->is_demo,
            'timezone' => $this->timezone,
            'notify_daily' => $this->notify_daily,
            'notify_hour' => $this->notify_hour,
            'google_connected' => $this->googleAccount()->exists(),
        ];
    }
}
