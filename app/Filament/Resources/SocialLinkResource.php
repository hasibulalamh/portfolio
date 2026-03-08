<?php
namespace App\Filament\Resources;

use App\Filament\Resources\SocialLinkResource\Pages;
use App\Models\SocialLink;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SocialLinkResource extends Resource
{
    protected static ?string $model = SocialLink::class;
    protected static ?string $navigationIcon = 'heroicon-o-share';
    protected static ?string $navigationGroup = 'Portfolio';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Basic Information')
                ->schema([
                    Forms\Components\TextInput::make('platform')
                        ->required()
                        ->placeholder('e.g., GitHub, LinkedIn, Facebook'),

                    Forms\Components\TextInput::make('url')
                        ->required()
                        ->url()
                        ->placeholder('https://github.com/username'),

                    Forms\Components\TextInput::make('icon')
                        ->placeholder('e.g., mdi:github, mdi:linkedin')
                        ->helperText('Browse icons at iconify.design'),

                    Forms\Components\TextInput::make('color')
                        ->placeholder('e.g., #1DA1F2')
                        ->helperText('Hex color for icon'),

                    Forms\Components\TextInput::make('order')
                        ->numeric()
                        ->default(0),
                ])->columns(2),

            Forms\Components\Section::make('Display Settings')
                ->schema([
                    Forms\Components\Toggle::make('show_in_hero')
                        ->label('Show in Hero')
                        ->default(true),

                    Forms\Components\Toggle::make('show_in_contact')
                        ->label('Show in Contact')
                        ->default(true),

                    Forms\Components\Toggle::make('show_in_footer')
                        ->label('Show in Footer')
                        ->default(true),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ])->columns(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('order')
            ->columns([
                Tables\Columns\TextColumn::make('order')->sortable()->width(60),
                Tables\Columns\TextColumn::make('platform')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('url')->limit(40)->color('gray'),
                Tables\Columns\TextColumn::make('icon')->color('gray'),
                Tables\Columns\IconColumn::make('show_in_hero')->boolean()->label('Hero'),
                Tables\Columns\IconColumn::make('show_in_contact')->boolean()->label('Contact'),
                Tables\Columns\IconColumn::make('show_in_footer')->boolean()->label('Footer'),
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
            'index'  => Pages\ListSocialLinks::route('/'),
            'create' => Pages\CreateSocialLink::route('/create'),
            'edit'   => Pages\EditSocialLink::route('/{record}/edit'),
        ];
    }
}
