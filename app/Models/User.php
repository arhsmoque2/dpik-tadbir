<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
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
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * @return HasMany<PersonalNote, $this>
     */
    public function personalNotes(): HasMany
    {
        return $this->hasMany(PersonalNote::class);
    }

    /**
     * @return HasMany<PersonalTask, $this>
     */
    public function personalTasks(): HasMany
    {
        return $this->hasMany(PersonalTask::class);
    }

    /**
     * @return HasMany<ExecutivePreset, $this>
     */
    public function executivePresets(): HasMany
    {
        return $this->hasMany(ExecutivePreset::class);
    }

    /**
     * @return HasMany<ChatSession, $this>
     */
    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }

    /**
     * @return HasMany<AiActionReceipt, $this>
     */
    public function actionReceipts(): HasMany
    {
        return $this->hasMany(AiActionReceipt::class);
    }

    /**
     * @return HasOne<UserPersonalizationProfile, $this>
     */
    public function personalizationProfile(): HasOne
    {
        return $this->hasOne(UserPersonalizationProfile::class);
    }
}
