<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Concerns\InteractsWithDrafts;
use App\Filament\Resources\Services\ServiceResource;
use Filament\Resources\Pages\EditRecord;

class EditService extends EditRecord
{
    use InteractsWithDrafts;

    protected static string $resource = ServiceResource::class;

    protected static string $publishPermission = 'catalog.publish';

    protected function getHeaderActions(): array
    {
        return $this->draftActions();
    }

    protected function getSaveFormAction(): \Filament\Actions\Action
    {
        return parent::getSaveFormAction()->label(fn () => $this->getRecord()->is_published ? 'Save draft' : 'Save');
    }
}
