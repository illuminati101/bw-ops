<?php

namespace App\Filament\Resources\SurchargeRules\Pages;

use App\Filament\Resources\SurchargeRules\SurchargeRuleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSurchargeRule extends CreateRecord
{
    protected static string $resource = SurchargeRuleResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::hasPage('view') ? static::getResource()::getUrl('view', ['record' => $this->getRecord()]) : static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
