<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ExecutiveSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Executive Settings';

    protected static ?string $title = 'Settings & Integration';

    protected static string|\UnitEnum|null $navigationGroup = 'AI Configuration';

    protected string $view = 'filament.pages.executive-settings';
}
