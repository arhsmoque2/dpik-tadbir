<?php

namespace App\Filament\Resources\BundleResource\Pages;

use App\Filament\Resources\BundleResource;
use App\Models\User;
use App\Services\Mcp\OutlookMcpBridge;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;

class ViewBundle extends ViewRecord
{
    protected static string $resource = BundleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('displayLiveBody')
                ->label('Live Email Body Content')
                ->modalHeading('Live Email Body (Graph API)')
                ->modalDescription('Full body fetched live from Microsoft Graph API without disk storage.')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->fillForm(fn (array $arguments): array => [
                    'body' => (string) ($arguments['body'] ?? 'No body content returned from Graph API.'),
                ])
                ->schema([
                    Textarea::make('body')
                        ->label('Email Body Content')
                        ->rows(15)
                        ->readOnly(),
                ]),
            Actions\Action::make('fetchLiveEmailBody')
                ->label('Fetch Full Body Live (Graph API)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->schema([
                    Select::make('message_id')
                        ->label('Select Email Pointer')
                        ->options(function ($record) {
                            return $record->bundleEmails->pluck('subject', 'message_id')->toArray();
                        })
                        ->required(),
                ])
                ->action(function (array $data, $record): void {
                    $messageId = (string) $data['message_id'];
                    $user = auth()->user();
                    if ($user instanceof User) {
                        $bridge = app(OutlookMcpBridge::class)->forUser($user);
                        $result = $bridge->readMessage($messageId, false);
                        $bodyText = (string) ($result['body'] ?? $result['content'] ?? 'No body content returned from Graph API.');

                        $this->mountAction('displayLiveBody', ['body' => $bodyText]);
                    }
                }),
            Actions\Action::make('askCopilot')
                ->label('Ask Copilot About Bundle')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->action(function ($record) {
                    $this->dispatch('open-copilot-drawer', bundleId: $record->id);
                }),
        ];
    }
}
