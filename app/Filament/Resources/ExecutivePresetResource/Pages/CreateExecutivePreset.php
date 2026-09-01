<?php

namespace App\Filament\Resources\ExecutivePresetResource\Pages;

use App\Filament\Resources\ExecutivePresetResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExecutivePreset extends CreateRecord
{
    protected static string $resource = ExecutivePresetResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }
}
