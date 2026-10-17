<?php

namespace App\Filament\Resources\PublicHolidays\Pages;

use App\Filament\Resources\PublicHolidays\PublicHolidayResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePublicHoliday extends CreateRecord
{
    protected static string $resource = PublicHolidayResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::hasPage('view') ? static::getResource()::getUrl('view', ['record' => $this->getRecord()]) : static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
