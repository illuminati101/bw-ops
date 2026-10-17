<?php

namespace App\Filament\Resources\Bookings\RelationManagers;

use App\Events\ChatMessageSent;
use App\Support\Realtime;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $title = 'Chat';

    public function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->poll('10s')->columns([
            TextColumn::make('created_at')->label('Time')->dateTime('D H:i'),
            TextColumn::make('sender_role')->label('From')->badge()->color(fn ($state) => ['client' => 'gray', 'operator' => 'info', 'staff' => 'primary'][$state] ?? 'gray'),
            TextColumn::make('body')->wrap(),
        ])->headerActions([
            Action::make('send')->label('Message as control room')->icon('heroicon-o-chat-bubble-left-right')
                ->schema([Textarea::make('body')->required()->rows(3)])
                ->action(function (array $data) {
                    $m = $this->getOwnerRecord()->messages()->create(['sender_id' => auth()->id(), 'sender_role' => 'staff', 'body' => $data['body']]);
                    Realtime::send(new ChatMessageSent($m));
                }),
        ]);
    }
}
