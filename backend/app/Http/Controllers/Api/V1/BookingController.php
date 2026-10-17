<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Events\ChatMessageSent;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Rating;
use App\Services\Booking\BookingService;
use App\Services\Booking\TripShareService;
use App\Services\Messaging\Channels;
use App\Support\Realtime;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public static function rules(): array
    {
        return [
            'service_id' => 'required|integer',
            'mode' => 'nullable|in:advance,on_demand',
            'starts_at' => 'nullable|date',
            'duration_minutes' => 'nullable|integer|min:30|max:1440',
            'pickup' => 'required|array',
            'pickup.lat' => 'required|numeric', 'pickup.lng' => 'required|numeric', 'pickup.address' => 'nullable|string|max:250',
            'dropoff' => 'nullable|array',
            'dropoff.lat' => 'nullable|numeric', 'dropoff.lng' => 'nullable|numeric', 'dropoff.address' => 'nullable|string|max:250',
            'stops' => 'nullable|array|max:8',
            'guards' => 'nullable|integer|min:0|max:20',
            'vehicles' => 'nullable|integer|min:0|max:10',
            'feature_ids' => 'nullable|array', 'feature_ids.*' => 'integer',
            'extras' => 'nullable|array',
            'schedule' => 'nullable|array',
            'principal' => 'nullable|array',
            'principal.name' => 'nullable|string|max:100', 'principal.phone' => 'nullable|string|max:24',
            'passengers' => 'nullable|integer|min:1|max:12',
            'client_notes' => 'nullable|string|max:1000',
            'preferred_operator_id' => 'nullable|integer',
            'payment_method' => 'nullable|in:card,card_machine,eft',
            'payment_method_id' => 'nullable|integer',
            'company_id' => 'nullable|integer',
        ];
    }

    private function find(Request $request, string $reference): Booking
    {
        return Booking::query()->visibleTo($request->user())->where('reference', $reference)
            ->with(['service', 'stops', 'shifts', 'paymentMethodRecord'])->firstOrFail();
    }

    public function index(Request $request)
    {
        $scope = $request->query('scope', 'upcoming');
        $q = Booking::query()->visibleTo($request->user())->with(['service', 'stops', 'shifts', 'paymentMethodRecord']);
        $scope === 'past'
            ? $q->whereIn('status', array_map(fn ($s) => $s->value, BookingStatus::closed()))->orderByDesc('starts_at')
            : $q->whereNotIn('status', array_map(fn ($s) => $s->value, BookingStatus::closed()))->orderBy('starts_at');

        return BookingResource::collection($q->paginate(20));
    }

    public function store(Request $request, BookingService $bookings)
    {
        $request->validate(self::rules());
        $user = $request->user();
        if (! $user->accepted_terms_at) {
            throw ValidationException::withMessages(['terms' => 'Please accept our Terms of Service first.']);
        }
        $booking = $bookings->create($request->all(), $user, $user, $request->header('X-App') ? 'app' : 'web');

        return (new BookingResource($booking->load(['service', 'stops', 'shifts'])))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $reference)
    {
        return new BookingResource($this->find($request, $reference));
    }

    public function cancellationFee(Request $request, string $reference, BookingService $bookings)
    {
        $b = $this->find($request, $reference);

        return $bookings->cancellationFee($b) + ['cancellable' => $b->status->isCancellable()];
    }

    public function cancel(Request $request, string $reference, BookingService $bookings)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:300']);
        $b = $this->find($request, $reference);
        $fee = $bookings->cancel($b, $request->user(), $data['reason'] ?? null);

        return ['fee' => $fee, 'booking' => new BookingResource($b->fresh())];
    }

    public function reconfirm(Request $request, string $reference, BookingService $bookings)
    {
        return new BookingResource($bookings->reconfirm($this->find($request, $reference), $request->user()));
    }

    public function acceptQuote(Request $request, string $reference, BookingService $bookings)
    {
        return new BookingResource($bookings->acceptQuote($this->find($request, $reference), $request->user()));
    }

    public function rate(Request $request, string $reference)
    {
        $data = $request->validate(['score' => 'required|integer|min:1|max:5', 'comment' => 'nullable|string|max:1000']);
        $b = $this->find($request, $reference);
        abort_unless($b->status === BookingStatus::Completed && feature_enabled('ratings'), 422, 'Only completed bookings can be rated.');
        $lead = $b->activeAssignments()->with('operator')->first();
        Rating::updateOrCreate(
            ['booking_id' => $b->id, 'rater_id' => $request->user()->id, 'direction' => 'client_to_operator'],
            ['ratee_id' => $lead?->operator?->user_id, 'score' => $data['score'], 'comment' => $data['comment'] ?? null, 'flagged' => $data['score'] <= 2]
        );
        foreach ($b->activeAssignments()->with('operator')->get() as $a) {
            $op = $a->operator;
            $avg = Rating::query()->where('ratee_id', $op->user_id)->where('direction', 'client_to_operator')->avg('score');
            $op->update(['rating_avg' => round((float) $avg, 2), 'rating_count' => Rating::query()->where('ratee_id', $op->user_id)->count()]);
        }
        if ($data['score'] <= 2) {
            app(\App\Services\Messaging\Notifier::class)->staff('Low rating', "{$b->reference} was rated {$data['score']}/5.", 'quality', 'bookings.view');
        }

        return response()->noContent();
    }

    public function share(Request $request, string $reference, TripShareService $shares)
    {
        abort_unless(feature_enabled('trip_sharing'), 422, 'Trip sharing is not available.');
        $b = $this->find($request, $reference);
        abort_if($b->status->isClosed(), 422, 'This booking has ended.');
        $share = $shares->create($b, $request->input('label'));

        return [
            'url' => url('t/'.$share->token),
            'expires_at' => $share->expires_at->toIso8601String(),
            'message' => "Follow my trip with ".settings('company.trading_name').": ".url('t/'.$share->token),
        ];
    }

    public function messages(Request $request, string $reference)
    {
        $b = $this->find($request, $reference);
        $b->messages()->whereNot('sender_role', 'client')->whereNull('read_at')->update(['read_at' => now()]);

        return ['messages' => $b->messages()->get()->map(fn ($m) => [
            'id' => $m->id, 'mine' => $m->sender_role === 'client', 'sender_role' => $m->sender_role, 'body' => $m->body, 'created_at' => $m->created_at->toIso8601String(),
        ]), 'open' => $b->chatOpen()];
    }

    public function sendMessage(Request $request, string $reference)
    {
        $data = $request->validate(['body' => 'required|string|max:1000']);
        $b = $this->find($request, $reference);
        abort_unless($b->chatOpen(), 422, 'Chat is available while your booking is active.');
        $m = $b->messages()->create(['sender_id' => $request->user()->id, 'sender_role' => 'client', 'body' => $data['body']]);
        Realtime::send(new ChatMessageSent($m));
        foreach ($b->activeAssignments()->with('operator.user')->get() as $a) {
            app(Channels::class)->push($a->operator->user, 'Message from client', mb_strimwidth($data['body'], 0, 90, '…'), ['job' => $a->id, 'chat' => true], 'crew');
        }

        return ['message' => ['id' => $m->id, 'mine' => true, 'sender_role' => 'client', 'body' => $m->body, 'created_at' => $m->created_at->toIso8601String()]];
    }

    /** Masked call: Twilio rings the client, then connects them to the operator. Neither sees the other's number. */
    public function call(Request $request, string $reference, Channels $channels)
    {
        $b = $this->find($request, $reference);
        abort_unless(feature_enabled('masked_calls') && $channels->twilioReady() && $b->chatOpen(), 422, 'Calling is not available right now. Use chat or call the control room.');
        $op = $b->activeAssignments()->with('operator.user')->first()?->operator?->user;
        abort_unless($op?->phone && $request->user()->phone, 422, 'Calling is not available right now.');
        $channels->bridgeCall($request->user()->phone, $op->phone, $request->user());
        $b->logEvent('call.client_to_operator', 'Masked call started by client');

        return ['message' => 'We are calling you now and will connect you to your operator.'];
    }
}
