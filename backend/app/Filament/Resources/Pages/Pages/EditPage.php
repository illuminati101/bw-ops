<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Concerns\InteractsWithDrafts;
use App\Filament\Resources\Pages\PageResource;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    use InteractsWithDrafts;

    protected static string $resource = PageResource::class;

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
