<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TestimonialResource\Pages;
use App\Models\Testimonial;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TestimonialResource extends Resource
{
    protected static ?string $model = Testimonial::class;
    protected static ?string $navigationIcon = 'heroicon-o-star';
    protected static ?string $navigationLabel = 'Testimonials';
    protected static ?string $navigationGroup = 'Portfolio';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Client Information')
                ->schema([
                    Forms\Components\TextInput::make('client_name')
                        ->required()
                        ->maxLength(255)
                        ->label('Client Name'),

                    Forms\Components\TextInput::make('client_role')
                        ->maxLength(255)
                        ->label('Role/Position')
                        ->placeholder('e.g., CEO, Project Manager'),

                    Forms\Components\TextInput::make('client_company')
                        ->maxLength(255)
                        ->label('Company')
                        ->placeholder('e.g., Tech Corp'),

                    Forms\Components\FileUpload::make('client_photo')
                        ->image()
                        ->imageEditor()
                        ->directory('testimonials')
                        ->maxSize(1024)
                        ->label('Client Photo'),
                ])->columns(2),

            Forms\Components\Section::make('Testimonial Content')
                ->schema([
                    Forms\Components\Textarea::make('content')
                        ->required()
                        ->rows(4)
                        ->maxLength(1000)
                        ->label('Review/Testimonial')
                        ->placeholder('What did the client say about your work?'),

                    Forms\Components\Select::make('rating')
                        ->required()
                        ->options([
                            1 => '⭐ 1 Star',
                            2 => '⭐⭐ 2 Stars',
                            3 => '⭐⭐⭐ 3 Stars',
                            4 => '⭐⭐⭐⭐ 4 Stars',
                            5 => '⭐⭐⭐⭐⭐ 5 Stars',
                        ])
                        ->default(5)
                        ->label('Rating'),

                    Forms\Components\TextInput::make('project_type')
                        ->maxLength(255)
                        ->label('Project Type')
                        ->placeholder('e.g., E-Commerce Website, Mobile App'),

                    Forms\Components\DatePicker::make('date')
                        ->label('Date')
                        ->default(now()),
                ])->columns(2),

            Forms\Components\Section::make('Settings')
                ->schema([
                    Forms\Components\TextInput::make('order')
                        ->numeric()
                        ->default(0)
                        ->label('Display Order'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('order')
            ->columns([
                Tables\Columns\ImageColumn::make('client_photo')
                    ->circular()
                    ->label('Photo'),

                Tables\Columns\TextColumn::make('client_name')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->client_company ?? $record->client_role),

                Tables\Columns\TextColumn::make('content')
                    ->limit(60)
                    ->color('gray'),

                Tables\Columns\TextColumn::make('rating')
                    ->formatStateUsing(fn ($state) => str_repeat('⭐', $state))
                    ->label('Rating'),

                Tables\Columns\TextColumn::make('project_type')
                    ->badge()
                    ->color('warning'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
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
            'index'  => Pages\ListTestimonials::route('/'),
            'create' => Pages\CreateTestimonial::route('/create'),
            'edit'   => Pages\EditTestimonial::route('/{record}/edit'),
        ];
    }
}
