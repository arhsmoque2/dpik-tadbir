<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BundleResource\Pages;
use App\Models\Bundle;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BundleResource extends Resource
{
    protected static ?string $model = Bundle::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder-open';

    protected static string|\UnitEnum|null $navigationGroup = 'Executive Hub';

    protected static ?string $modelLabel = 'Mail Bundle';

    protected static ?string $pluralModelLabel = 'Mail Bundles';

    /**
     * @return Builder<Bundle>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Bundle> $query */
        $query = parent::getEloquentQuery();

        return $query->where('user_id', '=', auth()->id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('filter_label')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('project_code')
                            ->placeholder('e.g. PC-2023-011'),

                        DateTimePicker::make('retrieved_at')
                            ->required(),

                        Textarea::make('notes')
                            ->label('Executive Personal Notes (Zero AI Cost)')
                            ->placeholder('Write personal notes on this bundle...')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('filter_label')
                    ->label('Bundle Title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('project_code')
                    ->label('Project')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->placeholder('General'),

                TextColumn::make('email_count')
                    ->label('Emails')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('retrieved_at')
                    ->label('Retrieved')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                TextColumn::make('notes')
                    ->label('Notes Preview')
                    ->limit(40)
                    ->placeholder('No notes'),
            ])
            ->defaultSort('retrieved_at', 'desc')
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                InfolistSection::make('Bundle Information')
                    ->schema([
                        TextEntry::make('filter_label')
                            ->label('Filter Label'),
                        TextEntry::make('project_code')
                            ->label('Project Code')
                            ->badge(),
                        TextEntry::make('retrieved_at')
                            ->label('Retrieval Timestamp')
                            ->dateTime('d M Y, H:i:s'),
                        TextEntry::make('email_count')
                            ->label('Materialized Email Count'),
                        TextEntry::make('notes')
                            ->label('Executive Personal Notes')
                            ->placeholder('No personal notes recorded on this bundle.')
                            ->columnSpanFull(),
                    ])
                    ->columns(4),

                InfolistSection::make('Materialized Email Pointers (Graph API Metadata)')
                    ->schema([
                        RepeatableEntry::make('bundleEmails')
                            ->schema([
                                TextEntry::make('from_name')
                                    ->label('Sender'),
                                TextEntry::make('from_email')
                                    ->label('Sender Address'),
                                TextEntry::make('subject')
                                    ->label('Subject')
                                    ->weight('bold'),
                                TextEntry::make('received_at')
                                    ->label('Received')
                                    ->dateTime('d M Y, H:i'),
                                TextEntry::make('snippet')
                                    ->label('Preview Snippet')
                                    ->columnSpanFull(),
                            ])
                            ->columns(4),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBundles::route('/'),
            'view' => Pages\ViewBundle::route('/{record}'),
            'edit' => Pages\EditBundle::route('/{record}/edit'),
        ];
    }
}
