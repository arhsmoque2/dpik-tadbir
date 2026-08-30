<?php

namespace App\Filament\Resources\AllowedRegistrationEmailResource\Pages;

use App\Filament\Resources\AllowedRegistrationEmailResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAllowedRegistrationEmail extends CreateRecord
{
    protected static string $resource = AllowedRegistrationEmailResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id() ?? 1;

        return $data;
    }
}
