<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CtiWebhookTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Cti;

use App\Models\{CommunicationNote, CtiConnection, Customer, ExternalReference, User};
use App\Services\Cti\{CtiCallService, CtiNormalizerResolver, GenericNormalizer, PlacetelNormalizer, SipgateNormalizer, StarfaceNormalizer};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 056, MVP-118: CTI-Webhook. Prüft Token-Auth, Nummer→Kunde-Match und
 * die Protokollierung als Kommunikationseintrag (nur Metadaten), Idempotenz je
 * Call-ID, das Ignorieren unbekannter Anrufer/Zwischenzustände.
 */
final class CtiWebhookTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private string $token;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $user->id])->save();

        [, $this->token] = CtiConnection::issue($this->organization->id, 'Zentrale', 'generic');
    }

    private function customer(string $phone = '+493012345678'): Customer {
        return Customer::factory()->create(['organization_id' => $this->organization->id, 'phone' => $phone]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<\Illuminate\Http\Response>
     */
    private function webhook(array $payload, ?string $token = null): TestResponse {
        return $this->postJson('/api/cti/webhook/' . ($token ?? $this->token), $payload);
    }

    /** @return array<string, mixed> */
    private function inboundPayload(string $callId = 'c-1', string $from = '+493012345678'): array {
        return ['call_id' => $callId, 'direction' => 'inbound', 'from' => $from, 'to' => '+4930999000'];
    }

    public function test_invalid_token_is_rejected(): void {
        $this->webhook($this->inboundPayload(), token: 'cti_wrong')->assertStatus(404);
    }

    public function test_known_inbound_call_is_logged_without_content(): void {
        $customer = $this->customer();

        $this->webhook($this->inboundPayload('c-1'))
            ->assertOk()
            ->assertJsonPath('status', 'recorded');

        $note = CommunicationNote::query()->firstOrFail();
        $this->assertSame('call', $note->type->value);
        $this->assertSame('inbound', $note->direction->value);
        $this->assertSame($customer->getMorphClass(), $note->notable_type);
        $this->assertSame($customer->id, $note->notable_id);
        $this->assertSame('', (string) $note->body); // keine Inhalte

        $this->assertSame(1, ExternalReference::query()
            ->where('plugin_id', CtiCallService::PLUGIN_ID)
            ->where('external_type', CtiCallService::EXTERNAL_TYPE)
            ->where('external_id', 'c-1')
            ->count());
    }

    public function test_replay_is_idempotent(): void {
        $this->customer();

        $this->webhook($this->inboundPayload('dup'))->assertJsonPath('status', 'recorded');
        $this->webhook($this->inboundPayload('dup'))->assertJsonPath('status', 'skipped');

        $this->assertSame(1, CommunicationNote::query()->count());
    }

    public function test_unknown_caller_is_not_logged(): void {
        $this->customer('+493012345678');

        $this->webhook($this->inboundPayload('c-2', from: '+499999999999'))
            ->assertJsonPath('status', 'unmatched');

        $this->assertSame(0, CommunicationNote::query()->count());
    }

    public function test_resolver_selects_normalizer_by_provider_with_generic_fallback(): void {
        $resolver = new CtiNormalizerResolver();

        $this->assertInstanceOf(SipgateNormalizer::class, $resolver->for('sipgate'));
        $this->assertInstanceOf(PlacetelNormalizer::class, $resolver->for('placetel'));
        $this->assertInstanceOf(StarfaceNormalizer::class, $resolver->for('starface'));
        $this->assertInstanceOf(GenericNormalizer::class, $resolver->for('unknown-pbx'));
    }

    public function test_placetel_normalizer_maps_hungup_payload(): void {
        [, $placetelToken] = CtiConnection::issue($this->organization->id, 'Placetel', 'placetel');
        $this->customer();

        // Zwischenzustand (IncomingCall) wird ignoriert.
        $this->webhook(['event' => 'IncomingCall', 'call_id' => 'pt-1', 'direction' => 'in', 'from' => '+493012345678', 'to' => '+4930999'], token: $placetelToken)
            ->assertJsonPath('status', 'ignored');
        $this->assertSame(0, CommunicationNote::query()->count());

        // Terminales HungUp: Nummer, Richtung und Dauer werden übernommen.
        $this->webhook(['event' => 'HungUp', 'call_id' => 'pt-1', 'direction' => 'in', 'from' => '+493012345678', 'to' => '+4930999', 'duration' => 95], token: $placetelToken)
            ->assertJsonPath('status', 'recorded');

        $note = CommunicationNote::query()->firstOrFail();
        $this->assertSame('inbound', $note->direction->value);

        $reference = ExternalReference::query()
            ->where('plugin_id', CtiCallService::PLUGIN_ID)
            ->where('external_id', 'pt-1')
            ->firstOrFail();
        $this->assertSame('inbound', $reference->payload['direction']);
        $this->assertSame('+493012345678', $reference->payload['number']);
        $this->assertSame(95, $reference->payload['duration_seconds']);
    }

    public function test_starface_normalizer_maps_hangup_payload(): void {
        [, $starfaceToken] = CtiConnection::issue($this->organization->id, 'STARFACE', 'starface');
        $this->customer();

        // Zwischenzustand (RINGING) wird ignoriert.
        $this->webhook(['callState' => 'RINGING', 'callId' => 'sf-1', 'direction' => 'INBOUND', 'callerNumber' => '+493012345678', 'calledNumber' => '+4930999'], token: $starfaceToken)
            ->assertJsonPath('status', 'ignored');
        $this->assertSame(0, CommunicationNote::query()->count());

        // Terminales HANGUP eines AUSGEHENDEN Anrufs: Gegenstelle = gewählte Nummer.
        $this->webhook(['callState' => 'HANGUP', 'callId' => 'sf-1', 'direction' => 'OUTBOUND', 'callerNumber' => '+4930999', 'calledNumber' => '+493012345678', 'durationInSeconds' => 42], token: $starfaceToken)
            ->assertJsonPath('status', 'recorded');

        $note = CommunicationNote::query()->firstOrFail();
        $this->assertSame('outbound', $note->direction->value);

        $reference = ExternalReference::query()
            ->where('plugin_id', CtiCallService::PLUGIN_ID)
            ->where('external_id', 'sf-1')
            ->firstOrFail();
        $this->assertSame('outbound', $reference->payload['direction']);
        $this->assertSame('+493012345678', $reference->payload['number']);
        $this->assertSame(42, $reference->payload['duration_seconds']);
    }

    public function test_sipgate_logs_only_terminal_hangup(): void {
        [, $sipgateToken] = CtiConnection::issue($this->organization->id, 'sipgate', 'sipgate');
        $this->customer();

        // Zwischenzustand wird ignoriert.
        $this->webhook(['event' => 'newCall', 'callId' => 'sg-1', 'direction' => 'in', 'from' => '+493012345678', 'to' => '+4930999'], token: $sipgateToken)
            ->assertJsonPath('status', 'ignored');
        $this->assertSame(0, CommunicationNote::query()->count());

        // Abgeschlossener Anruf wird protokolliert.
        $this->webhook(['event' => 'hangup', 'callId' => 'sg-1', 'direction' => 'in', 'from' => '+493012345678', 'to' => '+4930999'], token: $sipgateToken)
            ->assertJsonPath('status', 'recorded');
        $this->assertSame(1, CommunicationNote::query()->count());
    }
}
