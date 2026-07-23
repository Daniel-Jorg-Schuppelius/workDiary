<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyConfirmTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Calendly;

use App\Enums\Diary\Status;
use App\Models\{AppointmentRequest, Customer, DiaryEntry, DiaryEntryEvent, IntegrationInboxItem, User};
use App\Plugins\Calendly\Services\CalendlyConfirmService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 095: zweiphasige Bestätigung. Erst die interne Bestätigung erzeugt den
 * DiaryEntry + bestätigt die Disposition; Absage storniert ihn als echten
 * Storno über den OrderService (guard-geschützt → Inbox-Konflikt bei bereits
 * fortgeschrittenem Auftrag).
 */
final class CalendlyConfirmTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        Mail::fake();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'jane@example.com']);
    }

    private function service(): CalendlyConfirmService {
        return app(CalendlyConfirmService::class);
    }

    private function pendingRequest(): AppointmentRequest {
        return AppointmentRequest::query()->create([
            'organization_id' => $this->organization->id,
            'source' => AppointmentRequest::SOURCE_CALENDLY,
            'source_uri' => 'inv-confirm-' . uniqid(),
            'status' => AppointmentRequest::STATUS_REQUESTED,
            'customer_id' => $this->customer->id,
            'invitee_name' => 'Jane Doe',
            'invitee_email' => 'jane@example.com',
            'service_label' => 'Erstberatung',
            'start_at' => CarbonImmutable::parse('2026-08-01T10:00:00Z'),
            'end_at' => CarbonImmutable::parse('2026-08-01T10:30:00Z'),
        ]);
    }

    public function test_confirm_creates_diary_entry_and_confirms_dispatch(): void {
        $request = $this->pendingRequest();

        $entry = $this->service()->confirm($request, $this->admin);

        $this->assertInstanceOf(DiaryEntry::class, $entry);
        $this->assertSame($this->customer->id, $entry->customer_id);
        $this->assertSame(Status::Open, $entry->status);
        $this->assertSame((int) $this->admin->id, (int) $entry->planned_by_user_id);
        $this->assertSame('confirmed', $entry->fresh()?->getAttribute('dispatch_status'));
        $this->assertNotNull($entry->fresh()?->getAttribute('dispatch_confirmed_at'));

        $this->assertSame(1, DiaryEntryEvent::query()->where('event', 'dispatch.calendly_confirmed')->count());

        $request->refresh();
        $this->assertSame(AppointmentRequest::STATUS_CONFIRMED, $request->status);
        $this->assertSame($entry->id, $request->diary_entry_id);
        $this->assertSame((int) $this->admin->id, (int) $request->decided_by);
    }

    public function test_release_cancels_the_diary_entry(): void {
        $request = $this->pendingRequest();
        $entry = $this->service()->confirm($request, $this->admin);
        $this->assertInstanceOf(DiaryEntry::class, $entry);

        $this->service()->release($request->fresh() ?? $request, 'Vom Kunden abgesagt');

        $entry->refresh();
        $this->assertSame(Status::Cancelled, $entry->status);
        $this->assertNotNull($entry->cancelled_at);
    }

    public function test_release_of_advanced_order_creates_conflict_instead_of_forcing(): void {
        $request = $this->pendingRequest();
        $entry = $this->service()->confirm($request, $this->admin);
        $this->assertInstanceOf(DiaryEntry::class, $entry);

        // Auftrag ist bereits berechnet → nicht stornierbar (Guard).
        $entry->forceFill(['status' => Status::Invoiced])->save();

        $this->service()->release($request->fresh() ?? $request, 'Absage');

        $this->assertSame(Status::Invoiced, $entry->fresh()?->status);
        $this->assertSame(1, IntegrationInboxItem::query()->where('case_type', IntegrationInboxItem::CASE_CONFLICT)->count());
    }

    public function test_decline_marks_request_declined_without_entry(): void {
        $request = $this->pendingRequest();

        $this->service()->decline($request, $this->admin, 'Keine Kapazität');

        $request->refresh();
        $this->assertSame(AppointmentRequest::STATUS_DECLINED, $request->status);
        $this->assertSame('Keine Kapazität', $request->decline_reason);
        $this->assertSame(0, DiaryEntry::query()->count());
    }
}
