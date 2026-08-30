<?php

namespace App\Filament\Resources\ProjectRegisterResource\Pages;

use App\Filament\Resources\ProjectRegisterResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProjectRegister extends EditRecord
{
    protected static string $resource = ProjectRegisterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
