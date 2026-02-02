<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FooterItemResource\Pages;
use App\Filament\Resources\FooterItemResource\RelationManagers;
use App\Models\FooterItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FooterItemResource extends Resource
{
    protected static ?string $model = FooterItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

   public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\Select::make('type')
                ->required()
                ->options([
                    'link' => 'Link',
                    'social' => 'Social Media',
                    'info' => 'Information',
                ])
                ->default('link')
                ->reactive()
                ->label('Item Type'),

            Forms\Components\Select::make('category')
                ->required()
                ->options([
                    'quick_links' => 'Quick Links',
                    'social' => 'Social Media',
                    'contact' => 'Contact Info',
                ])
                ->default('quick_links')
                ->label('Category/Column'),

            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->label('Display Name')
                ->placeholder('e.g., Home, GitHub, Email'),

            Forms\Components\TextInput::make('value')
                ->required()
                ->maxLength(255)
                ->label('Link/Value')
                ->placeholder('e.g., /, https://github.com/..., email@example.com')
                ->helperText('For links: URL | For social: profile URL | For info: email/phone'),

            Forms\Components\Textarea::make('icon')
                ->maxLength(65535)
                ->label('SVG Icon Path (Optional)')
                ->placeholder('SVG path data for social icons')
                ->rows(3)
                ->helperText('For social media icons - paste SVG path'),

            Forms\Components\Toggle::make('is_active')
                ->label('Show in Footer')
                ->default(false)
                ->helperText('Toggle to show/hide in footer')
                ->columnSpanFull(),

            Forms\Components\TextInput::make('order')
                ->numeric()
                ->default(0)
                ->label('Display Order')
                ->helperText('Lower numbers appear first'),
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

            Tables\Columns\TextColumn::make('type')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'link' => 'info',
                    'social' => 'success',
                    'info' => 'warning',
                    default => 'gray',
                }),

            Tables\Columns\TextColumn::make('category')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'quick_links' => 'primary',
                    'social' => 'success',
                    'contact' => 'warning',
                    default => 'gray',
                }),

            Tables\Columns\TextColumn::make('value')
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

            Tables\Filters\SelectFilter::make('type')
                ->options([
                    'link' => 'Link',
                    'social' => 'Social Media',
                    'info' => 'Information',
                ]),

            Tables\Filters\SelectFilter::make('category')
                ->options([
                    'quick_links' => 'Quick Links',
                    'social' => 'Social Media',
                    'contact' => 'Contact Info',
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
            'index' => Pages\ListFooterItems::route('/'),
            'create' => Pages\CreateFooterItem::route('/create'),
            'edit' => Pages\EditFooterItem::route('/{record}/edit'),
        ];
    }
}
