<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectRegisterResource\Pages;
use App\Models\ProjectRegistryEntry;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProjectRegisterResource extends Resource
{
    protected static ?string $model = ProjectRegistryEntry::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    protected static string|\UnitEnum|null $navigationGroup = 'Enterprise Memory';

    protected static ?string $modelLabel = 'Project Memory Entry';

    protected static ?string $pluralModelLabel = 'Project Register';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('project_code')
                    ->required()
                    ->maxLength(50),
                TextInput::make('project_name')
                    ->maxLength(255),
                Select::make('source_type')
                    ->options([
                        'email_summary' => 'Email Summary',
                        'meeting_note' => 'Meeting Note',
                        'manual_entry' => 'Manual Entry',
                    ])
                    ->default('email_summary')
                    ->required(),
                DateTimePicker::make('recorded_at')
                    ->default(now()),
                Textarea::make('summary')
                    ->required()
                    ->columnSpanFull()
                    ->rows(4),
                TagsInput::make('decisions')
                    ->placeholder('Add decision point')
                    ->columnSpanFull(),
                TagsInput::make('commitments')
                    ->placeholder('Add commitment/promise')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('project_code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('project_name')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('summary')
                    ->limit(60)
                    ->searchable(),
                BadgeColumn::make('source_type')
                    ->colors([
                        'primary' => 'email_summary',
                        'warning' => 'meeting_note',
                        'success' => 'manual_entry',
                    ]),
                TextColumn::make('recorded_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('source_type')
                    ->options([
                        'email_summary' => 'Email Summary',
                        'meeting_note' => 'Meeting Note',
                        'manual_entry' => 'Manual Entry',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjectRegisters::route('/'),
            'create' => Pages\CreateProjectRegister::route('/create'),
            'edit' => Pages\EditProjectRegister::route('/{record}/edit'),
            'view' => Pages\ViewProjectRegister::route('/{record}'),
        ];
    }
}
