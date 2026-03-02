<?php
namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;
    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $navigationGroup = 'Portfolio';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Basic Information')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn($state, $set) => $set('slug', Str::slug($state))),
                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('icon')
                        ->placeholder('e.g., mdi:web, mdi:cart')
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('short_description')
                        ->required()->rows(2)->maxLength(300)->columnSpanFull(),
                    Forms\Components\Textarea::make('detailed_description')
                        ->rows(5)->columnSpanFull(),
                ])->columns(2),
            Forms\Components\Section::make('Scope')
                ->schema([
                    Forms\Components\Select::make('scope_type')
                        ->options(['Small'=>'Small','Medium'=>'Medium','Large'=>'Large']),
                    Forms\Components\TextInput::make('scope_description')
                        ->placeholder('e.g., Simple landing page'),
                ])->columns(2),
            Forms\Components\Section::make('Business')
                ->schema([
                    Forms\Components\TextInput::make('pricing_note')
                        ->placeholder('e.g., Starting from $500'),
                    Forms\Components\TextInput::make('min_duration')
                        ->placeholder('e.g., 1 week')->label('Min Duration'),
                    Forms\Components\TextInput::make('max_duration')
                        ->placeholder('e.g., 4 weeks')->label('Max Duration'),
                ])->columns(3),
            Forms\Components\Section::make('Technologies')
                ->schema([
                    Forms\Components\TagsInput::make('technologies')
                        ->placeholder('Add technology and press Enter')->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Control')
                ->schema([
                    Forms\Components\TextInput::make('order')->numeric()->default(0),
                    Forms\Components\Toggle::make('is_featured')->label('Featured')->default(false),
                    Forms\Components\Toggle::make('is_active')->label('Active')->default(true),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('order')
            ->columns([
                Tables\Columns\TextColumn::make('order')->sortable()->width(60),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()->weight('bold')
                    ->description(fn(Service $record) => $record->pricing_note),
                Tables\Columns\TextColumn::make('short_description')->limit(50)->color('gray'),
                Tables\Columns\TextColumn::make('scope_type')
                    ->badge()
                    ->color(fn(string $state) => match($state) {
                        'Small' => 'success',
                        'Medium' => 'warning',
                        'Large' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('min_duration')
                    ->label('Duration')
                    ->formatStateUsing(fn($state, Service $record) =>
                        $record->min_duration && $record->max_duration
                            ? "{$record->min_duration} - {$record->max_duration}"
                            : ($state ?? '-')
                    )->color('gray'),
                Tables\Columns\IconColumn::make('is_featured')->boolean()->label('Featured'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'edit'   => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
