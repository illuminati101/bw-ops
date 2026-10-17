<?php

namespace App\Filament\Resources\SurchargeRules\Pages;

use App\Filament\Resources\SurchargeRules\SurchargeRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSurchargeRules extends ListRecords
{
    protected static string $resource = SurchargeRuleResource::class;

    protected function getHeaderActions(): array
    {
        return SurchargeRuleResource::canCreate() ? [CreateAction::make()] : [];
    }
}
