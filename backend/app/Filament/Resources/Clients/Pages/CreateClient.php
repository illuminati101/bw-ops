<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/** Staff can open an account for a client who phones in (they verify by OTP on first app login). */
class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $profile = $data['clientProfile'] ?? [];
        unset($data['clientProfile']);
        $user = static::getModel()::create($data + ['type' => 'client']);
        $user->clientProfile()->create($profile + ['verification_status' => 'unverified']);

        return $user;
    }

    protected function getRedirectUrl(): string
    {
        return ClientResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
