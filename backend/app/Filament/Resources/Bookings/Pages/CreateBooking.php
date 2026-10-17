<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Feature;
use App\Models\Service;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Services\Maps\MapsService;
use App\Support\PhoneNumber;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/** Bookings taken by phone or in person. Uses exactly the same rules and pricing as the website and apps. */
class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    protected static ?string $title = 'New booking';

    public static function placeField(string $name, string $label, bool $required): Select
    {
        return Select::make($name)->label($label)->required($required)->searchable()->native(false)
            ->getSearchResultsUsing(function (string $search) {
                return collect(app(MapsService::class)->search($search))->mapWithKeys(fn ($r) => [json_encode($r) => $r['address']])->all();
            })
            ->getOptionLabelUsing(fn ($value) => json_decode((string) $value, true)['address'] ?? $value)
            ->helperText('Start typing an address or place name.')
            ->live();
    }

    private function payload(array $data): array
    {
        $place = function (?string $json) {
            if (! $json) {
                return null;
            }
            $p = json_decode($json, true);
            if (($p['lat'] ?? null) === null && ! empty($p['place_id'])) {
                $p = app(MapsService::class)->placeDetails($p['place_id']) ?? $p;
            }

            return $p;
        };

        return [
            'service_id' => $data['service_id'] ?? null,
            'mode' => $data['mode'] ?? 'advance',
            'starts_at' => $data['starts_at'] ?? null,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'pickup' => $place($data['pickup'] ?? null),
            'dropoff' => $place($data['dropoff'] ?? null),
            'guards' => $data['guards'] ?? null,
            'vehicles' => $data['vehicles'] ?? null,
            'feature_ids' => $data['feature_ids'] ?? [],
            'extras' => array_map(fn ($id) => ['id' => $id], $data['extras'] ?? []),
            'principal' => ['name' => $data['principal_name'] ?? null, 'phone' => $data['principal_phone'] ?? null],
            'client_notes' => $data['client_notes'] ?? null,
            'payment_method' => $data['payment_method'] ?? 'card',
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(2)->schema([
                Section::make('Client')->columns(2)->schema([
                    Select::make('client_id')->label('Client')->required()->searchable()->native(false)
                        ->getSearchResultsUsing(fn (string $search) => User::query()->where('type', 'client')
                            ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', '%'.ltrim(preg_replace('/\D/', '', $search), '0').'%')->orWhere('email', 'like', "%{$search}%"))
                            ->limit(20)->get()->mapWithKeys(fn ($u) => [$u->id => "{$u->name} · {$u->phone}"]))
                        ->getOptionLabelUsing(fn ($value) => ($u = User::find($value)) ? "{$u->name} · {$u->phone}" : null)
                        ->createOptionForm([
                            TextInput::make('name')->required(),
                            TextInput::make('phone')->required()->tel(),
                            TextInput::make('email')->email(),
                        ])
                        ->createOptionUsing(function (array $data) {
                            $phone = PhoneNumber::normalize($data['phone']);
                            $user = User::firstOrCreate(['phone' => $phone], ['name' => $data['name'], 'email' => $data['email'] ?? null, 'type' => 'client', 'status' => 'active', 'accepted_terms_at' => now()]);
                            $user->clientProfile()->firstOrCreate([], ['verification_status' => 'unverified']);

                            return $user->id;
                        })->live(),
                    Toggle::make('identity_checked')->label('I have verified this client\'s identity')->helperText('Required for clients who haven\'t completed ID verification online.')->inline(false),
                    TextInput::make('principal_name')->label('Booking for (principal)')->placeholder('Leave blank if the client'),
                    TextInput::make('principal_phone')->label('Principal\'s mobile')->tel(),
                ]),
                Section::make('Job')->columns(3)->schema([
                    Select::make('service_id')->label('Service')->required()->native(false)->live()
                        ->options(fn () => Service::query()->published()->orderBy('sort')->pluck('name', 'id')),
                    Select::make('mode')->options(['advance' => 'Scheduled', 'on_demand' => 'Dispatch now'])->default('advance')->required()->native(false)->live(),
                    DateTimePicker::make('starts_at')->label('Start')->seconds(false)->native(false)->minDate(now())->visible(fn ($get) => $get('mode') !== 'on_demand')->required(fn ($get) => $get('mode') !== 'on_demand')->live(onBlur: true),
                    Select::make('duration_minutes')->label('Duration')->native(false)->live()
                        ->options(collect([60, 90, 120, 180, 240, 300, 360, 480, 600, 720])->mapWithKeys(fn ($m) => [$m => intdiv($m, 60).'h'.($m % 60 ? ' 30m' : '')])),
                    TextInput::make('guards')->numeric()->label('Officers')->minValue(0)->maxValue(20)->live(onBlur: true),
                    TextInput::make('vehicles')->numeric()->label('Vehicles')->minValue(0)->maxValue(10)->live(onBlur: true),
                    self::placeField('pickup', 'Pickup', true)->columnSpanFull(),
                    self::placeField('dropoff', 'Drop-off (optional)', false)->columnSpanFull(),
                    Select::make('feature_ids')->label('Requirements')->multiple()->native(false)->columnSpanFull()->live()
                        ->options(fn () => Feature::query()->where('is_active', true)->orderBy('sort')->pluck('name', 'id')),
                    CheckboxList::make('extras')->label('Add-ons')->columns(3)->columnSpanFull()->live()
                        ->options(fn ($get) => $get('service_id') ? Service::find($get('service_id'))?->extras()->published()->pluck('name', 'extras.id') ?? [] : []),
                    Textarea::make('client_notes')->label('Notes from the client')->rows(2)->columnSpanFull(),
                ]),
            ]),
            Grid::make(1)->schema([
                Section::make('Payment')->schema([
                    Select::make('payment_method')->options(['card' => 'Client\'s saved card', 'card_machine' => 'Card machine on completion', 'eft' => 'EFT invoice'])->default('card')->required()->native(false)->live(),
                ]),
                Section::make('Quote')->schema([
                    Placeholder::make('quote_preview')->hiddenLabel()->content(function ($get) {
                        if (! $get('service_id') || ! $get('pickup')) {
                            return 'Choose a service and pickup to see the price.';
                        }
                        try {
                            $q = app(BookingService::class)->quote($this->payload($get()))['quote'];
                        } catch (ValidationException $e) {
                            return new HtmlString('<span style="color:#fda4af">'.e(collect($e->errors())->flatten()->first()).'</span>');
                        } catch (\Throwable $e) {
                            return 'Price unavailable: '.$e->getMessage();
                        }
                        if ($q->customQuote) {
                            return new HtmlString('Custom quote service. Estimate <strong>'.rands($q->total).'</strong>. The booking will wait for you to send the quote.');
                        }

                        return new HtmlString('<table style="width:100%;font-size:.85rem">'.collect($q->lines)->map(fn ($l) => '<tr><td style="color:#a1a1aa">'.e($l['label']).'</td><td style="text-align:right">'.rands($l['amount']).'</td></tr>')->implode('')
                            .($q->surcharge ? '<tr><td style="color:#a1a1aa">'.e($q->surcharge['label']).'</td><td style="text-align:right">'.rands($q->surcharge['amount']).'</td></tr>' : '')
                            .'<tr><td style="font-weight:700;padding-top:6px">Total</td><td style="text-align:right;font-weight:700;color:#C9A86A">'.rands($q->total).'</td></tr></table>');
                    }),
                ]),
            ]),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $client = User::findOrFail($data['client_id']);
        if (! empty($data['identity_checked']) && $client->clientProfile && ! $client->clientProfile->isVerified()) {
            $client->clientProfile->update(['verification_status' => 'verified', 'verified_at' => now(), 'verified_by' => auth()->id(), 'verification_notes' => 'Verified by staff (phone / in person)']);
        }
        if (! $client->accepted_terms_at) {
            $client->update(['accepted_terms_at' => now(), 'accepted_terms_version' => settings('legal.terms_version')]);
        }
        try {
            return app(BookingService::class)->create($this->payload($data), $client, auth()->user(), 'admin');
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($v, $k) => ['data.'.explode('.', $k)[0] => $v])->all());
        }
    }

    protected function getRedirectUrl(): string
    {
        return BookingResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
