<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Jobs\EscalateSos;
use App\Jobs\ExpireDispatchOffer;
use App\Models\Booking;
use App\Models\DispatchOffer;
use App\Models\OperatorProfile;
use App\Models\Service;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Services\Booking\JobService;
use App\Services\Compliance\ComplianceService;
use App\Services\Dispatch\DispatchService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(); // no external map/route calls: falls back to estimates
        Bus::fake([ExpireDispatchOffer::class, EscalateSos::class]);
        $this->seed(DatabaseSeeder::class);
    }

    private function client(): User
    {
        return User::where('phone', '+27820000001')->firstOrFail();
    }

    private function payload(string $slug, array $over = []): array
    {
        return array_replace_recursive([
            'service_id' => Service::where('slug', $slug)->value('id'),
            'mode' => 'advance',
            'starts_at' => now()->addDays(3)->setTime(10, 0)->toIso8601String(),
            'duration_minutes' => 120,
            'pickup' => ['address' => 'V&A Waterfront', 'lat' => -33.9036, 'lng' => 18.4207],
            'dropoff' => ['address' => 'Constantia', 'lat' => -34.0277, 'lng' => 18.4233],
        ], $over);
    }

    public function test_quote_is_firm_and_itemised(): void
    {
        $result = app(BookingService::class)->quote($this->payload('secure-chauffeur'), $this->client());
        $q = $result['quote'];
        $this->assertGreaterThan(0, $q->total);
        $this->assertEquals(0, fmod($q->total, 5), 'Quotes round to R5');
        $this->assertNotEmpty($q->lines);
        $this->assertSame(480.0, $q->overtimeRate);
        $this->assertGreaterThanOrEqual(1200, $q->total, 'Minimum charge applies');
    }

    public function test_night_surcharge_applies(): void
    {
        $day = app(BookingService::class)->quote($this->payload('secure-chauffeur'))['quote']->total;
        $night = app(BookingService::class)->quote($this->payload('secure-chauffeur', ['starts_at' => now()->addDays(3)->setTime(23, 0)->toIso8601String()]))['quote'];
        $this->assertNotNull($night->surcharge);
        $this->assertGreaterThan($day, $night->total);
    }

    public function test_trusted_client_advance_booking_confirms_instantly(): void
    {
        $b = app(BookingService::class)->create($this->payload('secure-chauffeur'), $this->client());
        $this->assertSame(BookingStatus::Confirmed, $b->status);
        $this->assertNotNull($b->reconfirm_due_at);
        $this->assertMatchesRegularExpression('/^\d{4}$/', $b->arrival_pin);
    }

    public function test_unverified_client_cannot_book(): void
    {
        $this->expectException(ValidationException::class);
        app(BookingService::class)->create($this->payload('secure-chauffeur'), User::where('phone', '+27820000003')->first());
    }

    public function test_team_and_custom_quote_bookings_need_staff(): void
    {
        $svc = app(BookingService::class);
        $team = $svc->create($this->payload('close-protection', ['guards' => 3, 'duration_minutes' => 240]), $this->client());
        $this->assertSame(BookingStatus::PendingApproval, $team->status);
        $this->assertSame('team', $team->structure);

        $event = $svc->create($this->payload('event-protection', ['starts_at' => now()->addDays(5)->setTime(18, 0)->toIso8601String(), 'guards' => 4, 'duration_minutes' => 300]), $this->client());
        $this->assertSame(BookingStatus::QuoteRequested, $event->status);

        $staff = User::where('email', 'admin@blackandwhiteops.com')->first();
        $svc->sendQuote($event, 9999, 'Includes walk-through', $staff);
        $this->assertSame(BookingStatus::Quoted, $event->fresh()->status);
        $this->assertEquals(10000, $event->fresh()->quoted_total);
        $svc->acceptQuote($event->fresh(), $this->client());
        $this->assertSame(BookingStatus::Confirmed, $event->fresh()->status);
    }

    public function test_switching_off_team_bookings_blocks_them(): void
    {
        settings()->set('features.team_bookings', false);
        $this->expectException(ValidationException::class);
        app(BookingService::class)->create($this->payload('close-protection', ['guards' => 3, 'duration_minutes' => 240]), $this->client());
    }

    public function test_notice_period_is_enforced(): void
    {
        $this->expectException(ValidationException::class);
        app(BookingService::class)->create($this->payload('secure-chauffeur', ['starts_at' => now()->addMinutes(30)->toIso8601String()]), $this->client());
    }

    public function test_on_demand_full_lifecycle(): void
    {
        $svc = app(BookingService::class);
        $b = $svc->create($this->payload('secure-chauffeur', ['mode' => 'on_demand']), $this->client());
        $this->assertSame(BookingStatus::Searching, $b->status);

        $offer = DispatchOffer::where('booking_id', $b->id)->where('status', 'pending')->first();
        $this->assertNotNull($offer, 'An offer was made');
        $this->assertSame('BW-01', $offer->operator->call_sign, 'Nearest eligible driver gets it first');

        // First operator lets it expire: cascades to the next
        $offer->update(['expires_at' => now()->subSecond()]);
        app(DispatchService::class)->expire($offer);
        $next = DispatchOffer::where('booking_id', $b->id)->where('status', 'pending')->first();
        $this->assertNotNull($next);
        $this->assertNotSame($offer->operator_profile_id, $next->operator_profile_id);

        $assignment = app(DispatchService::class)->accept($next, $next->operator);
        $b->refresh();
        $this->assertSame(BookingStatus::Assigned, $b->status);

        $jobs = app(JobService::class);
        $op = $next->operator;
        $jobs->update($assignment, $op, 'en_route');
        $this->assertSame(BookingStatus::EnRoute, $b->fresh()->status);
        $jobs->update($assignment->fresh(), $op, 'arrived');
        $this->assertSame(BookingStatus::Arrived, $b->fresh()->status);

        try {
            $jobs->update($assignment->fresh(), $op, 'start', ['pin' => '0000' === $b->arrival_pin ? '1111' : '0000']);
            $this->fail('Wrong PIN must be rejected');
        } catch (ValidationException) {
        }
        $jobs->update($assignment->fresh(), $op, 'start', ['pin' => $b->arrival_pin]);
        $this->assertSame(BookingStatus::InProgress, $b->fresh()->status);

        $jobs->update($assignment->fresh(), $op, 'complete');
        $b->refresh();
        $this->assertSame(BookingStatus::Completed, $b->status);
        $this->assertSame('paid', $b->payment_status, 'Card charged after the job (record-only in test mode)');
        $this->assertNotNull($b->final_total);
        if ($op->isContractor()) {
            $this->assertNotNull($assignment->fresh()->payout_amount);
        }
    }

    public function test_cancellation_tiers(): void
    {
        $svc = app(BookingService::class);
        $far = $svc->create($this->payload('secure-chauffeur', ['starts_at' => now()->addDays(3)->toIso8601String()]), $this->client());
        $this->assertEquals(0, $svc->cancellationFee($far)['fee']);

        $near = $svc->create($this->payload('secure-chauffeur', ['starts_at' => now()->addHours(3)->toIso8601String()]), $this->client());
        $fee = $svc->cancellationFee($near)['fee'];
        $this->assertEquals(round($near->quoted_total * 0.25, 2), $fee);

        $charged = $svc->cancel($near, $this->client(), 'Plans changed');
        $this->assertEquals($fee, $charged);
        $this->assertSame(BookingStatus::Cancelled, $near->fresh()->status);
    }

    public function test_no_show_charges_fee_and_adds_strike(): void
    {
        $svc = app(BookingService::class);
        $b = $svc->create($this->payload('secure-chauffeur', ['starts_at' => now()->addHours(3)->toIso8601String()]), $this->client());
        $op = OperatorProfile::where('call_sign', 'BW-06')->first();
        $a = app(DispatchService::class)->assign($b, $op, 'driver', null, null, null, true);
        $a->update(['status' => AssignmentStatus::Arrived, 'arrived_at' => now()->subMinutes(30)]);
        $b->update(['starts_at' => now()->subMinutes(20)]);

        app(JobService::class)->update($a->fresh(), $op, 'no_show');
        $b->refresh();
        $this->assertSame(BookingStatus::NoShow, $b->status);
        $this->assertGreaterThanOrEqual(500, $b->cancellation_fee);
        $this->assertSame(1, $this->client()->activeStrikesCount());
    }

    public function test_expired_document_suspends_feature(): void
    {
        $thandi = OperatorProfile::where('call_sign', 'BW-03')->first();
        $compliance = app(ComplianceService::class);
        $slugs = $compliance->effectiveFeatures($thandi)->pluck('slug')->all();
        $this->assertContains('armed', $slugs);
        $this->assertNotContains('medic', $slugs, 'Expired first-aid certificate suspends Medic-trained');

        // Expire her firearm competency: "Armed" drops off too
        $thandi->documents()->whereHas('type', fn ($q) => $q->where('slug', 'firearm-competency'))->update(['expires_on' => now()->subDay()]);
        $this->assertNotContains('armed', $compliance->effectiveFeatures($thandi->fresh())->pluck('slug')->all());
    }

    public function test_armed_request_only_offered_to_compliant_armed_operators(): void
    {
        $armed = \App\Models\Feature::where('slug', 'armed')->value('id');
        $b = app(BookingService::class)->create($this->payload('close-protection', ['mode' => 'on_demand', 'feature_ids' => [$armed], 'duration_minutes' => 180]), $this->client());
        $offer = DispatchOffer::where('booking_id', $b->id)->first();
        $this->assertNotNull($offer);
        $this->assertContains($offer->operator->call_sign, ['BW-01', 'BW-03', 'BW-04']);
    }

    public function test_drafts_do_not_change_live_prices_until_published(): void
    {
        $service = Service::where('slug', 'secure-chauffeur')->first();
        $card = $service->rateCards()->first();
        $before = app(BookingService::class)->quote($this->payload('secure-chauffeur'))['quote']->total;

        $card->saveDraft(['per_hour' => 900] + $card->only($card->draftableAttributes()));
        $this->assertTrue($card->fresh()->hasDraft());
        $this->assertEquals($before, app(BookingService::class)->quote($this->payload('secure-chauffeur'))['quote']->total);

        $card->fresh()->publish();
        $this->assertGreaterThan($before, app(BookingService::class)->quote($this->payload('secure-chauffeur'))['quote']->total);
        $this->assertSame(2, $card->revisions()->count());
    }
}
