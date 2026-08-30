<?php

namespace App\Filament\Resources\ExecutivePresetResource\Pages;

use App\Filament\Resources\ExecutivePresetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExecutivePresets extends ListRecords
{
    protected static string $resource = ExecutivePresetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
