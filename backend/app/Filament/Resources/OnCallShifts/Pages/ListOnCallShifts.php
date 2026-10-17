<?php

namespace App\Filament\Resources\OnCallShifts\Pages;

use App\Filament\Resources\OnCallShifts\OnCallShiftResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOnCallShifts extends ListRecords
{
    protected static string $resource = OnCallShiftResource::class;

    protected function getHeaderActions(): array
    {
        return OnCallShiftResource::canCreate() ? [CreateAction::make()] : [];
    }
}
