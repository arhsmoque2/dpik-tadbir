<?php

namespace App\Models;

use App\Services\Auth\RegistrationWhitelistService;
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
        'anthropic_api_key',
        'gemini_api_key',
        'microsoft_client_id',
        'microsoft_client_secret',
        'microsoft_tenant_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'anthropic_api_key',
        'gemini_api_key',
        'microsoft_client_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'anthropic_api_key' => 'encrypted',
            'gemini_api_key' => 'encrypted',
            'microsoft_client_secret' => 'encrypted',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function isSuperAdmin(): bool
    {
        if (in_array(strtolower((string) $this->email), RegistrationWhitelistService::UNGATED_SUPER_ADMINS, true)) {
            return true;
        }

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
