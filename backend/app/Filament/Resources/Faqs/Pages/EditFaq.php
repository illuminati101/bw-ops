<?php

namespace App\Filament\Resources\Faqs\Pages;

use App\Filament\Concerns\InteractsWithDrafts;
use App\Filament\Resources\Faqs\FaqResource;
use Filament\Resources\Pages\EditRecord;

class EditFaq extends EditRecord
{
    use InteractsWithDrafts;

    protected static string $resource = FaqResource::class;

    protected static string $publishPermission = 'content.publish';

    protected function getHeaderActions(): array
    {
        return $this->draftActions();
    }

    protected function getSaveFormAction(): \Filament\Actions\Action
    {
        return parent::getSaveFormAction()->label(fn () => $this->getRecord()->is_published ? 'Save draft' : 'Save');
    }
}
