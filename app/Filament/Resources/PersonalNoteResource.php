<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PersonalNoteResource\Pages;
use App\Models\PersonalNote;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PersonalNoteResource extends Resource
{
    protected static ?string $model = PersonalNote::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Personal Workspace';

    protected static ?string $modelLabel = 'Personal Note';

    protected static ?string $pluralModelLabel = 'Personal Notes';

    /**
     * @return Builder<PersonalNote>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<PersonalNote> $query */
        $query = parent::getEloquentQuery();

        return $query->where('user_id', '=', auth()->id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Toggle::make('is_pinned')
                    ->label('Pin to Top')
                    ->default(false),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                TextInput::make('project_code')
                    ->maxLength(50),
                RichEditor::make('content')
                    ->required()
                    ->columnSpanFull(),
                TagsInput::make('tags')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('is_pinned')
                    ->label('Pinned')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('project_code')
                    ->searchable()
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('is_pinned', 'desc')
            ->filters([
                TernaryFilter::make('is_pinned')
                    ->label('Pinned Notes'),
            ])
            ->actions([
                Action::make('toggle_pin')
                    ->label(fn (PersonalNote $record): string => $record->is_pinned ? 'Unpin' : 'Pin')
                    ->icon(fn (PersonalNote $record): string => $record->is_pinned ? 'heroicon-s-bookmark' : 'heroicon-o-bookmark')
                    ->color(fn (PersonalNote $record): string => $record->is_pinned ? 'warning' : 'gray')
                    ->action(fn (PersonalNote $record) => $record->update(['is_pinned' => ! $record->is_pinned])),
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
            'index' => Pages\ListPersonalNotes::route('/'),
            'create' => Pages\CreatePersonalNote::route('/create'),
            'edit' => Pages\EditPersonalNote::route('/{record}/edit'),
        ];
    }
}
