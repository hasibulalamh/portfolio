<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactSettingResource\Pages;
use App\Models\ContactSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContactSettingResource extends Resource
{
    protected static ?string $model = ContactSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Contact Settings';
    protected static ?string $navigationGroup = 'Contact';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Section Content')
                    ->schema([
                        Forms\Components\TextInput::make('title')->required()->default('Get In Touch')->maxLength(255),
                        Forms\Components\TextInput::make('subtitle')->maxLength(255),
                        Forms\Components\Textarea::make('description')->rows(3)->maxLength(500),
                    ])->columns(1),

                Forms\Components\Section::make('Contact Information')
                    ->schema([
                        Forms\Components\TextInput::make('email')->email()->required()->maxLength(255),
                        Forms\Components\TextInput::make('phone')->tel()->maxLength(255),
                        Forms\Components\TextInput::make('address')->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Availability Status')
                    ->schema([
                        Forms\Components\Select::make('availability_status')
                            ->required()
                            ->options([
                                'available'   => 'Available for Work',
                                'busy'        => 'Busy / Limited Availability',
                                'unavailable' => 'Not Available',
                            ])->default('available'),
                        Forms\Components\Textarea::make('availability_message')->rows(2)->maxLength(255),
                    ])->columns(1),

                Forms\Components\Section::make('Display Settings')
                    ->schema([
                        Forms\Components\Toggle::make('show_form')->label('Show Contact Form')->default(true),
                        Forms\Components\Toggle::make('is_active')->label('Active')->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('email')->copyable()->icon('heroicon-o-envelope'),
                Tables\Columns\TextColumn::make('phone')->icon('heroicon-o-phone'),
                Tables\Columns\TextColumn::make('availability_status')
                    ->badge()
                    ->colors(['success' => 'available', 'warning' => 'busy', 'danger' => 'unavailable']),
                Tables\Columns\IconColumn::make('show_form')->boolean()->label('Form'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->filters([])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListContactSettings::route('/'),
            'create' => Pages\CreateContactSetting::route('/create'),
            'edit'   => Pages\EditContactSetting::route('/{record}/edit'),
        ];
    }
}
