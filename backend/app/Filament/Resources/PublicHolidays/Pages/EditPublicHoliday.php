<?php

namespace App\Filament\Resources\PublicHolidays\Pages;

use App\Filament\Resources\PublicHolidays\PublicHolidayResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPublicHoliday extends EditRecord
{
    protected static string $resource = PublicHolidayResource::class;

    protected function getHeaderActions(): array
    {
        return method_exists(PublicHolidayResource::class, 'editHeaderActions') ? PublicHolidayResource::editHeaderActions($this) : [DeleteAction::make()];
    }
}
