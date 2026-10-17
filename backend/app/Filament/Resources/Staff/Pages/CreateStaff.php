<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Resources\Staff\StaffResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['type' => 'staff', 'email_verified_at' => now()];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::hasPage('view') ? static::getResource()::getUrl('view', ['record' => $this->getRecord()]) : static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
