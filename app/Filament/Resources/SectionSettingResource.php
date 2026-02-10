<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SectionSettingResource\Pages;
use App\Filament\Resources\SectionSettingResource\RelationManagers;
use App\Models\SectionSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SectionSettingResource extends Resource
{
    protected static ?string $model = SectionSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-view-columns';
    protected static ?string $navigationLabel = 'Homepage Sections';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 99;

   public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\Section::make('Section Information')
                ->schema([
                    Forms\Components\TextInput::make('section_name')
                        ->required()
                        ->label('Section Name')
                        ->disabled()
                        ->helperText('Auto-generated from component'),

                    Forms\Components\TextInput::make('model_name')
                        ->required()
                        ->label('Model Name')
                        ->disabled()
                        ->helperText('Laravel model from config/sections.php'),

                    Forms\Components\TextInput::make('component_name')
                        ->required()
                        ->label('Vue Component')
                        ->disabled()
                        ->helperText('Component in resources/js/Components/Home/'),
                ])
                ->columns(3),

            Forms\Components\Section::make('Display Settings')
                ->schema([
                    Forms\Components\Toggle::make('is_enabled')
                        ->label('Show on Homepage')
                        ->default(true)
                        ->helperText('Toggle to show/hide this section')
                        ->inline(false),

                    Forms\Components\TextInput::make('order')
                        ->numeric()
                        ->required()
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
            Tables\Columns\TextColumn::make('section_name')
                ->label('Section')
                ->searchable()
                ->sortable()
                ->weight('bold'),

            Tables\Columns\TextColumn::make('model_name')
                ->label('Model')
                ->badge()
                ->color('info'),

            Tables\Columns\ToggleColumn::make('is_enabled')
                ->label('Enabled')
                ->sortable()
                ->onColor('success')
                ->offColor('danger'),

            Tables\Columns\TextColumn::make('order')
                ->label('Order')
                ->sortable(),

            Tables\Columns\TextColumn::make('data_status')
                ->label('Has Data')
                ->state(function ($record) {
                    $modelClass = "App\\Models\\{$record->model_name}";

                    if (!class_exists($modelClass)) {
                        return 'Model Not Found';
                    }

                    try {
                        if (str_ends_with($record->model_name, 'Setting')) {
                            $count = $modelClass::where('is_active', true)->count();
                        } else {
                            $count = $modelClass::where('is_active', true)->count();
                        }

                        return $count > 0 ? "✓ {$count} record(s)" : '✗ No data';
                    } catch (\Exception $e) {
                        return 'Error';
                    }
                })
                ->badge()
                ->color(fn (string $state): string => str_contains($state, '✓') ? 'success' : 'danger'),
        ])
        ->filters([
            Tables\Filters\TernaryFilter::make('is_enabled')
                ->label('Enabled Status'),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
        ])
        ->bulkActions([])
        ->reorderable('order')
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
            'index' => Pages\ListSectionSettings::route('/'),
            'create' => Pages\CreateSectionSetting::route('/create'),
            'edit' => Pages\EditSectionSetting::route('/{record}/edit'),
        ];
    }
}
