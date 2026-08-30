<?php

namespace App\Filament\Resources\AllowedRegistrationEmailResource\Pages;

use App\Filament\Resources\AllowedRegistrationEmailResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAllowedRegistrationEmails extends ListRecords
{
    protected static string $resource = AllowedRegistrationEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
