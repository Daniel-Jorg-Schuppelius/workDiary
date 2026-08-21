<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteFollowUpTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Models\CommunicationNote;
use App\Models\{Customer, Organization, Quote, User};
use App\Models\Notification\{NotificationDispatchLog, NotificationRule};
use App\Services\Invoicing\{QuoteFollowUpService, QuoteService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Angebots-Nachfassen (Feature 112, MVP-601): Vorbelegung beim Versand,
 * Protokollierung als Kommunikationsnotiz, Fristen-Scanner und Arbeitsliste.
 */
class QuoteFollowUpTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->org->id]);
    }

    private function rule(NotificationEvent $event): void {
        \Illuminate\Support\Facades\Notification::fake();
        NotificationRule::factory()->forEvent($event)->create([
            'organization_id' => $this->org->id,
            'channels' => [NotificationChannel::InApp->value],
            'notify_affected' => true,
        ]);
    }

    private function quote(array $attributes = []): Quote {
        return Quote::create(array_merge([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'A-' . uniqid(),
            'status' => 'sent',
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    public function test_sending_prefills_the_follow_up_date(): void {
        $quote = $this->quote(['status' => 'approved']);

        app(QuoteService::class)->send($quote, $this->admin);

        $quote->refresh();
        $this->assertNotNull($quote->follow_up_at);
        $this->assertTrue($quote->follow_up_at->isFuture());
        $this->assertSame((int) $this->admin->id, (int) $quote->follow_up_user_id);
    }

    /** Ein bereits gesetzter Termin wird beim Versand nicht überschrieben. */
    public function test_sending_keeps_an_existing_follow_up_date(): void {
        $quote = $this->quote(['status' => 'approved', 'follow_up_at' => now()->addDays(30)->toDateString()]);

        app(QuoteService::class)->send($quote, $this->admin);

        $this->assertSame(now()->addDays(30)->toDateString(), $quote->refresh()->follow_up_at?->toDateString());
    }

    public function test_recording_writes_a_communication_note_and_closes_the_follow_up(): void {
        $quote = $this->quote(['follow_up_at' => now()->subDay()->toDateString(), 'follow_up_user_id' => $this->admin->id]);

        app(QuoteFollowUpService::class)->record($quote, $this->admin, 'Kunde prüft noch intern.');

        $quote->refresh();
        $this->assertNotNull($quote->followed_up_at);
        $this->assertNull($quote->follow_up_at);
        $this->assertFalse($quote->isFollowUpDue());

        $note = CommunicationNote::query()->sole();
        // Die Notiz hängt am KUNDEN — nur dort zeigt die Oberfläche sie an.
        $this->assertSame(Customer::class, $note->notable_type);
        $this->assertSame((int) $this->customer->id, (int) $note->notable_id);
        $this->assertStringContainsString((string) $quote->number, (string) $note->subject);
        $this->assertSame('Kunde prüft noch intern.', $note->body);
    }

    /** Mit Folgetermin beginnt die Uhr neu — das Angebot bleibt offen. */
    public function test_recording_with_a_next_date_reopens_the_follow_up(): void {
        $quote = $this->quote(['follow_up_at' => now()->subDay()->toDateString()]);
        $next = now()->addDays(10)->toDateString();

        app(QuoteFollowUpService::class)->record($quote, $this->admin, 'Rückruf nächste Woche vereinbart.', $next);

        $quote->refresh();
        $this->assertSame($next, $quote->follow_up_at?->toDateString());
        $this->assertNull($quote->followed_up_at);
        $this->assertSame($next, CommunicationNote::query()->sole()->next_action_due_at?->toDateString());
    }

    public function test_recording_is_refused_for_drafts(): void {
        $quote = $this->quote(['status' => 'draft']);

        $this->expectException(\RuntimeException::class);
        app(QuoteFollowUpService::class)->record($quote, $this->admin, 'Zu früh.');
    }

    public function test_scanner_notifies_the_owner_when_the_follow_up_is_due(): void {
        $this->quote([
            'follow_up_at' => now()->subDay()->toDateString(),
            'follow_up_user_id' => $this->admin->id,
        ]);

        $this->rule(NotificationEvent::QuoteFollowUpDue);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertDatabaseHas('notification_dispatch_log', [
            'event' => NotificationEvent::QuoteFollowUpDue->value,
            'stage' => NotificationDispatchLog::STAGE_INITIAL,
        ]);
    }

    public function test_scanner_warns_about_quotes_expiring_without_reaction(): void {
        $this->quote([
            'valid_until' => now()->addDays(5)->toDateString(),
            'follow_up_user_id' => $this->admin->id,
        ]);

        $this->rule(NotificationEvent::QuoteExpiringWithoutReaction);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertDatabaseHas('notification_dispatch_log', [
            'event' => NotificationEvent::QuoteExpiringWithoutReaction->value,
            'stage' => NotificationDispatchLog::STAGE_INITIAL,
        ]);
    }

    /** Ein bereits nachgefasstes Angebot löst keine Erinnerung mehr aus. */
    public function test_followed_up_quotes_are_not_reminded_again(): void {
        $quote = $this->quote(['follow_up_at' => now()->subDay()->toDateString(), 'follow_up_user_id' => $this->admin->id]);
        app(QuoteFollowUpService::class)->record($quote, $this->admin, 'Erledigt, Kunde hat abgesagt.');

        $this->rule(NotificationEvent::QuoteFollowUpDue);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(0, NotificationDispatchLog::query()
            ->where('event', NotificationEvent::QuoteFollowUpDue->value)
            ->count());
    }

    public function test_work_list_separates_due_expiring_and_untracked(): void {
        $due = $this->quote(['number' => 'A-DUE', 'follow_up_at' => now()->subDay()->toDateString()]);
        $expiring = $this->quote(['number' => 'A-EXP', 'valid_until' => now()->addDays(3)->toDateString()]);
        $untracked = $this->quote(['number' => 'A-UNT']);

        $response = $this->actingAs($this->admin)->get(route('quotes.follow-ups.index'));

        $response->assertOk();
        $this->assertSame([$due->id], $response->viewData('due')->pluck('id')->all());
        $this->assertSame([$expiring->id], $response->viewData('expiring')->pluck('id')->all());
        $this->assertContains($untracked->id, $response->viewData('untracked')->pluck('id')->all());
    }

    public function test_work_list_can_be_limited_to_own_quotes(): void {
        $colleague = User::factory()->user()->create(['organization_id' => $this->org->id]);
        $this->quote(['number' => 'A-MINE', 'follow_up_at' => now()->subDay()->toDateString(), 'follow_up_user_id' => $this->admin->id]);
        $this->quote(['number' => 'A-THEIRS', 'follow_up_at' => now()->subDay()->toDateString(), 'follow_up_user_id' => $colleague->id]);

        $response = $this->actingAs($this->admin)->get(route('quotes.follow-ups.index', ['mine' => 1]));

        $response->assertOk();
        $this->assertCount(1, $response->viewData('due'));
    }

    public function test_endpoint_records_the_follow_up(): void {
        $quote = $this->quote(['follow_up_at' => now()->subDay()->toDateString()]);

        $this->actingAs($this->admin)
            ->post(route('quotes.follow-ups.store', $quote), ['result' => 'Angerufen, Kunde meldet sich.'])
            ->assertRedirect();

        $this->assertNotNull($quote->refresh()->followed_up_at);
    }
}
