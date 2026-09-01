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

/**
 * @property array<string, mixed>|list<array{key: string, label: string, icon: string, url: string}>|null $bottom_nav_slots
 */
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
        'openrouter_api_key',
        'favorite_model_1',
        'favorite_model_2',
        'favorite_model_3',
        'microsoft_client_id',
        'microsoft_client_secret',
        'microsoft_tenant_id',
        'bottom_nav_slots',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'anthropic_api_key',
        'gemini_api_key',
        'openrouter_api_key',
        'microsoft_client_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'anthropic_api_key' => 'encrypted',
            'gemini_api_key' => 'encrypted',
            'openrouter_api_key' => 'encrypted',
            'microsoft_client_secret' => 'encrypted',
            'bottom_nav_slots' => 'array',
        ];
    }

    /**
     * Get configured mobile bottom navigation slots or defaults.
     *
     * @return list<array{key: string, label: string, icon: string, url: string}>
     */
    public function getBottomNavSlots(): array
    {
        if (is_array($this->bottom_nav_slots) && $this->bottom_nav_slots !== []) {
            /** @var list<array{key: string, label: string, icon: string, url: string}> $slots */
            $slots = $this->bottom_nav_slots;

            return $slots;
        }

        return [
            ['key' => 'copilot', 'label' => 'Copilot', 'icon' => 'heroicon-o-sparkles', 'url' => '/admin/executive-assistant'],
            ['key' => 'bundles', 'label' => 'Bundles', 'icon' => 'heroicon-o-folder-open', 'url' => '/admin/bundles'],
            ['key' => 'notes', 'label' => 'Notes', 'icon' => 'heroicon-o-document-text', 'url' => '/admin/personal-notes'],
            ['key' => 'settings', 'label' => 'Settings', 'icon' => 'heroicon-o-cog-6-tooth', 'url' => '/admin/executive-settings'],
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
