<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return method_exists(ClientResource::class, 'viewHeaderActions') ? ClientResource::viewHeaderActions($this) : (ClientResource::hasPage('edit') ? [EditAction::make()] : []);
    }
}
