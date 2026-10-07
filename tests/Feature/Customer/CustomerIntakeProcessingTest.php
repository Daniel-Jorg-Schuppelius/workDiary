<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerIntakeProcessingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Enums\Customer\{IntakeKind, IntakeStatus};
use App\Enums\Sales\QuoteStatus;
use App\Mail\CustomerIntakeNoticeMail;
use App\Models\Customer\{Customer, CustomerIntake};
use App\Models\Platform\User;
use App\Models\Sales\Quote;
use App\Models\ServiceTicket\ServiceTicket;
use App\Services\Customer\Intake\{CustomerIntakeService, IntakeTemplates};
use App\Services\Fields\{FieldDocument, FieldValues};
use App\Services\Invoicing\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Mail, Storage};
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * MVP-1075: Bearbeitung des Kundeneingangs — Rechte und Liste, Rückfrage,
 * Antwort, interne Notiz, Ablehnung, Angebotsverknüpfung und -entscheidung
 * im Portal (voll, teilweise, überholt) sowie gemeinsame Übernahmeregeln.
 */
final class CustomerIntakeProcessingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private Customer $customer;

    private User $portalUser;

    private User $lead;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        Storage::fake('local');
        Mail::fake();

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($this->customer, ['intakes', 'tickets']);
        $this->portalUser = User::factory()->kunde((int) $this->customer->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);
        $this->lead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
    }

    private function intake(IntakeKind $kind = IntakeKind::It): CustomerIntake {
        $templates = app(IntakeTemplates::class);
        $values = $kind === IntakeKind::It
            ? ['service' => 'Servermigration', 'impact' => 'planned', 'execution' => 'remote']
            : ['product' => 'Flyer', 'quantity' => 500, 'final_format' => 'a5', 'color_mode' => '4_4', 'delivery' => 'pickup'];
        $schema = $templates->schema($kind);

        return app(CustomerIntakeService::class)->submit($this->portalUser, [
            'kind' => $kind,
            'subject' => 'Anfrage ' . $kind->value,
            'description' => 'Bitte anbieten.',
            'desired_date' => null,
            'submission_key' => (string) Str::uuid(),
        ], new FieldDocument($schema, FieldValues::normalize($schema, $values)), [UploadedFile::fake()->createWithContent('diagnose.pdf', "%PDF-1.7\nlog")]);
    }

    /** @param list<array<string, mixed>> $items */
    private function sentQuote(array $items = []): Quote {
        $service = app(QuoteService::class);
        $quote = $service->create(['customer_id' => $this->customer->id], $items !== [] ? $items : [
            ['description' => 'Servermigration', 'quantity' => 1, 'unit' => 'pauschal', 'unit_price' => 1200],
        ], $this->lead);
        $service->approve($quote, $this->lead);
        $service->send($quote->refresh(), $this->lead);

        return $quote->refresh();
    }

    public function test_internal_list_requires_permission_and_limits_only_closed_intakes_by_period(): void {
        $open = $this->intake();
        $open->forceFill(['created_at' => now()->subYears(2)])->save();
        $closed = CustomerIntake::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'status' => IntakeStatus::Withdrawn,
            'closed_at' => now()->subYears(2),
        ]);

        $this->actingAs(User::factory()->user()->create(['organization_id' => $this->organization->id]), 'web')
            ->get(route('customer-intakes.index'))->assertForbidden();

        $this->actingAs($this->lead, 'web')->get(route('customer-intakes.index'))
            ->assertOk()
            ->assertSee($open->number)
            ->assertDontSee($closed->number);
        $this->actingAs($this->lead, 'web')->get(route('customer-intakes.show', $open))->assertOk()->assertSee('diagnose.pdf');
    }

    public function test_question_reply_and_internal_note_keep_their_visibility(): void {
        $intake = $this->intake();

        $this->actingAs($this->lead, 'web')->post(route('customer-intakes.message', $intake), [
            'kind' => 'question',
            'body' => 'Welches Betriebssystem läuft aktuell?',
            'uploads' => [UploadedFile::fake()->createWithContent('checkliste.pdf', "%PDF-1.7\nq")],
        ])->assertRedirect(route('customer-intakes.show', $intake));
        $this->assertSame(IntakeStatus::AwaitingCustomer, $intake->fresh()?->status);
        Mail::assertSent(CustomerIntakeNoticeMail::class, fn (CustomerIntakeNoticeMail $mail): bool => $mail->notice === 'question');
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.dashboard'))->assertOk()->assertSee('Davon warten 1 auf Sie');

        $this->actingAs($this->lead, 'web')->post(route('customer-intakes.message', $intake), [
            'kind' => 'note',
            'body' => 'Interne Kalkulation: 8 Stunden',
            'uploads' => [UploadedFile::fake()->createWithContent('kalkulation.pdf', "%PDF-1.7\nk")],
        ])->assertRedirect();
        $noteFile = $intake->attachments()->where('original_name', 'kalkulation.pdf')->firstOrFail();
        $this->assertFalse((bool) $noteFile->customer_visible);

        $this->actingAs($this->portalUser, 'customer')->get(route('customer.intakes.show', $intake))
            ->assertOk()
            ->assertSee('Welches Betriebssystem läuft aktuell?')
            ->assertSee('checkliste.pdf')
            ->assertDontSee('Interne Kalkulation')
            ->assertDontSee('kalkulation.pdf');
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.intakes.files.download', [$intake, $noteFile]))->assertNotFound();

        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.reply', $intake), ['body' => 'Windows Server 2016.'])->assertRedirect();
        $this->assertSame(IntakeStatus::InProgress, $intake->refresh()->status);
        $this->actingAs($this->lead, 'web')->get(route('customer-intakes.show', $intake))->assertSee('Windows Server 2016.');
    }

    public function test_reject_needs_a_reason_the_customer_can_read(): void {
        $intake = $this->intake();

        $this->actingAs($this->lead, 'web')->post(route('customer-intakes.reject', $intake), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->lead, 'web')->post(route('customer-intakes.reject', $intake), ['reason' => 'Diese Leistung bieten wir nicht an.'])->assertRedirect();

        $this->assertSame(IntakeStatus::Rejected, $intake->fresh()?->status);
        Mail::assertSent(CustomerIntakeNoticeMail::class, fn (CustomerIntakeNoticeMail $mail): bool => $mail->notice === 'rejected');
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.intakes.show', $intake))->assertSee('Diese Leistung bieten wir nicht an.');
    }

    public function test_only_own_customer_quotes_can_be_linked_and_new_quote_is_linked(): void {
        $intake = $this->intake();
        $foreign = app(QuoteService::class)->create(['customer_id' => Customer::factory()->create(['organization_id' => $this->organization->id])->id], [], $this->lead);

        $this->actingAs($this->lead, 'web')->post(route('customer-intakes.quote.link', $intake), ['quote_id' => $foreign->sqid])->assertSessionHasErrors('quote_id');
        $this->assertNull($intake->fresh()?->quote_id);

        $admin = $this->orgAdmin();
        $response = $this->actingAs($admin, 'web')->post(route('customer-intakes.quote.create', $intake));
        $quote = Quote::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('quotes.show', $quote));
        $this->assertSame((int) $quote->id, (int) $intake->fresh()?->quote_id);
        $this->assertSame((int) $this->customer->id, (int) $quote->customer_id);
        $this->actingAs($admin, 'web')->get(route('quotes.show', $quote))->assertSee($intake->number);
    }

    public function test_customer_decides_only_a_sent_current_quote_in_the_portal(): void {
        $intake = $this->intake();
        $draft = app(QuoteService::class)->create(['customer_id' => $this->customer->id], [['description' => 'Migration', 'quantity' => 1, 'unit_price' => 900]], $this->lead);
        $this->actingAs($this->lead, 'web')->post(route('customer-intakes.quote.link', $intake), ['quote_id' => $draft->sqid])->assertRedirect();

        $this->actingAs($this->portalUser, 'customer')->get(route('customer.intakes.show', $intake))->assertDontSee('Migration');
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.quote.decide', $intake), ['decision' => 'accept'])->assertSessionHasErrors('decision');

        app(QuoteService::class)->approve($draft, $this->lead);
        app(QuoteService::class)->send($draft->refresh(), $this->lead);
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.intakes.show', $intake))->assertSee('Migration')->assertSee('Angebot annehmen');

        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.quote.decide', $intake), ['decision' => 'accept'])->assertRedirect();
        $this->assertSame(QuoteStatus::Accepted, $draft->fresh()?->status);
        $this->assertContains('quote_accepted', $intake->journal()->get()->map->eventKey()->all());
        $this->assertTrue($intake->fresh()?->status->isOpen());

        // Nach der Annahme keine Rücknahme und keine Ablehnung mehr.
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.withdraw', $intake))->assertSessionHasErrors('status');
        $this->actingAs($this->lead, 'web')->post(route('customer-intakes.reject', $intake), ['reason' => 'zu spät'])->assertSessionHasErrors('reason');
    }

    public function test_superseded_version_cannot_be_decided_or_handed_over(): void {
        $intake = $this->intake();
        $quote = $this->sentQuote();
        $this->actingAs($this->lead, 'web')->post(route('customer-intakes.quote.link', $intake), ['quote_id' => $quote->sqid])->assertRedirect();

        app(QuoteService::class)->newVersion($quote, $this->lead);

        $this->actingAs($this->portalUser, 'customer')->get(route('customer.intakes.show', $intake))->assertSee('überarbeitet');
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.quote.decide', $intake), ['decision' => 'accept'])->assertSessionHasErrors('decision');
        $this->assertSame(QuoteStatus::Sent, $quote->fresh()?->status);
    }

    public function test_handover_rules_partial_scope_and_idempotency(): void {
        $intake = $this->intake();
        $quote = $this->sentQuote([
            ['description' => 'Migration', 'quantity' => 1, 'unit_price' => 1200],
            ['description' => 'Schulung', 'quantity' => 2, 'unit_price' => 150],
        ]);

        $this->actingAs($this->lead, 'web')->post(route('customer-intakes.handover', $intake))->assertSessionHasErrors('handover');

        $this->actingAs($this->lead, 'web')->post(route('customer-intakes.quote.link', $intake), ['quote_id' => $quote->sqid]);
        $this->actingAs($this->lead, 'web')->post(route('customer-intakes.handover', $intake))->assertSessionHasErrors('handover');

        // Teilannahme: nur die zweite Pflichtposition gewählt.
        $second = $quote->items()->where('description', 'Schulung')->firstOrFail();
        $this->actingAs($this->portalUser, 'customer')->post(route('customer.intakes.quote.decide', $intake), ['decision' => 'accept', 'item_ids' => [$second->sqid]]);
        $this->assertSame(QuoteStatus::PartiallyAccepted, $quote->fresh()?->status);

        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($member, 'web')->post(route('customer-intakes.handover', $intake))->assertForbidden();

        $this->actingAs($this->lead, 'web')->post(route('customer-intakes.handover', $intake))->assertSessionHasErrors('scope_note');
        $this->actingAs($this->lead, 'web')->post(route('customer-intakes.handover', $intake), ['scope_note' => 'Nur Schulung beauftragt, telefonisch bestätigt.'])->assertRedirect(route('customer-intakes.show', $intake));

        $intake->refresh();
        $this->assertSame(IntakeStatus::HandedOver, $intake->status);
        $ticket = ServiceTicket::query()->findOrFail($intake->target_id);
        $this->assertSame($ticket->getMorphClass(), $intake->target_type);

        // Wiederholung erzeugt keinen zweiten Zielvorgang.
        $this->actingAs($this->lead, 'web')->post(route('customer-intakes.handover', $intake))->assertRedirect();
        $this->assertSame(1, ServiceTicket::query()->count());
        Mail::assertSent(CustomerIntakeNoticeMail::class, fn (CustomerIntakeNoticeMail $mail): bool => $mail->notice === 'handed_over');
    }

    public function test_old_rejected_intakes_are_proposed_and_purged_with_their_files(): void {
        $old = $this->intake();
        $old->forceFill(['status' => IntakeStatus::Rejected, 'rejection_reason' => 'Nicht im Angebot', 'closed_at' => now()->subYears(4)])->save();
        $path = $old->attachments()->firstOrFail()->path;
        $fresh = $this->intake();
        $fresh->forceFill(['status' => IntakeStatus::Withdrawn, 'closed_at' => now()->subMonth()])->save();
        $handedOver = $this->intake();
        $handedOver->forceFill(['status' => IntakeStatus::HandedOver, 'closed_at' => now()->subYears(4)])->save();

        $scan = app(\App\Services\Retention\RetentionScanService::class);
        $scan->scan($this->organization);
        $proposals = \App\Models\Privacy\RetentionProposal::query()->where('area', 'customer_intakes')->get();
        $this->assertCount(1, $proposals);
        $this->assertSame((int) $old->id, (int) $proposals->first()?->subject_id);

        $admin = $this->orgAdmin();
        $scan->approve($proposals->first(), $admin);
        $scan->purge($proposals->first()->fresh(), $admin);

        $this->assertNull(CustomerIntake::query()->withoutGlobalScopes()->find($old->id));
        $this->assertFalse(Storage::disk('local')->exists($path));
        $this->assertNotNull($fresh->fresh());
        $this->assertNotNull($handedOver->fresh());
    }
}
