<?php

namespace App\Filament\Resources\Extras\Pages;

use App\Filament\Resources\Extras\ExtraResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExtra extends CreateRecord
{
    protected static string $resource = ExtraResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::hasPage('view') ? static::getResource()::getUrl('view', ['record' => $this->getRecord()]) : static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
