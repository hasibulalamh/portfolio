<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HeroSettingResource\Pages;
use App\Models\HeroSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HeroSettingResource extends Resource
{
    protected static ?string $model = HeroSetting::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Basic Information')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->label('Your Name')
                        ->placeholder('e.g., Hasibul Alam'),

                    Forms\Components\TextInput::make('tagline')
                        ->required()
                        ->maxLength(255)
                        ->label('Tagline/Title')
                        ->placeholder('e.g., Full-Stack Web Developer'),

                    Forms\Components\Textarea::make('description')
                        ->maxLength(500)
                        ->rows(3)
                        ->label('Short Description')
                        ->placeholder('Brief introduction about yourself...')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Profile Photo')
                ->schema([
                    Forms\Components\FileUpload::make('photo')
                        ->image()
                        ->imageEditor()
                        ->maxSize(2048)
                        ->directory('hero')
                        ->label('Profile Photo')
                        ->helperText('Upload your profile photo (max 2MB)'),

                    Forms\Components\Toggle::make('show_photo')
                        ->label('Show Photo')
                        ->default(true),
                ])
                ->columns(2),

            Forms\Components\Section::make('Call to Action Buttons')
                ->schema([
                    Forms\Components\TextInput::make('primary_cta_text')
                        ->label('Primary Button Text')
                        ->default('Hire Me'),

                    Forms\Components\TextInput::make('primary_cta_url')
                        ->label('Primary Button URL')
                        ->default('#contact')
                        ->placeholder('#contact or /contact'),

                    Forms\Components\TextInput::make('secondary_cta_text')
                        ->label('Secondary Button Text')
                        ->default('View Resume'),

                    Forms\Components\FileUpload::make('resume_url')
                        ->label('Resume PDF')
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(5120)
                        ->directory('resumes')
                        ->helperText('Upload your resume PDF (max 5MB)')
                        ->downloadable()
                        ->openable()
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Availability Badge')
                ->schema([
                    Forms\Components\Toggle::make('show_availability')
                        ->label('Show Availability Badge')
                        ->default(true)
                        ->helperText('Show availability status on hero section'),

                    Forms\Components\Toggle::make('is_available')
                        ->label('Currently Available')
                        ->default(true)
                        ->helperText('Green = Available, Red = Not Available'),

                    Forms\Components\TextInput::make('availability_text')
                        ->label('Availability Text')
                        ->default('Available for hire')
                        ->placeholder('e.g., Available for freelance')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Settings')
                ->schema([
                    Forms\Components\Toggle::make('show_social_links')
                        ->label('Show Social Links')
                        ->default(true)
                        ->helperText('Display social media icons below CTA buttons'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('Only one hero setting can be active at a time'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('photo')
                    ->circular()
                    ->defaultImageUrl(url('/images/default-avatar.png')),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('tagline')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\IconColumn::make('is_available')
                    ->boolean()
                    ->label('Available'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable()
                    ->label('Active'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListHeroSettings::route('/'),
            'create' => Pages\CreateHeroSetting::route('/create'),
            'edit'   => Pages\EditHeroSetting::route('/{record}/edit'),
        ];
    }
}
