<?php

namespace App\Filament\Resources\OnCallShifts\Pages;

use App\Filament\Resources\OnCallShifts\OnCallShiftResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOnCallShift extends CreateRecord
{
    protected static string $resource = OnCallShiftResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::hasPage('view') ? static::getResource()::getUrl('view', ['record' => $this->getRecord()]) : static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
