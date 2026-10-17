<?php

namespace Tests\Feature;

use App\Jobs\EscalateSos;
use App\Jobs\ExpireDispatchOffer;
use App\Models\DispatchOffer;
use App\Models\OperatorProfile;
use App\Models\Service;
use App\Models\SosAlert;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Bus::fake([ExpireDispatchOffer::class, EscalateSos::class]);
        $this->seed(DatabaseSeeder::class);
    }

    /** Each request authenticates fresh, as it would from separate devices. */
    private function as(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function login(string $phone, string $app = 'client'): string
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $res = $this->postJson('/api/v1/auth/otp/request', ['phone' => $phone, 'app' => $app])->assertOk();
        $code = $res->json('dev_code');
        $this->assertNotNull($code, 'Codes are revealed while SMS is in test mode');

        return $this->postJson('/api/v1/auth/otp/verify', ['phone' => $phone, 'code' => $code, 'app' => $app])->assertOk()->json('token');
    }

    public function test_config_and_catalog_are_public(): void
    {
        $this->getJson('/api/v1/config')->assertOk()->assertJsonPath('region.name', 'Cape Town')->assertJsonPath('payments.record_only', true);
        $this->getJson('/api/v1/services')->assertOk()->assertJsonCount(6, 'data');
    }

    public function test_new_client_signs_up_with_phone(): void
    {
        $token = $this->login('083 555 1234');
        $this->as($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.phone', '+27835551234');
        $this->as($token)->patchJson('/api/v1/me', ['name' => 'Nomvula Dlamini', 'email' => 'nomvula@example.com', 'accept_terms' => true])
            ->assertOk()->assertJsonPath('user.terms_accepted', true);
        // Can't book before ID verification
        $this->as($token)->postJson('/api/v1/bookings', $this->booking())->assertStatus(422)->assertJsonValidationErrors('verification');
    }

    public function test_crew_login_rejects_non_operators(): void
    {
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '0820000001', 'app' => 'crew'])->assertStatus(422);
    }

    private function booking(array $over = []): array
    {
        return array_replace([
            'service_id' => Service::where('slug', 'secure-chauffeur')->value('id'),
            'mode' => 'on_demand',
            'pickup' => ['address' => 'V&A Waterfront', 'lat' => -33.9036, 'lng' => 18.4207],
            'dropoff' => ['address' => 'Camps Bay', 'lat' => -33.9510, 'lng' => 18.3780],
        ], $over);
    }

    public function test_client_and_crew_complete_a_job_over_the_api(): void
    {
        $client = $this->login('0820000001');
        $this->as($client)->postJson('/api/v1/quotes', $this->booking())->assertOk()->assertJsonStructure(['quote' => ['total', 'lines']]);
        $ref = $this->as($client)->postJson('/api/v1/bookings', $this->booking())->assertCreated()->assertJsonPath('data.status', 'searching')->json('data.reference');

        $offer = DispatchOffer::where('status', 'pending')->first();
        $opUser = $offer->operator->user;
        $crew = $this->login($opUser->phone, 'crew');
        $this->as($crew)->getJson('/api/v1/crew/feed')->assertOk()->assertJsonCount(1, 'offers');
        $jobId = $this->as($crew)->postJson("/api/v1/crew/offers/{$offer->id}/accept")->assertOk()->json('job.id');

        // Client sees the operator and PIN; operator does not see the PIN
        $show = $this->as($client)->getJson("/api/v1/bookings/{$ref}")->assertOk()->assertJsonPath('data.status', 'assigned');
        $pin = $show->json('data.arrival_pin');
        $this->assertNotNull($pin);
        $job = $this->as($crew)->getJson("/api/v1/crew/jobs/{$jobId}")->assertOk();
        $this->assertStringNotContainsString($pin, json_encode($job->json('job.pickup')));
        $this->assertArrayNotHasKey('phone', $job->json('job.principal'));

        // Chat both ways
        $this->as($client)->postJson("/api/v1/bookings/{$ref}/messages", ['body' => 'I am at the main entrance'])->assertOk();
        $this->as($crew)->getJson("/api/v1/crew/jobs/{$jobId}/messages")->assertOk()->assertJsonCount(1, 'messages');

        foreach (['en_route', 'arrived'] as $step) {
            $this->as($crew)->postJson("/api/v1/crew/jobs/{$jobId}/action", ['action' => $step])->assertOk();
        }
        $this->as($crew)->postJson("/api/v1/crew/jobs/{$jobId}/action", ['action' => 'start', 'pin' => $pin])->assertOk()->assertJsonPath('job.status', 'on_job');
        $this->as($crew)->postJson("/api/v1/crew/jobs/{$jobId}/action", ['action' => 'complete'])->assertOk();

        $this->as($client)->getJson("/api/v1/bookings/{$ref}")->assertJsonPath('data.status', 'completed')->assertJsonPath('data.can.rate', true);
        $this->as($client)->postJson("/api/v1/bookings/{$ref}/rate", ['score' => 5])->assertNoContent();
        // After the job, the operator no longer sees client details
        $this->as($crew)->getJson("/api/v1/crew/jobs/{$jobId}")->assertJsonPath('job.live', false)->assertJsonMissingPath('job.principal');
    }

    public function test_crew_token_cannot_use_client_endpoints(): void
    {
        $crew = $this->login('0820000101', 'crew');
        $this->as($crew)->getJson('/api/v1/bookings')->assertForbidden();
    }

    public function test_sos_duress_pin_keeps_alert_open(): void
    {
        $client = $this->login('0820000001');
        $this->as($client)->postJson('/api/v1/me/safety-pins', ['safety_pin' => '2468', 'duress_pin' => '8642'])->assertOk();
        $id = $this->as($client)->postJson('/api/v1/sos', ['lat' => -33.92, 'lng' => 18.42])->assertCreated()->json('id');

        $this->as($client)->postJson("/api/v1/sos/{$id}/cancel", ['pin' => '1111'])->assertStatus(422);
        $this->as($client)->postJson("/api/v1/sos/{$id}/cancel", ['pin' => '8642'])->assertOk()->assertJsonPath('status', 'cancelled');
        $this->assertSame('open', SosAlert::find($id)->status, 'Duress PIN looks like a cancel but the alert stays live');

        $this->as($client)->postJson("/api/v1/sos/{$id}/cancel", ['pin' => '2468'])->assertOk();
        $this->assertSame('cancelled', SosAlert::find($id)->status);
    }

    public function test_account_deletion(): void
    {
        $token = $this->login('083 555 9999');
        $this->as($token)->deleteJson('/api/v1/me')->assertNoContent();
        $this->assertNull(User::where('phone', '+27835559999')->first());
    }
}
