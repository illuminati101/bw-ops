<?php

namespace App\Filament\Resources\Regions\Pages;

use App\Filament\Resources\Regions\RegionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRegion extends CreateRecord
{
    protected static string $resource = RegionResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::hasPage('view') ? static::getResource()::getUrl('view', ['record' => $this->getRecord()]) : static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
