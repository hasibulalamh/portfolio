<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NavbarItemResource\Pages;
use App\Filament\Resources\NavbarItemResource\RelationManagers;
use App\Models\NavbarItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class NavbarItemResource extends Resource
{
    protected static ?string $model = NavbarItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

   public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->label('Menu Name'),

            Forms\Components\TextInput::make('href')
                ->required()
                ->maxLength(255)
                ->label('Link/URL')
                ->placeholder('/projects or #projects'),

            Forms\Components\Select::make('section_type')
                ->label('Section Type')
                ->options([
                    'home' => 'Home',
                    'projects' => 'Projects',
                    'skills' => 'Skills',
                    'experience' => 'Experience',
                    'education' => 'Education',
                    'blog' => 'Blog',
                    'testimonials' => 'Testimonials',
                    'contact' => 'Contact',
                ])
                ->searchable()
                ->helperText('Which section does this menu link to?'),

            Forms\Components\TextInput::make('icon')
                ->maxLength(255)
                ->label('Icon (Optional)')
                ->placeholder('heroicon name'),

            Forms\Components\Toggle::make('is_active')
                ->label('Show in Navbar')
                ->default(false)
                ->helperText('Toggle to show/hide in frontend navbar')
                ->columnSpanFull(),

            Forms\Components\TextInput::make('order')
                ->numeric()
                ->default(0)
                ->label('Display Order')
                ->helperText('Lower numbers appear first (e.g., 1, 2, 3...)'),
        ]);
}

 public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('order')
                ->sortable()
                ->label('#'),

            Tables\Columns\TextColumn::make('name')
                ->searchable()
                ->sortable()
                ->weight('bold'),

            Tables\Columns\TextColumn::make('section_type')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'home' => 'gray',
                    'projects' => 'success',
                    'skills' => 'info',
                    'experience' => 'warning',
                    'blog' => 'danger',
                    default => 'secondary',
                })
                ->sortable(),

            Tables\Columns\TextColumn::make('href')
                ->searchable()
                ->limit(30),

            Tables\Columns\IconColumn::make('is_active')
                ->boolean()
                ->sortable()
                ->label('Visible'),

            Tables\Columns\TextColumn::make('created_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
        ->defaultSort('order', 'asc')
        ->filters([
            Tables\Filters\TernaryFilter::make('is_active')
                ->label('Visibility')
                ->placeholder('All items')
                ->trueLabel('Visible only')
                ->falseLabel('Hidden only'),

            Tables\Filters\SelectFilter::make('section_type')
                ->options([
                    'home' => 'Home',
                    'projects' => 'Projects',
                    'skills' => 'Skills',
                    'experience' => 'Experience',
                    'blog' => 'Blog',
                    'contact' => 'Contact',
                ]),
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNavbarItems::route('/'),
            'create' => Pages\CreateNavbarItem::route('/create'),
            'edit' => Pages\EditNavbarItem::route('/{record}/edit'),
        ];
    }
}
