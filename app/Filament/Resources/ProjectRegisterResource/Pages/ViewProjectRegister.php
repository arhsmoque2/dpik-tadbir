<?php

namespace App\Filament\Resources\ProjectRegisterResource\Pages;

use App\Filament\Resources\ProjectRegisterResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProjectRegister extends ViewRecord
{
    protected static string $resource = ProjectRegisterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
