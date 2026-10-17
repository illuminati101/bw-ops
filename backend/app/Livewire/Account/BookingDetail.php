<?php

namespace App\Livewire\Account;

use App\Enums\BookingStatus;
use App\Events\ChatMessageSent;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Rating;
use App\Models\SosAlert;
use App\Services\Booking\BookingService;
use App\Services\Booking\TripShareService;
use App\Services\Messaging\Channels;
use App\Services\Messaging\Notifier;
use App\Services\Safety\SosService;
use App\Support\Realtime;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** A client's view of one booking: live map, operator, arrival PIN, chat, and every action the app offers. */
#[Layout('components.layouts.site')]
class BookingDetail extends Component
{
    #[Locked]
    public string $reference;

    public string $chatBody = '';

    public string $cancelReason = '';

    public ?string $shareUrl = null;

    public int $score = 0;

    public string $comment = '';

    #[Locked]
    public ?int $sosId = null;

    public string $sosPin = '';

    public ?string $flash = null;

    public array $mapData = [];

    public function mount(string $reference): void
    {
        $this->reference = $reference;
        $b = $this->booking();
        $this->sosId = SosAlert::query()->where('user_id', auth()->id())->where('booking_id', $b->id)->whereIn('status', ['open', 'acknowledged'])->value('id');
    }

    private function booking(): Booking
    {
        return Booking::query()->visibleTo(auth()->user())->where('reference', $this->reference)
            ->with(['service', 'stops', 'shifts', 'paymentMethodRecord'])->firstOrFail();
    }

    private function fail(ValidationException $e): void
    {
        $this->flash = collect($e->errors())->flatten()->first();
    }

    public function reconfirm(BookingService $bookings): void
    {
        $bookings->reconfirm($this->booking(), auth()->user());
        $this->flash = 'Thank you. Your booking is reconfirmed.';
    }

    public function acceptQuote(BookingService $bookings): void
    {
        try {
            $bookings->acceptQuote($this->booking(), auth()->user());
            $this->flash = 'Quote accepted. Your booking is confirmed.';
        } catch (ValidationException $e) {
            $this->fail($e);
        }
    }

    public function cancel(BookingService $bookings): void
    {
        $this->validate(['cancelReason' => 'nullable|string|max:300']);
        try {
            $fee = $bookings->cancel($this->booking(), auth()->user(), $this->cancelReason ?: null);
            $this->flash = $fee > 0 ? 'Booking cancelled. A cancellation fee of '.rands($fee).' applies.' : 'Booking cancelled. No fee applies.';
            $this->dispatch('close-dialog');
        } catch (ValidationException $e) {
            $this->fail($e);
        }
    }

    public function share(TripShareService $shares): void
    {
        $b = $this->booking();
        if (! feature_enabled('trip_sharing') || $b->status->isClosed()) {
            return;
        }
        $this->shareUrl = url('t/'.$shares->create($b, 'web')->token);
    }

    public function sendMessage(): void
    {
        $this->validate(['chatBody' => 'required|string|max:1000'], [], ['chatBody' => 'message']);
        $b = $this->booking();
        if (! $b->chatOpen()) {
            $this->flash = 'Chat is available while your booking is active.';

            return;
        }
        $m = $b->messages()->create(['sender_id' => auth()->id(), 'sender_role' => 'client', 'body' => $this->chatBody]);
        Realtime::send(new ChatMessageSent($m));
        foreach ($b->activeAssignments()->with('operator.user')->get() as $a) {
            app(Channels::class)->push($a->operator->user, 'Message from client', mb_strimwidth($this->chatBody, 0, 90, '…'), ['job' => $a->id, 'chat' => true], 'crew');
        }
        $this->chatBody = '';
    }

    public function call(Channels $channels): void
    {
        $b = $this->booking();
        $op = $b->activeAssignments()->with('operator.user')->first()?->operator?->user;
        if (! feature_enabled('masked_calls') || ! $channels->twilioReady() || ! $b->chatOpen() || ! $op?->phone) {
            $this->flash = 'Calling is not available right now. Use chat, or call our control room.';

            return;
        }
        $channels->bridgeCall(auth()->user()->phone, $op->phone, auth()->user());
        $b->logEvent('call.client_to_operator', 'Masked call started by client');
        $this->flash = 'We are calling your phone now and will connect you to your operator.';
    }

    public function rate(): void
    {
        $this->validate(['score' => 'required|integer|min:1|max:5', 'comment' => 'nullable|string|max:1000']);
        $b = $this->booking();
        if ($b->status !== BookingStatus::Completed || ! feature_enabled('ratings')) {
            return;
        }
        $lead = $b->activeAssignments()->with('operator')->first();
        Rating::updateOrCreate(
            ['booking_id' => $b->id, 'rater_id' => auth()->id(), 'direction' => 'client_to_operator'],
            ['ratee_id' => $lead?->operator?->user_id, 'score' => $this->score, 'comment' => $this->comment ?: null, 'flagged' => $this->score <= 2]
        );
        foreach ($b->activeAssignments()->with('operator')->get() as $a) {
            $op = $a->operator;
            $q = Rating::query()->where('ratee_id', $op->user_id)->where('direction', 'client_to_operator');
            $op->update(['rating_avg' => round((float) $q->avg('score'), 2), 'rating_count' => $q->count()]);
        }
        if ($this->score <= 2) {
            app(Notifier::class)->staff('Low rating', "{$b->reference} was rated {$this->score}/5.", 'quality', 'bookings.view');
        }
        $this->flash = 'Thank you for your feedback.';
    }

    public function raiseSos(SosService $sos, ?float $lat = null, ?float $lng = null): void
    {
        try {
            $this->sosId = $sos->raise(auth()->user(), $lat, $lng, $this->booking())->id;
        } catch (ValidationException $e) {
            $this->fail($e);
        }
    }

    public function updateSosLocation(float $lat, float $lng): void
    {
        if ($this->sosId) {
            SosAlert::query()->where('user_id', auth()->id())->whereKey($this->sosId)->update(['lat' => $lat, 'lng' => $lng]);
        }
    }

    public function cancelSos(SosService $sos): void
    {
        $alert = SosAlert::query()->where('user_id', auth()->id())->find($this->sosId);
        if (! $alert) {
            $this->sosId = null;

            return;
        }
        try {
            $sos->cancelByUser($alert, auth()->user(), $this->sosPin ?: null, 'False alarm (cancelled by client on the website)');
        } catch (ValidationException $e) {
            $this->addError('sosPin', collect($e->errors())->flatten()->first());

            return;
        }
        // Identical outcome on screen whether this was the safety PIN or the duress PIN.
        $this->sosId = null;
        $this->sosPin = '';
        $this->flash = 'Alert cancelled. Our control room may still call to check on you.';
    }

    public function render(BookingService $bookings)
    {
        $b = $this->booking();
        BookingResource::$detailed = true;
        // Round-trip through JSON so nested resources (operators) become plain arrays.
        $d = (new BookingResource($b))->response(request())->getData(true)['data'];
        $b->messages()->whereNot('sender_role', 'client')->whereNull('read_at')->update(['read_at' => now()]);

        $this->mapData = [
            'pickup' => $d['pickup'],
            'dropoff' => $d['dropoff'],
            'stops' => $d['stops'],
            'operators' => collect($d['operators'])->filter(fn ($o) => $o['location'])->map(fn ($o) => ['lat' => $o['location']['lat'], 'lng' => $o['location']['lng'], 'label' => $o['call_sign'] ? preg_replace('/^\D+/', '', $o['call_sign']) : mb_substr($o['first_name'], 0, 1)])->values()->all(),
        ];

        return view('livewire.account.booking-detail', [
            'b' => $b,
            'd' => $d,
            'live' => in_array($b->status, BookingStatus::active(), true) || $b->status === BookingStatus::Searching,
            'cancelFee' => $d['can']['cancel'] ? $bookings->cancellationFee($b) : null,
            'messages' => $d['can']['chat'] || $b->messages()->exists() ? $b->messages()->get() : collect(),
            'controlRoom' => settings('company.control_room_phone'),
            'pinSet' => (bool) auth()->user()->safety_pin_hash,
        ])->title($b->service->name.' '.$b->reference);
    }
}
