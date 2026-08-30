<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class ExecutiveSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Executive Settings';

    protected static ?string $title = 'Settings & Integration';

    protected static string|\UnitEnum|null $navigationGroup = 'AI Configuration';

    protected string $view = 'filament.pages.executive-settings';

    public ?string $anthropic_api_key = null;

    public ?string $gemini_api_key = null;

    public function mount(): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user) {
            $this->anthropic_api_key = $user->anthropic_api_key;
            $this->gemini_api_key = $user->gemini_api_key;
        }
    }

    public function save(): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $user->update([
            'anthropic_api_key' => filled($this->anthropic_api_key) ? trim((string) $this->anthropic_api_key) : null,
            'gemini_api_key' => filled($this->gemini_api_key) ? trim((string) $this->gemini_api_key) : null,
        ]);

        Notification::make()
            ->title('API Keys Updated')
            ->body('Your sovereign AI API keys have been saved securely.')
            ->success()
            ->send();
    }
}
