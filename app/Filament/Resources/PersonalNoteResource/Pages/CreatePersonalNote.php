<?php

namespace App\Filament\Resources\PersonalNoteResource\Pages;

use App\Filament\Resources\PersonalNoteResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePersonalNote extends CreateRecord
{
    protected static string $resource = PersonalNoteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id() ?? 1;

        return $data;
    }
}
