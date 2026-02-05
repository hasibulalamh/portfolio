<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AboutSettingResource\Pages;
use App\Filament\Resources\AboutSettingResource\RelationManagers;
use App\Models\AboutSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AboutSettingResource extends Resource
{
    protected static ?string $model = AboutSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

   public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\Section::make('Basic Information')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->default('About Me')
                        ->maxLength(255)
                        ->label('Section Title'),

                    Forms\Components\TextInput::make('subtitle')
                        ->maxLength(255)
                        ->label('Subtitle (Optional)')
                        ->placeholder('e.g., Who I Am'),

                    Forms\Components\Textarea::make('bio')
                        ->required()
                        ->rows(6)
                        ->label('Biography')
                        ->placeholder('Write your professional bio here...'),
                ])
                ->columns(1),

            Forms\Components\Section::make('Profile Image')
                ->schema([
                    Forms\Components\FileUpload::make('image')
                        ->image()
                        ->imageEditor()
                        ->maxSize(2048)
                        ->directory('about')
                        ->label('About Image')
                        ->helperText('Upload a professional photo (max 2MB)'),

                    Forms\Components\Toggle::make('show_image')
                        ->label('Show Image')
                        ->default(true),
                ])
                ->columns(2),

            Forms\Components\Section::make('Statistics')
                ->schema([
                    Forms\Components\TextInput::make('years_experience')
                        ->numeric()
                        ->default(0)
                        ->label('Years of Experience')
                        ->suffix('years'),

                    Forms\Components\TextInput::make('projects_completed')
                        ->numeric()
                        ->default(0)
                        ->label('Projects Completed')
                        ->suffix('projects'),

                    Forms\Components\TextInput::make('happy_clients')
                        ->numeric()
                        ->default(0)
                        ->label('Happy Clients')
                        ->suffix('clients'),

                    Forms\Components\TextInput::make('technologies_count')
                        ->numeric()
                        ->default(0)
                        ->label('Technologies Mastered')
                        ->suffix('technologies'),

                    Forms\Components\Toggle::make('show_stats')
                        ->label('Show Statistics')
                        ->default(true)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Key Highlights')
                ->schema([
                    Forms\Components\Repeater::make('highlights')
                        ->schema([
                            Forms\Components\TextInput::make('text')
                                ->required()
                                ->label('Highlight')
                                ->placeholder('e.g., 5+ years building scalable web applications'),
                        ])
                        ->label('Achievements/Highlights')
                        ->defaultItems(0)
                        ->addActionLabel('Add Highlight')
                        ->collapsible()
                        ->helperText('Add key achievements or highlights about your career'),
                ])
                ->columns(1),

            Forms\Components\Section::make('Settings')
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('Only one about setting can be active at a time'),
                ])
                ->columns(1),
        ]);
}

  public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\ImageColumn::make('image')
                ->circular(),

            Tables\Columns\TextColumn::make('title')
                ->searchable()
                ->sortable()
                ->weight('bold'),

            Tables\Columns\TextColumn::make('bio')
                ->limit(50)
                ->searchable(),

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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAboutSettings::route('/'),
            'create' => Pages\CreateAboutSetting::route('/create'),
            'edit' => Pages\EditAboutSetting::route('/{record}/edit'),
        ];
    }
}
