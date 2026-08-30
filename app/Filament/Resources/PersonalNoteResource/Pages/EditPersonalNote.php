<?php

namespace App\Filament\Resources\PersonalNoteResource\Pages;

use App\Filament\Resources\PersonalNoteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPersonalNote extends EditRecord
{
    protected static string $resource = PersonalNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
