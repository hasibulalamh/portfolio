<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExperienceResource\Pages;
use App\Filament\Resources\ExperienceResource\RelationManagers;
use App\Models\Experience;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ExperienceResource extends Resource
{
    protected static ?string $model = Experience::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

  public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\Section::make('Basic Information')
                ->schema([
                    Forms\Components\Select::make('type')
                        ->required()
                        ->options([
                            'work' => 'Work Experience',
                            'education' => 'Education',
                            'certification' => 'Certification',
                        ])
                        ->default('work')
                        ->live(),

                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->label('Job Title / Degree')
                        ->placeholder('e.g., Senior Full-Stack Developer'),

                    Forms\Components\TextInput::make('company')
                        ->required()
                        ->maxLength(255)
                        ->label('Company / Institution')
                        ->placeholder('e.g., Tech Company Inc.'),

                    Forms\Components\TextInput::make('location')
                        ->maxLength(255)
                        ->placeholder('e.g., Dhaka, Bangladesh'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Timeline')
                ->schema([
                    Forms\Components\DatePicker::make('start_date')
                        ->required()
                        ->label('Start Date'),

                    Forms\Components\DatePicker::make('end_date')
                        ->label('End Date')
                        ->disabled(fn (Forms\Get $get) => $get('is_current')),

                    Forms\Components\Toggle::make('is_current')
                        ->label('Currently Working Here')
                        ->reactive()
                        ->afterStateUpdated(fn ($state, Forms\Set $set) =>
                            $state ? $set('end_date', null) : null
                        ),
                ])
                ->columns(3),

            Forms\Components\Section::make('Details')
                ->schema([
                    Forms\Components\FileUpload::make('company_logo')
                        ->image()
                        ->maxSize(1024)
                        ->directory('companies')
                        ->label('Company/Institution Logo'),

                    Forms\Components\TextInput::make('website_url')
                        ->url()
                        ->label('Company Website')
                        ->placeholder('https://example.com'),

                    Forms\Components\Textarea::make('description')
                        ->maxLength(1000)
                        ->rows(3)
                        ->label('Brief Description'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Responsibilities & Achievements')
                ->schema([
                    Forms\Components\TagsInput::make('responsibilities')
                        ->placeholder('Add responsibility (press Enter)')
                        ->helperText('Key responsibilities in this role'),

                    Forms\Components\TagsInput::make('achievements')
                        ->placeholder('Add achievement (press Enter)')
                        ->helperText('Major accomplishments'),

                    Forms\Components\TagsInput::make('technologies')
                        ->placeholder('Add technology (press Enter)')
                        ->helperText('Technologies/tools used'),
                ])
                ->columns(1),

            Forms\Components\Section::make('Display Settings')
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),

                    Forms\Components\TextInput::make('order')
                        ->numeric()
                        ->default(0)
                        ->label('Display Order')
                        ->helperText('Lower numbers appear first'),
                ])
                ->columns(2),
        ]);
}

   public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\ImageColumn::make('company_logo')
                ->circular()
                ->label('Logo'),

            Tables\Columns\TextColumn::make('title')
                ->searchable()
                ->sortable()
                ->weight('bold'),

            Tables\Columns\TextColumn::make('company')
                ->searchable()
                ->sortable(),

            Tables\Columns\TextColumn::make('type')
                ->badge()
                ->colors([
                    'primary' => 'work',
                    'success' => 'education',
                    'warning' => 'certification',
                ]),

            Tables\Columns\TextColumn::make('formatted_date_range')
                ->label('Duration')
                ->sortable(),

            Tables\Columns\IconColumn::make('is_current')
                ->boolean()
                ->label('Current'),

            Tables\Columns\IconColumn::make('is_active')
                ->boolean()
                ->label('Active'),

            Tables\Columns\TextColumn::make('order')
                ->sortable(),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('type')
                ->options([
                    'work' => 'Work Experience',
                    'education' => 'Education',
                    'certification' => 'Certification',
                ]),

            Tables\Filters\TernaryFilter::make('is_current')
                ->label('Current Position'),

            Tables\Filters\TernaryFilter::make('is_active')
                ->label('Active Status'),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ])
        ->defaultSort('order');
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
            'index' => Pages\ListExperiences::route('/'),
            'create' => Pages\CreateExperience::route('/create'),
            'edit' => Pages\EditExperience::route('/{record}/edit'),
        ];
    }
}
