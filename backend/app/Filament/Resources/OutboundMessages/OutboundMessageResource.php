<?php

namespace App\Filament\Resources\OutboundMessages;

use App\Models\OutboundMessage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class OutboundMessageResource extends Resource
{
    protected static ?string $model = OutboundMessage::class;

    protected static ?string $slug = 'message-log';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Message log';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('messages.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->poll('10s')->columns([
            TextColumn::make('created_at')->dateTime('D j M H:i:s')->label('When'),
            TextColumn::make('channel')->badge()->color(fn ($state) => ['sms' => 'primary', 'email' => 'info', 'push' => 'gray', 'voice' => 'warning'][$state] ?? 'gray'),
            TextColumn::make('to')->searchable(),
            TextColumn::make('subject')->placeholder('—')->toggleable(),
            TextColumn::make('body')->wrap()->limit(140)
                ->description(fn ($record) => $record->driver === 'log' && $record->channel === 'sms' ? 'Test mode: not actually sent (connect Twilio to send).' : null),
            TextColumn::make('status')->badge()->color(fn ($state) => ['sent' => 'success', 'failed' => 'danger', 'logged' => 'warning'][$state] ?? 'gray')->tooltip(fn ($record) => $record->error),
        ])->filters([
            SelectFilter::make('channel')->options(['sms' => 'SMS', 'email' => 'Email', 'push' => 'Push', 'voice' => 'Voice']),
            SelectFilter::make('status')->options(['sent' => 'Sent', 'logged' => 'Test mode', 'failed' => 'Failed']),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListOutboundMessages::route('/')];
    }
}
