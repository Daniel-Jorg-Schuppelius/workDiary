<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyIngestTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Calendly;

use App\Models\{AppointmentRequest, Customer, IntegrationInboxItem};
use App\Plugins\Calendly\Services\CalendlyIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 095: Zustandsautomat der Calendly-Terminwünsche. Idempotenter Upsert
 * per Invitee-URI, Inbox-First bei Unzuordenbarem, Reschedule = cancel(alt) +
 * create(neu) mit URI-Verlinkung und Mapping-Vererbung.
 */
final class CalendlyIngestTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function service(): CalendlyIngestService {
        return app(CalendlyIngestService::class);
    }

    /** @return array<string, mixed> */
    private function created(string $inviteeUri, string $email = 'jane@example.com', ?string $oldInvitee = null): array {
        $invitee = [
            'uri' => $inviteeUri,
            'email' => $email,
            'name' => 'Jane Doe',
            'scheduled_event' => [
                'uri' => 'https://api.calendly.com/scheduled_events/e1',
                'name' => 'Erstberatung',
                'start_time' => '2026-08-01T10:00:00.000000Z',
                'end_time' => '2026-08-01T10:30:00.000000Z',
            ],
        ];
        if ($oldInvitee !== null) {
            $invitee['old_invitee'] = $oldInvitee;
        }

        return ['event' => 'invitee.created', 'payload' => $invitee];
    }

    /** @return array<string, mixed> */
    private function canceled(string $inviteeUri, bool $rescheduled = false, ?string $newInvitee = null): array {
        return ['event' => 'invitee.canceled', 'payload' => [
            'uri' => $inviteeUri,
            'email' => 'jane@example.com',
            'name' => 'Jane Doe',
            'status' => 'canceled',
            'rescheduled' => $rescheduled,
            'new_invitee' => $newInvitee,
            'cancellation' => ['canceled_by' => 'Jane Doe', 'reason' => 'Terminkollision', 'canceler_type' => 'invitee'],
        ]];
    }

    public function test_created_with_matching_customer_lands_as_requested(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'jane@example.com']);

        $request = $this->service()->handlePayload($this->organization, $this->created('inv-1'));

        $this->assertNotNull($request);
        $this->assertSame(AppointmentRequest::STATUS_REQUESTED, $request->status);
        $this->assertSame($customer->id, $request->customer_id);
        $this->assertSame('Erstberatung', $request->service_label);
        $this->assertNotNull($request->start_at);
        $this->assertSame(0, IntegrationInboxItem::query()->count());
    }

    public function test_created_without_match_goes_to_inbox(): void {
        $request = $this->service()->handlePayload($this->organization, $this->created('inv-2', 'unknown@example.com'));

        $this->assertNotNull($request);
        $this->assertNull($request->customer_id);
        $this->assertSame(1, IntegrationInboxItem::query()->where('case_type', IntegrationInboxItem::CASE_UNMATCHED)->count());
    }

    public function test_created_is_idempotent_per_invitee_uri(): void {
        $this->service()->handlePayload($this->organization, $this->created('inv-3'));
        $this->service()->handlePayload($this->organization, $this->created('inv-3'));

        $this->assertSame(1, AppointmentRequest::query()->where('source_uri', 'inv-3')->count());
    }

    public function test_canceled_marks_request_canceled(): void {
        $this->service()->handlePayload($this->organization, $this->created('inv-4'));
        $this->service()->handlePayload($this->organization, $this->canceled('inv-4'));

        $request = AppointmentRequest::query()->where('source_uri', 'inv-4')->firstOrFail();
        $this->assertSame(AppointmentRequest::STATUS_CANCELED, $request->status);
        $this->assertSame('Terminkollision', $request->cancellation['reason'] ?? null);
    }

    public function test_reschedule_supersedes_predecessor_and_inherits_mapping(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'jane@example.com']);

        // Ursprungsbuchung (gematcht) …
        $this->service()->handlePayload($this->organization, $this->created('inv-old'));
        // … dann Umbuchung: cancel(alt, rescheduled) + create(neu, old_invitee).
        $this->service()->handlePayload($this->organization, $this->canceled('inv-old', rescheduled: true, newInvitee: 'inv-new'));
        $successor = $this->service()->handlePayload($this->organization, $this->created('inv-new', 'unknown@example.com', oldInvitee: 'inv-old'));

        $predecessor = AppointmentRequest::query()->where('source_uri', 'inv-old')->firstOrFail();
        $this->assertSame(AppointmentRequest::STATUS_SUPERSEDED, $predecessor->status);
        $this->assertSame('inv-new', $predecessor->rescheduled_to_uri);

        $this->assertNotNull($successor);
        $this->assertSame(AppointmentRequest::STATUS_REQUESTED, $successor->status);
        $this->assertTrue($successor->is_reschedule);
        $this->assertSame('inv-old', $successor->rescheduled_from_uri);
        // Mapping vom Vorgänger geerbt, obwohl die neue Invitee-E-Mail nicht matcht.
        $this->assertSame($customer->id, $successor->customer_id);
    }
}
