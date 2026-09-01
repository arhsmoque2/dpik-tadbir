<?php

namespace App\Filament\Resources\BundleResource\Pages;

use App\Filament\Resources\BundleResource;
use App\Services\Mcp\OutlookMcpBridge;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ViewRecord;

class ViewBundle extends ViewRecord
{
    protected static string $resource = BundleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('fetchLiveEmailBody')
                ->label('Fetch Full Body Live (Graph API)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->form([
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
                    if ($user instanceof \App\Models\User) {
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
