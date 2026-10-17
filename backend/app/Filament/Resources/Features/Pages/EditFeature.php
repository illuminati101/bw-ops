<?php

namespace App\Filament\Resources\Features\Pages;

use App\Filament\Resources\Features\FeatureResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFeature extends EditRecord
{
    protected static string $resource = FeatureResource::class;

    protected function getHeaderActions(): array
    {
        return method_exists(FeatureResource::class, 'editHeaderActions') ? FeatureResource::editHeaderActions($this) : [DeleteAction::make()];
    }
}
