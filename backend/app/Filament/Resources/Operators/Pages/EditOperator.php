<?php

namespace App\Filament\Resources\Operators\Pages;

use App\Filament\Resources\Operators\OperatorResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditOperator extends EditRecord
{
    protected static string $resource = OperatorResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $u = $this->getRecord()->user;

        return $data + ['user_name' => $u?->name, 'user_phone' => $u?->phone, 'user_email' => $u?->email, 'user_avatar' => $u?->avatar_path];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        OperatorResource::syncUser($record, $data);
        $record->update(collect($data)->except(['user_name', 'user_phone', 'user_email', 'user_avatar', 'features'])->all());

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deactivate')->label('Deactivate')->color('danger')->icon('heroicon-o-user-minus')
                ->visible(fn () => $this->getRecord()->status === 'active')
                ->requiresConfirmation()->modalDescription('They will be signed out of the Crew app and removed from dispatch. Their history is kept.')
                ->action(function () {
                    $op = $this->getRecord();
                    $op->update(['status' => 'inactive', 'is_online' => false]);
                    $op->user->tokens()->delete();
                    $this->refreshFormData(['status']);
                }),
        ];
    }
}
