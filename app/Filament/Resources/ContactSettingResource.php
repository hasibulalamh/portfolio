<?php


namespace App\Filament\Resources;

use App\Filament\Resources\ContactMessageResource\Pages;
use App\Models\ContactMessage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox';
    protected static ?string $navigationLabel = 'Messages';
    protected static ?string $navigationGroup = 'Contact';
    protected static ?int $navigationSort = 1;

    // Show unread count badge
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::unread()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::unread()->count() > 0 ? 'danger' : 'success';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
              Forms\Components\Section::make('Message Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->disabled(),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->disabled()
                            ->copyable()
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('sendEmail')
                                    ->icon('heroicon-o-envelope')
                                    ->url(fn ($record) => "mailto:{$record->email}")
                                    ->openUrlInNewTab()
                            ),

                        Forms\Components\TextInput::make('subject')
                            ->maxLength(255)
                            ->disabled(),

                        Forms\Components\Textarea::make('message')
                            ->required()
                            ->rows(8)
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Status & Metadata')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->required()
                            ->options([
                                'unread' => 'Unread',
                                'read' => 'Read',
                                'replied' => 'Replied',
                                'archived' => 'Archived',
                            ]),

                        Forms\Components\TextInput::make('ip_address')
                            ->label('IP Address')
                            ->disabled(),

                        Forms\Components\Placeholder::make('created_at')
                            ->label('Received At')
                            ->content(fn ($record) => $record?->created_at?->format('M d, Y h:i A')),

                        Forms\Components\Placeholder::make('read_at')
                            ->label('Read At')
                            ->content(fn ($record) => $record?->read_at?->format('M d, Y h:i A') ?? 'Not read yet'),
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
                    ->weight('bold')
                    ->description(fn ($record) => $record->email),

                Tables\Columns\TextColumn::make('subject')
                    ->searchable()
                    ->limit(40)
                    ->description(fn ($record) => Str::limit($record->message, 60)),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'danger' => 'unread',
                        'success' => 'read',
                        'info' => 'replied',
                        'gray' => 'archived',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'unread' => 'Unread',
                        'read' => 'Read',
                        'replied' => 'Replied',
                        'archived' => 'Archived',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('markAsRead')
                    ->label('Mark Read')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'unread')
                    ->action(fn ($record) => $record->markAsRead()),

                Tables\Actions\Action::make('reply')
                    ->label('Reply')
                    ->icon('heroicon-o-envelope')
                    ->color('info')
                    ->url(fn ($record) => "mailto:{$record->email}?subject=Re: {$record->subject}")
                    ->openUrlInNewTab(),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('markAsRead')
                        ->label('Mark as Read')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each->markAsRead()),

                    Tables\Actions\BulkAction::make('markAsArchived')
                        ->label('Archive')
                        ->icon('heroicon-o-archive-box')
                        ->action(fn ($records) => $records->each->update(['status' => 'archived'])),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListContactMessages::route('/'),
            'create' => Pages\CreateContactMessage::route('/create'),
            'edit' => Pages\EditContactMessage::route('/{record}/edit'),
        ];
    }
}
