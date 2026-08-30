<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExecutivePresetResource\Pages;
use App\Models\ExecutivePreset;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExecutivePresetResource extends Resource
{
    protected static ?string $model = ExecutivePreset::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    protected static string|\UnitEnum|null $navigationGroup = 'AI Configuration';

    protected static ?string $modelLabel = 'Executive Preset';

    protected static ?string $pluralModelLabel = 'Executive Presets';

    /**
     * @return Builder<ExecutivePreset>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<ExecutivePreset> $query */
        $query = parent::getEloquentQuery();

        return $query->where(function (Builder $q) {
            $q->whereNull('user_id')->orWhere('user_id', '=', auth()->id());
        });
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Select::make('category')
                    ->options([
                        'inbox_triage' => 'Inbox Triage',
                        'project_update' => 'Project Update',
                        'stakeholder_reply' => 'Stakeholder Reply',
                        'report' => 'Report Generation',
                        'general' => 'General',
                    ])
                    ->default('inbox_triage')
                    ->required(),
                TextInput::make('icon')
                    ->maxLength(50),
                Toggle::make('is_active')
                    ->default(true),
                Textarea::make('description')
                    ->columnSpanFull()
                    ->rows(2),
                Textarea::make('prompt_template')
                    ->required()
                    ->columnSpanFull()
                    ->rows(5)
                    ->helperText('Supports variables like {{user_name}}, {{current_date}}, {{current_time}}'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                BadgeColumn::make('category')
                    ->colors([
                        'primary' => 'inbox_triage',
                        'success' => 'project_update',
                        'warning' => 'stakeholder_reply',
                        'danger' => 'report',
                    ]),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('user_id')
                    ->label('Scope')
                    ->formatStateUsing(fn ($state) => $state === null ? 'System Default' : 'Personal')
                    ->badge(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExecutivePresets::route('/'),
            'create' => Pages\CreateExecutivePreset::route('/create'),
            'edit' => Pages\EditExecutivePreset::route('/{record}/edit'),
        ];
    }
}
