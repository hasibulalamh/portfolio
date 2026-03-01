<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactMessageResource\Pages;
use App\Models\ContactMessage;
use App\Mail\ClientProjectDetails;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox';
    protected static ?string $navigationLabel = 'Messages';
    protected static ?string $navigationGroup = 'Contact';
    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'unread')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Message Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')->disabled(),
                        Forms\Components\TextInput::make('email')->disabled(),
                        Forms\Components\TextInput::make('subject')->disabled(),
                        Forms\Components\Textarea::make('message')->disabled()->rows(8)->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Status & Metadata')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'unread'   => 'Unread',
                                'read'     => 'Read',
                                'replied'  => 'Replied',
                                'archived' => 'Archived',
                            ]),
                        Forms\Components\TextInput::make('ip_address')->label('IP Address')->disabled(),
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Received At')
                            ->content(fn ($record) => $record?->created_at?->format('M d, Y h:i A')),
                        Forms\Components\Placeholder::make('read_at')
                            ->label('Read At')
                            ->content(fn ($record) => $record?->read_at?->format('M d, Y h:i A') ?? 'Not read yet'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()->sortable()->weight('bold')
                    ->description(fn ($record) => $record->email),

                Tables\Columns\TextColumn::make('subject')
                    ->searchable()->limit(40)
                    ->description(fn ($record) => Str::limit($record->message, 60)),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'danger'  => 'unread',
                        'success' => 'read',
                        'info'    => 'replied',
                        'gray'    => 'archived',
                    ])->sortable(),

                Tables\Columns\IconColumn::make('project_details_sent')
                    ->boolean()
                    ->label('Reply Sent')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Received')->dateTime()->sortable()->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'unread'   => 'Unread',
                        'read'     => 'Read',
                        'replied'  => 'Replied',
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

                // ⭐ Main Reply Action
                Action::make('reply')
                    ->label('Send Reply')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->modalHeading(fn ($record) => '✉️ Reply to ' . $record->name)
                    ->modalWidth('2xl')
                    ->form([
                        Forms\Components\Section::make('📩 Client\'s Message')
                            ->schema([
                                Forms\Components\Placeholder::make('from')
                                    ->label('From')
                                    ->content(fn ($record) => $record->name . ' (' . $record->email . ')'),
                                Forms\Components\Placeholder::make('client_message')
                                    ->label('Message')
                                    ->content(fn ($record) => $record->message),
                            ])->collapsible(),

                        Forms\Components\Section::make('✍️ Your Personal Reply')
                            ->schema([
                                Forms\Components\Textarea::make('personal_message')
                                    ->label('Write your reply')
                                    ->placeholder("Hi [Name],\n\nThank you for reaching out! I've reviewed your requirements carefully.\n\n[Write your personal message here...]")
                                    ->rows(8)
                                    ->required()
                                    ->minLength(20)
                                    ->helperText('Service suggestions will be auto-added below based on the client\'s message.'),
                            ]),
                    ])
                    ->action(function ($record, array $data) {
                        if ($record->project_details_sent) {
                            Notification::make()
                                ->warning()
                                ->title('Already Sent!')
                                ->body('A reply was already sent to this client.')
                                ->send();
                            return;
                        }

                        try {
                            Mail::to($record->email)->send(
                                new ClientProjectDetails($record, $data['personal_message'])
                            );

                            $record->update([
                                'project_details_sent'    => true,
                                'project_details_sent_at' => now(),
                                'status'                  => 'replied',
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Reply Sent! 🚀')
                                ->body('Email sent to ' . $record->email)
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Failed to Send!')
                                ->body('Error: ' . $e->getMessage())
                                ->send();
                        }
                    })
                    ->modalSubmitActionLabel('📤 Send Reply'),

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
            ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListContactMessages::route('/'),
            'create' => Pages\CreateContactMessage::route('/create'),
            'edit'   => Pages\EditContactMessage::route('/{record}/edit'),
        ];
    }
}
