<?php

namespace App\Filament\Resources\ProjectRegisterResource\Pages;

use App\Filament\Resources\ProjectRegisterResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProjectRegisters extends ListRecords
{
    protected static string $resource = ProjectRegisterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
