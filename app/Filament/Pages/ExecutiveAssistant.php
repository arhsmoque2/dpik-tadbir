<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ExecutiveAssistant extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'AI Executive Copilot';

    protected static ?string $title = 'Executive Copilot';

    protected static string|\UnitEnum|null $navigationGroup = 'Copilot Command Center';

    protected string $view = 'filament.pages.executive-assistant';
}
