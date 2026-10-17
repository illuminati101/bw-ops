<?php

namespace App\Livewire\Account;

use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\Feature;
use App\Models\Region;
use App\Models\Service;
use App\Services\Booking\BookingService;
use App\Services\Maps\MapsService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** One-page booking: every change re-prices live, using exactly the same rules as the apps and the admin panel. */
#[Layout('components.layouts.site')]
#[Title('Book')]
class BookingWizard extends Component
{
    public ?int $serviceId = null;

    public string $mode = 'advance';

    public ?array $pickup = null;

    public ?array $dropoff = null;

    public array $stops = [];

    public string $date = '';

    public string $time = '';

    public int $duration = 120;

    public array $schedule = ['type' => 'multi_day', 'start_date' => '', 'end_date' => '', 'start_time' => '08:00', 'end_time' => '18:00', 'weekdays' => [1, 2, 3, 4, 5]];

    public int $guards = 0;

    public int $vehicles = 1;

    public array $featureIds = [];

    public array $extraIds = [];

    public int $passengers = 1;

    public ?int $preferredOperatorId = null;

    public bool $forSomeoneElse = false;

    public string $principalName = '';

    public string $principalPhone = '';

    public string $notes = '';

    public string $paymentMethod = 'card';

    public ?int $paymentMethodId = null;

    public ?int $companyId = null;

    public ?array $quote = null;

    public ?array $route = null;

    public ?string $quoteError = null;

    public function mount(): void
    {
        if (request()->boolean('resume') && ($draft = session()->pull('booking_draft'))) {
            foreach ($draft as $k => $v) {
                if (property_exists($this, $k)) {
                    $this->{$k} = $v;
                }
            }
        } else {
            $slug = request('service');
            $service = $slug ? Service::query()->published()->where('slug', $slug)->first() : null;
            $service ??= Service::query()->published()->orderBy('sort')->get()->first(fn ($s) => $s->isAvailable());
            $this->selectService($service?->id, false);
            if (in_array(request('mode'), ['on_demand', 'advance'], true)) {
                $this->mode = request('mode');
            }
            if ($p = json_decode((string) request('pickup'), true)) {
                $this->pickup = isset($p['lat'], $p['lng']) ? $p : null;
            }
            $start = now()->addHours(3)->ceilMinutes(30);
            $this->date = $start->toDateString();
            $this->time = $start->format('H:i');
            $this->schedule['start_date'] = now()->addDays(3)->toDateString();
            $this->schedule['end_date'] = now()->addDays(5)->toDateString();
        }
        if ($user = auth()->user()) {
            $this->paymentMethodId ??= $user->defaultPaymentMethod()?->id;
        }
        $this->normaliseMode();
        $this->refreshQuote();
    }

    public function service(): ?Service
    {
        return $this->serviceId ? Service::query()->published()->find($this->serviceId) : null;
    }

    public function selectService(?int $id, bool $requote = true): void
    {
        $this->serviceId = $id;
        $s = $this->service();
        if ($s) {
            $this->guards = $s->default_guards;
            $this->vehicles = max(1, $s->default_vehicles);
            $this->duration = max($this->duration, $s->min_duration_minutes);
            $this->extraIds = array_values(array_intersect($this->extraIds, $s->extras()->published()->pluck('extras.id')->all()));
            if (! $s->includes_transport) {
                $this->dropoff = null;
                $this->stops = [];
            }
        }
        $this->normaliseMode();
        if ($requote) {
            $this->refreshQuote();
        }
    }

    private function normaliseMode(): void
    {
        $s = $this->service();
        if (! $s) {
            return;
        }
        $canNow = $s->allows_on_demand && feature_enabled('on_demand');
        $canLater = $s->allows_advance && feature_enabled('advance');
        if ($this->mode === 'on_demand' && ! $canNow) {
            $this->mode = 'advance';
        }
        if ($this->mode === 'advance' && ! $canLater && $canNow) {
            $this->mode = 'on_demand';
        }
    }

    public function updated(string $prop): void
    {
        if ($prop === 'serviceId') {
            $this->selectService($this->serviceId);

            return;
        }
        if ($prop === 'mode') {
            $this->normaliseMode();
        }
        if (! in_array($prop, ['notes', 'principalName', 'principalPhone'], true)) {
            $this->refreshQuote();
        }
    }

    public function searchPlaces(string $q): array
    {
        return array_slice(app(MapsService::class)->search($q), 0, 6);
    }

    public function setPlace(string $field, array $place, ?int $index = null): void
    {
        if (($place['lat'] ?? null) === null && ! empty($place['place_id'])) {
            $place = app(MapsService::class)->placeDetails($place['place_id']) ?? $place;
        }
        if (($place['lat'] ?? null) === null) {
            return;
        }
        $value = ['address' => $place['address'], 'lat' => (float) $place['lat'], 'lng' => (float) $place['lng']];
        if ($field === 'stop' && $index !== null) {
            $this->stops[$index] = array_merge($this->stops[$index] ?? ['wait_minutes' => 15], $value);
        } elseif (in_array($field, ['pickup', 'dropoff'], true)) {
            $this->{$field} = $value;
        }
        $this->refreshQuote();
    }

    public function useMyLocation(float $lat, float $lng): void
    {
        $address = app(MapsService::class)->reverse($lat, $lng) ?? 'Current location';
        $this->pickup = ['address' => $address, 'lat' => $lat, 'lng' => $lng];
        $this->refreshQuote();
    }

    public function clearPlace(string $field): void
    {
        $this->{$field} = null;
        $this->refreshQuote();
    }

    public function addStop(): void
    {
        if (count($this->stops) < 8) {
            $this->stops[] = ['address' => null, 'lat' => null, 'lng' => null, 'wait_minutes' => 15];
        }
    }

    public function removeStop(int $i): void
    {
        unset($this->stops[$i]);
        $this->stops = array_values($this->stops);
        $this->refreshQuote();
    }

    public function step(string $field, int $delta, int $min, int $max): void
    {
        $this->{$field} = max($min, min($max, $this->{$field} + $delta));
        $this->refreshQuote();
    }

    private function payload(): array
    {
        $s = $this->service();
        $isMulti = $s && $s->structure === 'multi_day';

        return [
            'service_id' => $this->serviceId,
            'mode' => $this->mode,
            'starts_at' => $this->mode === 'advance' && ! $isMulti ? trim($this->date.' '.$this->time) : null,
            'duration_minutes' => $isMulti ? null : $this->duration,
            'pickup' => $this->pickup,
            'dropoff' => $this->dropoff,
            'stops' => array_values(array_filter($this->stops, fn ($s) => isset($s['lat']))),
            'guards' => $this->guards,
            'vehicles' => $this->vehicles,
            'feature_ids' => $this->featureIds,
            'extras' => array_map(fn ($id) => ['id' => (int) $id], $this->extraIds),
            'schedule' => $isMulti ? $this->schedule : null,
            'principal' => $this->forSomeoneElse ? ['name' => $this->principalName, 'phone' => $this->principalPhone] : null,
            'passengers' => $this->passengers,
            'client_notes' => $this->notes ?: null,
            'preferred_operator_id' => $this->preferredOperatorId,
            'payment_method' => $this->paymentMethod,
            'payment_method_id' => $this->paymentMethodId,
            'company_id' => $this->companyId,
        ];
    }

    public function refreshQuote(): void
    {
        $this->quote = null;
        $this->quoteError = null;
        if (! $this->serviceId) {
            return;
        }
        if (! $this->pickup) {
            $this->quoteError = 'Add a pickup point to see your price.';

            return;
        }
        try {
            $r = app(BookingService::class)->quote($this->payload(), auth()->user());
            $this->quote = $r['quote']->toArray() + ['structure' => $r['structure'], 'minimum_minutes' => $r['minimum_minutes'], 'shifts' => $r['shifts']];
            $this->route = $r['route'];
            if ($this->duration < $r['minimum_minutes'] && $this->mode === 'advance' && $r['structure'] !== 'multi_day') {
                $this->duration = $r['minimum_minutes'];
            }
        } catch (ValidationException $e) {
            $this->quoteError = collect($e->errors())->flatten()->first();
        } catch (\Throwable $e) {
            report($e);
            $this->quoteError = 'We could not price this right now. Please try again, or call us.';
        }
    }

    private function saveDraft(): void
    {
        session()->put('booking_draft', collect(get_object_vars($this))->only([
            'serviceId', 'mode', 'pickup', 'dropoff', 'stops', 'date', 'time', 'duration', 'schedule', 'guards', 'vehicles',
            'featureIds', 'extraIds', 'passengers', 'forSomeoneElse', 'principalName', 'principalPhone', 'notes',
            'paymentMethod', 'paymentMethodId', 'companyId', 'preferredOperatorId',
        ])->all());
    }

    /** Leave to add a card or verify ID, keeping everything chosen so far. */
    public function leaveFor(string $where): mixed
    {
        $this->saveDraft();

        return $this->redirectRoute($where === 'verify' ? 'account.verify' : 'account.payment', ['return' => 'book'], navigate: false);
    }

    public function confirm(): mixed
    {
        $user = auth()->user();
        if (! $user) {
            $this->saveDraft();
            session()->put('url.intended', route('book', ['resume' => 1]));

            return $this->redirectRoute('account.login', navigate: false);
        }
        if (! $user->isClient()) {
            $this->quoteError = 'Staff and crew accounts cannot make bookings here. Use the admin panel to book for a client.';

            return null;
        }
        if ($this->forSomeoneElse) {
            $this->validate(['principalName' => 'required|string|max:100', 'principalPhone' => 'required|string|max:24'], [], ['principalName' => 'name', 'principalPhone' => 'mobile number']);
        }
        try {
            $booking = app(BookingService::class)->create($this->payload(), $user, $user, 'web');
        } catch (ValidationException $e) {
            $this->quoteError = collect($e->errors())->flatten()->first();

            return null;
        }

        return $this->redirectRoute('account.booking', ['reference' => $booking->reference], navigate: false);
    }

    public function render()
    {
        $user = auth()->user();
        $service = $this->service();
        $services = Service::query()->published()->orderBy('sort')->get()->filter->isAvailable()->values();
        $region = Region::default();

        return view('livewire.account.booking-wizard', [
            'services' => $services,
            'service' => $service,
            'region' => $region,
            'filters' => Feature::query()->clientFilterable()->get(),
            'extras' => $service ? $service->extras()->published()->orderBy('sort')->get() : collect(),
            'cards' => $user ? $user->paymentMethods()->orderByDesc('is_default')->get() : collect(),
            'companies' => $user ? $user->companies : collect(),
            'eftAllowed' => $user && feature_enabled('eft_invoicing') && (($user->clientProfile?->eft_approved) || $user->companies->contains('eft_approved', true)),
            'previousOperators' => $user && feature_enabled('preferred_operator') ? BookingAssignment::query()
                ->whereHas('booking', fn ($q) => $q->where('client_id', $user->id)->where('status', 'completed'))
                ->with('operator.user')->get()->pluck('operator')->filter()->unique('id')->values() : collect(),
            'verification' => $user?->clientProfile?->verification_status,
            'isMulti' => $service && $service->structure === 'multi_day',
            'minDate' => now()->toDateString(),
            'maxDate' => now()->addDays((int) settings('booking.max_days_ahead'))->toDateString(),
        ]);
    }
}
