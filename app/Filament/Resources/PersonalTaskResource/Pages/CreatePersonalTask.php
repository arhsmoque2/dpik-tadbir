<?php

namespace App\Filament\Resources\PersonalTaskResource\Pages;

use App\Filament\Resources\PersonalTaskResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePersonalTask extends CreateRecord
{
    protected static string $resource = PersonalTaskResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }
}
