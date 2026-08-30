<?php

namespace App\Filament\Resources\ExecutivePresetResource\Pages;

use App\Filament\Resources\ExecutivePresetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExecutivePreset extends EditRecord
{
    protected static string $resource = ExecutivePresetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
