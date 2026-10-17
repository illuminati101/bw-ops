<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Jobs\EscalateSos;
use App\Jobs\ExpireDispatchOffer;
use App\Livewire\Account\BookingDetail;
use App\Livewire\Account\BookingWizard;
use App\Livewire\Account\PaymentMethods;
use App\Livewire\Account\Profile;
use App\Models\Booking;
use App\Models\Service;
use App\Models\SosAlert;
use App\Models\User;
use App\Services\Booking\BookingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Bus::fake([ExpireDispatchOffer::class, EscalateSos::class]);
        $this->seed(DatabaseSeeder::class);
    }

    private function client(): User
    {
        return User::where('phone', '+27820000001')->firstOrFail();
    }

    private function booking(): Booking
    {
        return app(BookingService::class)->create([
            'service_id' => Service::where('slug', 'secure-chauffeur')->value('id'),
            'mode' => 'advance',
            'starts_at' => now()->addDays(3)->setTime(10, 0)->toIso8601String(),
            'duration_minutes' => 120,
            'pickup' => ['address' => 'V&A Waterfront, Cape Town', 'lat' => -33.9036, 'lng' => 18.4207],
        ], $this->client());
    }

    public function test_account_pages_render_for_a_client(): void
    {
        $b = $this->booking();
        $this->actingAs($this->client());
        foreach (['/account', '/account/bookings/'.$b->reference, '/account/profile', '/account/payment', '/account/verify', '/book'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/account')->assertRedirect('/account/login');
    }

    public function test_staff_are_sent_to_the_admin_panel(): void
    {
        $this->actingAs(User::where('email', 'admin@blackandwhiteops.com')->first())->get('/account')->assertRedirect('/admin');
    }

    public function test_wizard_quotes_and_books(): void
    {
        $this->actingAs($this->client());
        $c = Livewire::test(BookingWizard::class)
            ->set('serviceId', Service::where('slug', 'secure-chauffeur')->value('id'))
            ->call('setPlace', 'pickup', ['address' => 'V&A Waterfront', 'lat' => -33.9036, 'lng' => 18.4207])
            ->set('date', now()->addDays(4)->toDateString())
            ->set('time', '11:00');
        $this->assertNotNull($c->get('quote'));
        $this->assertGreaterThan(0, $c->get('quote')['total']);
        $c->call('confirm')->assertRedirect();
        $this->assertSame(1, Booking::where('client_id', $this->client()->id)->where('starts_at', now()->addDays(4)->setTime(11, 0))->count());
    }

    public function test_guest_draft_survives_sign_in(): void
    {
        Livewire::test(BookingWizard::class)
            ->call('setPlace', 'pickup', ['address' => 'Camps Bay', 'lat' => -33.951, 'lng' => 18.377])
            ->call('confirm')->assertRedirect(route('account.login'));
        $this->assertSame('Camps Bay', session('booking_draft')['pickup']['address']);
    }

    public function test_client_can_cancel_with_fee_preview(): void
    {
        $b = $this->booking();
        $this->actingAs($this->client());
        Livewire::test(BookingDetail::class, ['reference' => $b->reference])
            ->assertSee('Cancel booking')->call('cancel');
        $this->assertSame(BookingStatus::Cancelled, $b->fresh()->status);
        $this->assertEquals(0, $b->fresh()->cancellation_fee, 'Three days ahead is free');
    }

    public function test_other_clients_cannot_see_a_booking(): void
    {
        $b = $this->booking();
        $this->actingAs(User::where('phone', '+27820000003')->first())->get('/account/bookings/'.$b->reference)->assertNotFound();
    }

    public function test_trip_share_link_shows_status_without_full_address(): void
    {
        $b = $this->booking();
        $this->actingAs($this->client());
        $c = Livewire::test(BookingDetail::class, ['reference' => $b->reference])->call('share');
        $url = $c->get('shareUrl');
        $this->assertNotNull($url);
        auth()->logout();
        $path = parse_url($url, PHP_URL_PATH);
        $this->get($path)->assertOk()->assertDontSee('V&amp;A Waterfront, Cape Town', false);
        $this->getJson($path.'/data')->assertOk()->assertJsonPath('status', $b->status->value);
    }

    public function test_safety_pins_and_duress_cancel_look_identical(): void
    {
        $user = $this->client();
        $this->actingAs($user);
        Livewire::test(Profile::class)->set('safetyPin', '1234')->set('duressPin', '9876')->call('savePins')->assertHasNoErrors();
        $this->assertTrue(Hash::check('9876', $user->fresh()->duress_pin_hash));

        $b = $this->booking();
        $b->update(['status' => BookingStatus::EnRoute]);
        $c = Livewire::test(BookingDetail::class, ['reference' => $b->reference])->call('raiseSos', -33.9, 18.4);
        $sos = SosAlert::firstOrFail();
        $c->set('sosPin', '9876')->call('cancelSos')->assertSet('sosId', null)->assertSee('Alert cancelled');
        $this->assertSame('open', $sos->fresh()->status, 'Duress keeps the alert live');
    }

    public function test_record_only_card_setup_saves_placeholder(): void
    {
        $user = User::where('phone', '+27820000003')->first();
        $user->paymentMethods()->delete();
        $this->actingAs($user);
        Livewire::test(PaymentMethods::class)->call('addCard');
        $this->assertSame(1, $user->paymentMethods()->count());
    }

    public function test_payment_callback_rejects_open_redirects(): void
    {
        $this->get('/payments/callback?reference=nope&return=https://evil.example/x')
            ->assertRedirectContains(url('/payments/return'));
    }

    public function test_webhook_requires_a_valid_signature(): void
    {
        config(['bwops.integrations.paystack.secret_key' => 'sk_test_x']);
        $this->postJson('/payments/webhook', ['event' => 'charge.success'])->assertStatus(401);
        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => 'none']]);
        $this->call('POST', '/payments/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_x')], $body)->assertOk();
    }
}
