<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PersonalTaskResource\Pages;
use App\Models\PersonalTask;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PersonalTaskResource extends Resource
{
    protected static ?string $model = PersonalTask::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'Personal Workspace';

    protected static ?string $modelLabel = 'Personal Task';

    protected static ?string $pluralModelLabel = 'Personal Tasks';

    /**
     * @return Builder<PersonalTask>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<PersonalTask> $query */
        $query = parent::getEloquentQuery();

        return $query->where('user_id', '=', auth()->id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                TextInput::make('project_code')
                    ->maxLength(50),
                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('pending')
                    ->required(),
                DatePicker::make('due_date'),
                Textarea::make('description')
                    ->columnSpanFull()
                    ->rows(3),
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
                TextColumn::make('project_code')
                    ->searchable()
                    ->badge(),
                BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'primary' => 'in_progress',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->sortable(),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('due_date', 'asc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->actions([
                Action::make('toggle_complete')
                    ->label(fn (PersonalTask $record): string => $record->status === 'completed' ? 'Reopen' : 'Complete')
                    ->icon(fn (PersonalTask $record): string => $record->status === 'completed' ? 'heroicon-o-arrow-uturn-left' : 'heroicon-o-check-circle')
                    ->color(fn (PersonalTask $record): string => $record->status === 'completed' ? 'gray' : 'success')
                    ->action(fn (PersonalTask $record) => $record->update(['status' => $record->status === 'completed' ? 'pending' : 'completed'])),
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
            'index' => Pages\ListPersonalTasks::route('/'),
            'create' => Pages\CreatePersonalTask::route('/create'),
            'edit' => Pages\EditPersonalTask::route('/{record}/edit'),
        ];
    }
}
