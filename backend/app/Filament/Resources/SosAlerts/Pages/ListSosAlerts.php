<?php

namespace App\Filament\Resources\SosAlerts\Pages;

use App\Filament\Resources\SosAlerts\SosAlertResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSosAlerts extends ListRecords
{
    protected static string $resource = SosAlertResource::class;

    protected function getHeaderActions(): array
    {
        return SosAlertResource::canCreate() ? [CreateAction::make()] : [];
    }
}
