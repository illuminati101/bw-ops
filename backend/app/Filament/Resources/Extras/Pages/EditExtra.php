<?php

namespace App\Filament\Resources\Extras\Pages;

use App\Filament\Concerns\InteractsWithDrafts;
use App\Filament\Resources\Extras\ExtraResource;
use Filament\Resources\Pages\EditRecord;

class EditExtra extends EditRecord
{
    use InteractsWithDrafts;

    protected static string $resource = ExtraResource::class;

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
