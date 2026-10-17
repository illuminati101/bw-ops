<?php

namespace App\Filament\Resources\PublicHolidays\Pages;

use App\Filament\Resources\PublicHolidays\PublicHolidayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPublicHolidays extends ListRecords
{
    protected static string $resource = PublicHolidayResource::class;

    protected function getHeaderActions(): array
    {
        return PublicHolidayResource::canCreate() ? [CreateAction::make()] : [];
    }
}
