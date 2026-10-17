<?php

namespace App\Filament\Resources\OnCallShifts\Pages;

use App\Filament\Resources\OnCallShifts\OnCallShiftResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOnCallShift extends EditRecord
{
    protected static string $resource = OnCallShiftResource::class;

    protected function getHeaderActions(): array
    {
        return method_exists(OnCallShiftResource::class, 'editHeaderActions') ? OnCallShiftResource::editHeaderActions($this) : [DeleteAction::make()];
    }
}
