<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SkillResource\Pages;
use App\Filament\Resources\SkillResource\RelationManagers;
use App\Models\Skill;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SkillResource extends Resource
{
    protected static ?string $model = Skill::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

   public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\Section::make('Skill Information')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->label('Skill Name')
                        ->placeholder('e.g., Laravel, Vue.js, MySQL'),

                    Forms\Components\Select::make('category')
                        ->required()
                        ->options([
                            'frontend' => 'Frontend',
                            'backend' => 'Backend',
                            'database' => 'Database',
                            'tools' => 'Tools & Others',
                        ])
                        ->label('Category'),

                    Forms\Components\TextInput::make('icon')
                        ->required()
                        ->label('Icon Name (Iconify)')
                        ->placeholder('e.g., mdi:laravel, logos:vue')
                        ->helperText('Find icons at: https://icon-sets.iconify.design/'),

                    Forms\Components\ColorPicker::make('color')
                        ->label('Brand Color (Optional)')
                        ->helperText('Leave empty to use default gradient'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Proficiency & Display')
                ->schema([
                    Forms\Components\TextInput::make('proficiency')
                        ->numeric()
                        ->required()
                        ->default(80)
                        ->minValue(0)
                        ->maxValue(100)
                        ->suffix('%')
                        ->label('Proficiency Level')
                        ->helperText('Your skill level (0-100%)'),

                    Forms\Components\TextInput::make('order')
                        ->numeric()
                        ->default(0)
                        ->label('Display Order')
                        ->helperText('Lower numbers appear first'),

                    Forms\Components\Toggle::make('is_featured')
                        ->label('Featured Skill')
                        ->default(false)
                        ->helperText('Show in featured/highlight section'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('Show/hide this skill'),
                ])
                ->columns(2),
        ]);
}

  public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('name')
                ->searchable()
                ->sortable()
                ->weight('bold'),

            Tables\Columns\TextColumn::make('category')
                ->badge()
                ->colors([
                    'primary' => 'frontend',
                    'success' => 'backend',
                    'warning' => 'database',
                    'info' => 'tools',
                ])
                ->searchable(),

            Tables\Columns\TextColumn::make('icon')
                ->limit(30)
                ->searchable(),

            Tables\Columns\TextColumn::make('proficiency')
                ->suffix('%')
                ->sortable(),

            Tables\Columns\IconColumn::make('is_featured')
                ->boolean()
                ->label('Featured'),

            Tables\Columns\IconColumn::make('is_active')
                ->boolean()
                ->sortable()
                ->label('Active'),

            Tables\Columns\TextColumn::make('order')
                ->sortable(),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('category')
                ->options([
                    'frontend' => 'Frontend',
                    'backend' => 'Backend',
                    'database' => 'Database',
                    'tools' => 'Tools & Others',
                ]),

            Tables\Filters\TernaryFilter::make('is_active')
                ->label('Active Status'),

            Tables\Filters\TernaryFilter::make('is_featured')
                ->label('Featured'),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListSkills::route('/'),
            'create' => Pages\CreateSkill::route('/create'),
            'edit' => Pages\EditSkill::route('/{record}/edit'),
        ];
    }
}
