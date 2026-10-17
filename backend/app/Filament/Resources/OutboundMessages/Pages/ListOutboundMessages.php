<?php

namespace App\Filament\Resources\OutboundMessages\Pages;

use App\Filament\Resources\OutboundMessages\OutboundMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListOutboundMessages extends ListRecords
{
    protected static string $resource = OutboundMessageResource::class;

    public function getSubheading(): ?string
    {
        return config('bwops.integrations.sms_driver') === 'twilio' ? null : 'SMS is in test mode: login codes and alerts appear here instead of being sent. Add Twilio details in the .env file to send for real.';
    }
}
