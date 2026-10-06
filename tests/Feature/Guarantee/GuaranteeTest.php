<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GuaranteeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Guarantee;

use App\Enums\Guarantee\{GuaranteeDirection, GuaranteeKind, GuaranteeStatus};
use App\Enums\Invoicing\InvoiceStatus;
use App\Enums\Invoicing\{RetentionKind, RetentionStatus};
use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Models\Customer\Customer;
use App\Models\Guarantee\Guarantee;
use App\Models\Invoicing\Invoice;
use App\Models\Notification\{NotificationDispatchLog, NotificationRule};
use App\Models\Platform\{Organization, User};
use App\Services\Guarantee\GuaranteeService;
use App\Services\Invoicing\RetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bürgschaftsregister (Feature 114, MVP-603): Ablösung eines
 * Sicherheitseinbehalts, Rückgabe-Nachweis und die beiden gegenläufigen
 * Fristen-Alarme.
 */
class GuaranteeTest extends TestCase {
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

    /** @param array<string, mixed> $attributes */
    private function guarantee(array $attributes = []): Guarantee {
        return Guarantee::query()->create(array_merge([
            'organization_id' => $this->org->id,
            'direction' => GuaranteeDirection::Received->value,
            'kind' => GuaranteeKind::Warranty->value,
            'reference' => 'BG-' . uniqid(),
            'amount' => '500.00',
            'currency' => 'EUR',
            'issuer_name' => 'Musterbank AG',
            'customer_id' => $this->customer->id,
            'responsible_user_id' => $this->admin->id,
        ], $attributes));
    }

    /** @return array{0: Invoice, 1: \App\Models\Invoicing\InvoiceRetention} */
    private function invoiceWithRetention(float $percent = 5.0): array {
        $invoice = Invoice::create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'R-' . uniqid(),
            'status' => InvoiceStatus::Draft,
            'currency' => 'EUR',
            'tax_rate' => '0.00',
            'created_by' => $this->admin->id,
        ]);
        $invoice->items()->create([
            'organization_id' => $this->org->id,
            'description' => 'Rohbau',
            'quantity' => '1.000',
            'unit_price' => '10000.0000',
            'tax_rate' => '0.00',
            'position' => 1,
        ]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();

        $retention = app(RetentionService::class)->add($invoice->refresh(), RetentionKind::Warranty, $percent, null, now()->addYears(5)->toDateString(), $this->admin);

        return [$invoice->refresh(), $retention];
    }

    public function test_guarantee_replaces_a_retention_and_pays_it_out(): void {
        [$invoice, $retention] = $this->invoiceWithRetention();
        $guarantee = $this->guarantee();

        app(GuaranteeService::class)->secureRetention($guarantee, $retention, $this->admin);

        $this->assertSame(RetentionStatus::Secured, $retention->refresh()->status);
        $this->assertNotNull($retention->released_on);
        $this->assertSame((int) $retention->id, (int) $guarantee->refresh()->invoice_retention_id);
        // Der Einbehalt mindert den offenen Posten nicht mehr — er wurde ausgezahlt.
        $this->assertSame(0.0, app(RetentionService::class)->openAmountOf($invoice->refresh()));
    }

    /** Eine zu kleine Bürgschaft löst den Einbehalt NICHT ab. */
    public function test_a_guarantee_below_the_retention_is_refused(): void {
        [, $retention] = $this->invoiceWithRetention();
        $guarantee = $this->guarantee(['amount' => '100.00']);

        $this->expectException(\RuntimeException::class);
        app(GuaranteeService::class)->secureRetention($guarantee, $retention, $this->admin);
    }

    public function test_a_retention_can_only_be_replaced_once(): void {
        [, $retention] = $this->invoiceWithRetention();
        $service = app(GuaranteeService::class);
        $service->secureRetention($this->guarantee(), $retention, $this->admin);

        $this->expectException(\RuntimeException::class);
        $service->secureRetention($this->guarantee(), $retention->refresh(), $this->admin);
    }

    public function test_return_is_recorded_with_a_date(): void {
        $guarantee = $this->guarantee();

        app(GuaranteeService::class)->markReturned($guarantee, $this->admin, 'Original per Bote abgeholt.');

        $guarantee->refresh();
        $this->assertSame(GuaranteeStatus::Returned, $guarantee->status);
        $this->assertSame(now()->toDateString(), $guarantee->returned_on?->toDateString());
        $this->assertSame('Original per Bote abgeholt.', $guarantee->returned_note);
    }

    public function test_a_returned_guarantee_cannot_be_drawn(): void {
        $guarantee = $this->guarantee();
        app(GuaranteeService::class)->markReturned($guarantee, $this->admin);

        $this->expectException(\RuntimeException::class);
        app(GuaranteeService::class)->markDrawn($guarantee->refresh(), $this->admin);
    }

    public function test_expiry_reminder_fires_for_active_guarantees(): void {
        $this->rule(NotificationEvent::GuaranteeExpiring);
        $this->guarantee(['expires_on' => now()->addDays(10)->toDateString()]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertDatabaseHas('notification_dispatch_log', [
            'event' => NotificationEvent::GuaranteeExpiring->value,
        ]);
    }

    /** Freigegebener Einbehalt ⇒ die Urkunde gehört zurück. */
    public function test_return_reminder_fires_once_the_replaced_retention_is_released(): void {
        $this->rule(NotificationEvent::GuaranteeReturnDue);
        [, $retention] = $this->invoiceWithRetention();
        $guarantee = $this->guarantee();
        app(GuaranteeService::class)->secureRetention($guarantee, $retention, $this->admin);

        // Solange der Einbehalt „secured" ist, gibt es nichts zurückzufordern.
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->assertSame(0, NotificationDispatchLog::query()
            ->where('event', NotificationEvent::GuaranteeReturnDue->value)->count());

        $retention->refresh()->forceFill(['status' => RetentionStatus::Released->value])->save();
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertDatabaseHas('notification_dispatch_log', [
            'event' => NotificationEvent::GuaranteeReturnDue->value,
        ]);
    }

    public function test_index_lists_and_filters(): void {
        $this->guarantee(['direction' => GuaranteeDirection::Issued->value, 'reference' => 'BG-ISSUED']);
        $this->guarantee(['direction' => GuaranteeDirection::Received->value, 'reference' => 'BG-RECEIVED']);

        $response = $this->actingAs($this->admin)->get(route('guarantees.index', ['direction' => GuaranteeDirection::Issued->value]));

        $response->assertOk();
        $response->assertSee('BG-ISSUED');
        $response->assertDontSee('BG-RECEIVED');
        $this->assertSame(1, $response->viewData('activeIssued'));
        $this->assertSame(1, $response->viewData('activeReceived'));
    }

    public function test_endpoint_creates_a_guarantee_from_sqid_inputs(): void {
        $this->actingAs($this->admin)->post(route('guarantees.store'), [
            'direction' => GuaranteeDirection::Received->value,
            'kind' => GuaranteeKind::Performance->value,
            'reference' => 'BG-NEU',
            'amount' => '2500',
            'issued_on' => now()->toDateString(),
            'expires_on' => now()->addYears(2)->toDateString(),
            'issuer_name' => 'Musterbank AG',
            'customer_id' => $this->customer->sqid,
        ])->assertRedirect(route('guarantees.index'));

        $guarantee = Guarantee::query()->where('reference', 'BG-NEU')->sole();
        $this->assertSame((int) $this->customer->id, (int) $guarantee->customer_id);
        $this->assertSame('2500.00', $guarantee->amount->getAmount());
    }

    /** Ziehen und Ablösen stehen nur an aktiven Bürgschaften. */
    public function test_index_offers_draw_and_secure_only_for_active_guarantees(): void {
        $active = $this->guarantee();
        $returned = $this->guarantee(['status' => GuaranteeStatus::Returned->value]);

        $this->actingAs($this->admin)->get(route('guarantees.index', ['status' => '']))
            ->assertOk()
            ->assertSee(route('guarantees.drawn', $active), false)
            ->assertSee(route('guarantees.secure-dialog', $active), false)
            ->assertDontSee(route('guarantees.drawn', $returned), false)
            ->assertDontSee(route('guarantees.secure-dialog', $returned), false);
    }

    public function test_endpoint_records_the_drawing(): void {
        $guarantee = $this->guarantee();

        $this->actingAs($this->admin)->from(route('guarantees.index'))
            ->post(route('guarantees.drawn', $guarantee), ['note' => 'Mängel nicht beseitigt.'])
            ->assertRedirect(route('guarantees.index'))
            ->assertSessionHas('status', __('guarantee.drawn'));

        $guarantee->refresh();
        $this->assertSame(GuaranteeStatus::Drawn, $guarantee->status);
        $this->assertSame('Mängel nicht beseitigt.', $guarantee->returned_note);
    }

    /** Der Dialog bietet nur an, was der Dienst annimmt: offen und von der Bürgschaft gedeckt. */
    public function test_secure_dialog_offers_only_open_retentions_the_guarantee_covers(): void {
        [, $covered] = $this->invoiceWithRetention(5.0);
        [, $tooLarge] = $this->invoiceWithRetention(10.0);
        [, $released] = $this->invoiceWithRetention(5.0);
        $released->forceFill(['status' => RetentionStatus::Released->value])->save();
        $guarantee = $this->guarantee();

        $this->actingAs($this->admin)->get(route('guarantees.secure-dialog', $guarantee))
            ->assertOk()
            ->assertSee(route('guarantees.secure', $guarantee), false)
            ->assertSee('value="' . $covered->sqid . '"', false)
            ->assertDontSee('value="' . $tooLarge->sqid . '"', false)
            ->assertDontSee('value="' . $released->sqid . '"', false);
    }

    public function test_secure_dialog_has_no_form_without_a_matching_retention(): void {
        $guarantee = $this->guarantee();

        $this->actingAs($this->admin)->get(route('guarantees.secure-dialog', $guarantee))
            ->assertOk()
            ->assertSee(__('guarantee.secure.empty'))
            ->assertDontSee(route('guarantees.secure', $guarantee), false);
    }

    public function test_endpoint_replaces_the_retention_chosen_in_the_dialog(): void {
        [, $retention] = $this->invoiceWithRetention();
        $guarantee = $this->guarantee();

        $this->actingAs($this->admin)->from(route('guarantees.index'))
            ->post(route('guarantees.secure', $guarantee), ['retention' => $retention->sqid])
            ->assertRedirect(route('guarantees.index'))
            ->assertSessionHas('status', __('guarantee.secured'));

        $this->assertSame(RetentionStatus::Secured, $retention->refresh()->status);
        $this->assertSame((int) $retention->id, (int) $guarantee->refresh()->invoice_retention_id);

        // Abgelöst ist abgelöst: Die Liste bietet es nicht ein zweites Mal an.
        $this->actingAs($this->admin)->get(route('guarantees.index'))
            ->assertSee(route('guarantees.drawn', $guarantee), false)
            ->assertDontSee(route('guarantees.secure-dialog', $guarantee), false);
    }

    public function test_non_billing_user_is_forbidden(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->org->id]);

        $this->actingAs($user)->get(route('guarantees.index'))->assertForbidden();
    }

    private function rule(NotificationEvent $event): void {
        \Illuminate\Support\Facades\Notification::fake();
        NotificationRule::factory()->forEvent($event)->create([
            'organization_id' => $this->org->id,
            'channels' => [NotificationChannel::InApp->value],
            'notify_affected' => true,
            'recipient_user_ids' => [$this->admin->id],
        ]);
    }
}
