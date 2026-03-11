<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Models\Project;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Basic Information')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) =>
                            $operation === 'create' ? $set('slug', \Illuminate\Support\Str::slug($state)) : null
                        ),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->helperText('Auto-generated from title'),

                    Forms\Components\Select::make('category')
                        ->required()
                        ->options([
                            'web' => 'Web Application',
                            'mobile' => 'Mobile App',
                            'api' => 'API/Backend',
                            'ecommerce' => 'E-Commerce',
                            'portfolio' => 'Portfolio/Website',
                            'other' => 'Other',
                        ]),

                    Forms\Components\Textarea::make('description')
                        ->required()
                        ->maxLength(500)
                        ->rows(3)
                        ->label('Short Description')
                        ->helperText('Brief overview (shown on cards)')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Detailed Information')
                ->schema([
                    Forms\Components\RichEditor::make('long_description')
                        ->label('Detailed Description')
                        ->helperText('Full project description with features'),
                ])
                ->collapsible(),

            Forms\Components\Section::make('Case Study')
                ->schema([
                    Forms\Components\Textarea::make('problem_description')
                        ->label('Problem')
                        ->rows(4)
                        ->helperText('What problem did this project solve?')
                        ->columnSpanFull(),

                    Forms\Components\FileUpload::make('problem_image')
                        ->image()
                        ->imageEditor()
                        ->maxSize(2048)
                        ->directory('projects/case-study')
                        ->label('Problem Image')
                        ->helperText('Image showing the problem'),

                    Forms\Components\Textarea::make('solution_description')
                        ->label('Solution')
                        ->rows(4)
                        ->helperText('How did you solve it?'),
                ])
                ->columns(2)
                ->collapsible(),

            Forms\Components\Section::make('Technologies & Stack')
                ->schema([
                    Forms\Components\TagsInput::make('technologies')
                        ->placeholder('Add technology (press Enter)')
                        ->helperText('e.g., Laravel, Vue.js, MySQL, Tailwind CSS'),
                ]),

            Forms\Components\Section::make('Media')
                ->schema([
                    Forms\Components\FileUpload::make('featured_image')
                        ->image()
                        ->imageEditor()
                        ->maxSize(2048)
                        ->directory('projects')
                        ->label('Featured Image'),

                    Forms\Components\FileUpload::make('gallery_images')
                        ->image()
                        ->multiple()
                        ->maxFiles(8)
                        ->maxSize(2048)
                        ->directory('projects/gallery')
                        ->label('Gallery Images')
                        ->helperText('Additional screenshots (max 8)'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Links & Details')
                ->schema([
                    Forms\Components\TextInput::make('live_url')
                        ->url()
                        ->label('Live Demo URL')
                        ->placeholder('https://example.com'),

                    Forms\Components\TextInput::make('github_url')
                        ->url()
                        ->label('GitHub Repository')
                        ->placeholder('https://github.com/username/repo'),

                    Forms\Components\TextInput::make('client_name')
                        ->maxLength(255)
                        ->label('Client Name (Optional)'),

                    Forms\Components\DatePicker::make('completed_at')
                        ->label('Completion Date'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Display Settings')
                ->schema([
                    Forms\Components\Toggle::make('is_featured')
                        ->label('Featured Project')
                        ->default(false),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),

                    Forms\Components\TextInput::make('order')
                        ->numeric()
                        ->default(0)
                        ->label('Display Order'),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('featured_image')
                    ->label('Image'),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->colors([
                        'primary' => 'web',
                        'success' => 'mobile',
                        'warning' => 'api',
                        'danger' => 'ecommerce',
                        'info' => 'portfolio',
                    ]),

                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean()
                    ->label('Featured'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                Tables\Columns\TextColumn::make('completed_at')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('order')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'web' => 'Web Application',
                        'mobile' => 'Mobile App',
                        'api' => 'API/Backend',
                        'ecommerce' => 'E-Commerce',
                        'portfolio' => 'Portfolio/Website',
                        'other' => 'Other',
                    ]),
                Tables\Filters\TernaryFilter::make('is_featured')->label('Featured'),
                Tables\Filters\TernaryFilter::make('is_active')->label('Active Status'),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit'   => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}
