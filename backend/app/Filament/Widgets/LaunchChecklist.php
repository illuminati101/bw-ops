<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\Widget;

class LaunchChecklist extends Widget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.launch-checklist';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('settings.update');
    }

    protected function getViewData(): array
    {
        $placeholders = collect(settings()->definitions())->keys()->filter(fn ($k) => str_starts_with($k, 'company.') && is_string(settings($k)) && str_contains(settings($k), '['))->values();
        $items = [
            ['Company details for legal pages (ECTA)', $placeholders->isEmpty(), $placeholders->isEmpty() ? 'Complete' : $placeholders->count().' placeholder(s) left in Settings → Company', \App\Filament\Pages\Settings::getUrl()],
            ['Card payments (Paystack keys)', filled(config('bwops.integrations.paystack.secret_key')), 'Add PAYSTACK_SECRET_KEY / PUBLIC_KEY to .env', null],
            ['SMS & calls (Twilio)', config('bwops.integrations.sms_driver') === 'twilio' && filled(config('bwops.integrations.twilio.sid')), 'Add Twilio details to .env and set BW_SMS_DRIVER=twilio', null],
            ['Google Maps API key', filled(config('bwops.integrations.google_maps_key')), 'Add GOOGLE_MAPS_API_KEY to .env (OpenStreetMap is used until then)', null],
            ['Email sending', config('mail.default') !== 'log', 'Set MAIL_MAILER and mailbox details in .env', null],
            ['Owner password changed', ! \Hash::check('ChangeMe-2026!', (string) auth()->user()->password), 'Change it under your profile (top right)', null],
            ['Two-factor authentication', filled(auth()->user()->app_authentication_secret), 'Set up an authenticator app under your profile', null],
            ['Demo data removed', ! User::query()->where('email', 'dispatch@blackandwhiteops.com')->exists(), 'Delete the demo operators, clients and bookings before launch', null],
            ['Legal pages reviewed by an attorney', false, 'Terms, Privacy and Cancellation policy are drafts until reviewed', \App\Filament\Resources\Pages\PageResource::getUrl()],
            ['Rate cards set by the owner', false, 'Starter rates are placeholders: review every rate card', \App\Filament\Resources\RateCards\RateCardResource::getUrl()],
        ];

        return ['items' => $items, 'done' => collect($items)->where(1, true)->count()];
    }
}
