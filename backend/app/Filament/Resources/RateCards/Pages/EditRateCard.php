<?php

namespace App\Filament\Resources\RateCards\Pages;

use App\Filament\Concerns\InteractsWithDrafts;
use App\Filament\Resources\RateCards\RateCardResource;
use Filament\Resources\Pages\EditRecord;

class EditRateCard extends EditRecord
{
    use InteractsWithDrafts;

    protected static string $resource = RateCardResource::class;

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
