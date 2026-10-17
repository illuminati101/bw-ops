<?php

namespace App\Filament\Resources\SurchargeRules\Pages;

use App\Filament\Resources\SurchargeRules\SurchargeRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSurchargeRule extends EditRecord
{
    protected static string $resource = SurchargeRuleResource::class;

    protected function getHeaderActions(): array
    {
        return method_exists(SurchargeRuleResource::class, 'editHeaderActions') ? SurchargeRuleResource::editHeaderActions($this) : [DeleteAction::make()];
    }
}
