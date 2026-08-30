<?php

namespace App\Filament\Resources\ProjectRegisterResource\Pages;

use App\Filament\Resources\ProjectRegisterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProjectRegister extends CreateRecord
{
    protected static string $resource = ProjectRegisterResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id() ?? 1;

        return $data;
    }
}
