<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleIntegrityGuardTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Finance\BillingMode;
use App\Enums\Reselling\{LinkOrigin, PeriodStatus};
use App\Models\Audit\AuditLog;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\Invoice;
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\{LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine};
use App\Services\Reselling\Register\{HolderTakeover, LicenceAssignments, LinkProposer, PeriodLinker, PeriodPlanner, ResaleInvoiceDraftService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Sicherheitsaudit 2026-11-02, Welle 3 (sec-01 … sec-06): Rechnungsentwurf
 * nur einmal je Empfänger (Sperre und Nachprüfung unter Zeilensperre),
 * Abtretung und Übernahme prüfen die freie Menge unter der Sperre, der
 * ganze Halterwechsel scheitert an berechneten Perioden, Einzelverzicht und
 * Einzelbestätigung tragen denselben Guard wie die Sammelaktion, und der
 * Periodenstatus folgt seinem Übergangsvertrag.
 */
class ResaleIntegrityGuardTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-04');
    }

    private function customer(string $name, ?string $contactId = null, BillingMode $mode = BillingMode::Workdiary): Customer {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => $name, 'billing_mode' => $mode]);
        if ($contactId !== null) {
            ExternalReference::create([
                'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
                'external_id' => $contactId, 'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->getKey(),
            ]);
        }

        return $customer;
    }

    /** @param array<string, mixed> $attributes */
    private function subscription(Customer $customer, array $attributes = []): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'label' => 'Exchange Online (Plan 1)',
            'customer_id' => $customer->id, 'quantity' => 3, 'starts_on' => '2025-08-05', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto',
            'sale_unit_price' => '47.40', 'currency' => 'EUR', 'status' => 'active',
        ], $attributes));
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    private function article(): LexofficeArticle {
        return LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-exo', 'name' => 'Exchange Online (Plan 1)', 'article_number' => 'ART-EXO',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '3.95', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
    }

    private function voucherLine(LexofficeArticle $article, string $contactId, string $number, string $date, float $quantity = 12): LexofficeVoucherLine {
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-' . $number, 'contact_external_id' => $contactId, 'voucher_type' => 'invoice',
            'voucher_status' => 'paid', 'voucher_number' => $number, 'voucher_date' => $date, 'total_amount' => 100, 'currency' => 'EUR', 'archived' => false,
            'voucher_text' => '', 'lines_synced_at' => now(),
        ]);

        return LexofficeVoucherLine::create([
            'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => 1, 'type' => 'service',
            'external_article_id' => $article->external_id, 'lexoffice_article_id' => $article->id, 'name' => 'Exchange Online (Plan 1)',
            'quantity' => $quantity, 'unit_name' => 'Monat', 'unit_net' => '3.95', 'total_net' => (string) round(3.95 * $quantity, 2), 'tax_rate' => 19, 'currency' => 'EUR',
        ]);
    }

    public function test_second_draft_for_the_same_recipient_sees_the_stamped_periods_and_stops(): void {
        $admin = $this->orgAdmin();
        $customer = $this->customer('Entwurfshof GmbH');
        $subscription = $this->subscription($customer);
        $drafts = app(ResaleInvoiceDraftService::class);

        $first = $drafts->draft($this->organization, $customer, $admin);
        $this->assertTrue($first['local']);
        $this->assertSame(1, Invoice::query()->where('customer_id', $customer->id)->count());

        // Lokal stempelt der Entwurf und setzt „berechnet“ (Vorschlag) — der zweite Aufruf findet nichts Offenes mehr.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage((string) __('resale.draft.error.nothing_open'));
        try {
            $drafts->draft($this->organization, $customer, $admin);
        } finally {
            $this->assertSame(1, Invoice::query()->where('customer_id', $customer->id)->count(), 'kein zweiter Entwurf, keine zweite Rechnungsnummer');
            $this->assertSame([$first['draft_id'], $first['draft_id']], $subscription->periods()->pluck('draft_reference')->all());
        }
    }

    public function test_a_held_lock_turns_dialog_and_bulk_action_into_a_translated_abort(): void {
        $admin = $this->orgAdmin();
        $customer = $this->customer('Doppelklick AG');
        $period = $this->subscription($customer)->periods()->firstOrFail();
        $lock = Cache::lock('resale:draft:' . $this->organization->id . ':' . $customer->id, 60);
        $this->assertTrue($lock->get(), 'der Test hält die Sperre wie ein laufender erster Klick');

        try {
            $this->actingAs($admin)->from(route('finance.resale.periods.index'))
                ->post(route('finance.resale.periods.draft.store'), ['customer_id' => $customer->sqid])
                ->assertRedirect(route('finance.resale.periods.index'))
                ->assertSessionHasErrors(['customer_id' => __('resale.draft.error.locked')]);
            $this->actingAs($admin)->from(route('finance.resale.periods.index'))
                ->post(route('finance.resale.periods.bulk.draft'), ['ids' => [$period->sqid]])
                ->assertRedirect(route('finance.resale.periods.index'))
                ->assertSessionHas('warning', static fn (string $flash): bool => str_contains($flash, (string) __('resale.draft.error.locked')));
            $this->assertSame(0, Invoice::query()->count());
            $this->assertNull($period->fresh()?->draft_reference);
        } finally {
            $lock->release();
        }

        // Sperre frei: derselbe Klick legt den Entwurf an.
        $this->actingAs($admin)->post(route('finance.resale.periods.draft.store'), ['customer_id' => $customer->sqid])->assertSessionHasNoErrors();
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_the_locked_reread_aborts_when_a_period_was_stamped_between_selection_and_transaction(): void {
        $admin = $this->orgAdmin();
        $customer = $this->customer('Zwischenzeit GmbH');
        $period = $this->subscription($customer)->periods()->firstOrFail();
        // Simuliert den zweiten Prozess: beim zweiten Lesen derselben Periode (Nachprüfung unter
        // Zeilensperre) trägt die Zeile plötzlich einen fremden Stempel.
        $seen = [];
        ResalePeriod::retrieved(static function (ResalePeriod $row) use (&$seen, $period): void {
            if ((int) $row->id !== (int) $period->id) {
                return;
            }
            $seen[] = $row->id;
            if (count($seen) === 2) {
                $row->draft_reference = 'RE/2026/9099';
                $row->draft_created_at = now();
            }
        });

        try {
            app(ResaleInvoiceDraftService::class)->draft($this->organization, $customer, $admin);
            $this->fail('der Entwurf hätte an der Nachprüfung scheitern müssen');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('RE/2026/9099', $e->getMessage());
        }
        $this->assertSame(0, Invoice::query()->count(), 'nichts angelegt, keine Rechnungsnummer gezogen');
        $this->assertNull($period->fresh()?->draft_reference, 'Transaktion zurückgerollt');
        $this->assertSame(PeriodStatus::Open, $period->fresh()?->status);
    }

    public function test_assignments_recheck_the_free_quantity_under_the_contract_lock(): void {
        $holder = $this->customer('Halter GmbH');
        $first = $this->customer('Erster Nehmer GmbH');
        $second = $this->customer('Zweiter Nehmer GmbH');
        $contract = $this->subscription($holder);
        $assignments = new LicenceAssignments;

        // Validator umgangen: der Dienst selbst prüft — zwei Abtretungen über je zwei von drei Lizenzen.
        $assignments->create($contract, ['customer' => $first, 'foreign' => null], 2, '2025-08-05', null, null, null, null);
        try {
            $assignments->create($contract, ['customer' => $second, 'foreign' => null], 2, '2025-08-05', null, null, null, null);
            $this->fail('die zweite Abtretung übersteigt die Vertragsmenge');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame((string) __('resale.transfer.error.quantity', ['available' => 1]), $e->getMessage());
        }
        $this->assertSame(1, ResaleSubscription::query()->where('parent_id', $contract->id)->count());
        $this->assertSame(1, $contract->fresh()?->billableQuantityOn($contract->starts_on), 'der Vertrag rechnet weiter eine Lizenz');

        // Nichts frei → keine Abtretung mit null Lizenzen.
        try {
            $assignments->create($contract, ['customer' => $second, 'foreign' => null], 0, '2025-08-05', null, null, null, null);
            $this->fail('null Lizenzen sind keine Abtretung');
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(1, ResaleSubscription::query()->where('parent_id', $contract->id)->count());
    }

    public function test_a_whole_takeover_is_refused_while_periods_are_invoiced_to_the_previous_holder(): void {
        $admin = $this->orgAdmin();
        $article = $this->article();
        $insolvent = $this->customer('Altbau Technik GmbH', 'c-alt');
        $successor = $this->customer('Neubau Service GmbH', 'c-neu');
        $contract = $this->subscription($insolvent, ['quantity' => 1, 'starts_on' => '2024-08-05', 'ends_on' => '2026-08-04', 'renewal' => 'cancel', 'status' => 'ended']);
        [$billed, $open] = $contract->periods()->orderBy('starts_on')->get()->all();
        (new PeriodLinker)->attach($billed, $this->voucherLine($article, 'c-alt', 'RE/2024/9001', '2024-08-06'), 12.0, null, $admin->id);
        $this->assertSame(PeriodStatus::Billed, $billed->fresh()?->status);
        $store = route('finance.resale.takeover.store', $contract->sqid);
        $message = (string) __('resale.takeover.error.billed_whole', ['holder' => 'Altbau Technik GmbH', 'date' => '05.08.2025']);

        // Formular: Stichtag am Abo-Beginn = ganzer Wechsel → Feldfehler mit dem Vorschlag der ersten offenen Periode.
        $this->actingAs($admin)->post($store, ['mode' => 'customer', 'customer_id' => $successor->sqid, 'starts_on' => '2024-08-05'])
            ->assertSessionHasErrors(['starts_on' => $message]);
        $this->assertSame($insolvent->id, $contract->fresh()?->customer_id);

        // Dienst (Validator umgangen): dieselbe Ablehnung unter der Sperre.
        try {
            (new HolderTakeover)->apply($contract, ['customer' => $successor, 'foreign' => null], $contract->starts_on, false, null, $admin->id);
            $this->fail('ganzer Wechsel trotz berechneter Periode');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame($message, $e->getMessage());
        }
        $this->assertSame($insolvent->id, $contract->fresh()?->customer_id);
        $this->assertSame(0, AuditLog::query()->where('event', HolderTakeover::AUDIT)->count());

        // Ab der ersten offenen Periode: Abtretung an den Nachfolger, die berechnete Periode bleibt beim Halter.
        $this->actingAs($admin)->post($store, ['mode' => 'customer', 'customer_id' => $successor->sqid, 'starts_on' => '2025-08-05'])->assertSessionHasNoErrors();
        $assignment = ResaleSubscription::query()->where('parent_id', $contract->id)->firstOrFail();
        $this->assertSame($successor->id, $assignment->customer_id);
        $this->assertSame($insolvent->id, $contract->fresh()?->customer_id, 'der Vertrag bleibt beim bisherigen Halter');
        $this->assertSame(PeriodStatus::Billed, $billed->fresh()?->status);
        $this->assertSame(1, $billed->fresh()?->links()->count());
        $this->assertSame(0, $contract->periods()->whereKey($open->id)->count(), 'die offene Periode plant jetzt die Abtretung');
    }

    public function test_single_waive_and_confirm_carry_the_bulk_guards(): void {
        $admin = $this->orgAdmin();
        $article = $this->article();
        $customer = $this->customer('Badhaus Bäder GmbH', 'c-kl');
        $subscription = $this->subscription($customer, ['quantity' => 1, 'article_ref' => 'lex:' . $article->id, 'provider' => 'qualityhosting']);
        [$proposed, $open] = $subscription->periods()->orderBy('starts_on')->get()->all();
        // Rechnung 62 Tage nach Periodenbeginn: außerhalb des Fensters, bleibt Vorschlag (wie ResalePeriodBulkTest).
        $this->voucherLine($article, 'c-kl', 'RE/2025/9010', '2025-10-06');
        (new LinkProposer)->propose($this->organization);
        $this->assertTrue($proposed->fresh()?->load('links')->isProposedOnly() ?? false);
        $future = $this->subscription($customer, ['label' => 'Künftig', 'starts_on' => '2026-10-01'])->periods()->firstOrFail();
        $decided = $this->subscription($customer, ['label' => 'Entschieden', 'starts_on' => '2025-07-01'])->periods()->firstOrFail();
        $decided->forceFill(['status' => PeriodStatus::Waived, 'decided_at' => now(), 'decided_by_user_id' => $admin->id, 'waived_reason' => 'Kulanz'])->save();
        $payload = ['decision' => 'disputed', 'reason' => 'Kunde widerspricht'];
        $this->assertTrue($future->isUpcoming());

        // Einzelverzicht: entschieden und bevorstehend → 422 im Dialog, nichts geändert.
        $this->actingAs($admin)->post(route('finance.resale.periods.waive', $decided->sqid), $payload)->assertSessionHasErrors(['decision' => __('resale.link.error.not_decidable')]);
        $this->assertSame('Kulanz', $decided->fresh()?->waived_reason);
        $this->assertSame(PeriodStatus::Waived, $decided->fresh()?->status);
        $this->actingAs($admin)->post(route('finance.resale.periods.waive', $future->sqid), $payload)->assertSessionHasErrors('decision');
        $this->assertSame(PeriodStatus::Open, $future->fresh()?->status);
        $this->assertNull($future->fresh()?->decided_at);

        // Einzelverzicht auf eine Periode mit Vorschlag: Vorschläge gehen weg, Entscheidung und Audit wie in der Sammelaktion.
        $this->actingAs($admin)->from(route('finance.resale.periods.index'))->post(route('finance.resale.periods.waive', $proposed->sqid), $payload)
            ->assertRedirect(route('finance.resale.periods.index'))->assertSessionHasNoErrors();
        $proposed->refresh();
        $this->assertSame(PeriodStatus::Disputed, $proposed->status);
        $this->assertSame(0, $proposed->links()->where('origin', LinkOrigin::Proposed->value)->count());
        $this->assertSame($admin->id, $proposed->decided_by_user_id);
        $this->assertSame(1, AuditLog::query()->where('event', 'resale_period.disputed')->where('auditable_id', $proposed->id)->count());

        // Einzelbestätigung: ohne offenen Vorschlag bleibt die Periode offen und ohne Entscheidung.
        $this->actingAs($admin)->post(route('finance.resale.periods.confirm', $open->sqid))->assertRedirect()
            ->assertSessionHas('error', __('resale.link.error.not_confirmable'));
        $this->assertNull($open->fresh()?->decided_at);
        $this->assertSame(PeriodStatus::Open, $open->fresh()?->status);
        $this->assertSame(0, AuditLog::query()->where('event', 'resale_period.confirmed')->count());

        // Zurücknehmen läuft über den Statusvertrag (strittig → offen) und lässt die Periode wieder entscheiden.
        $this->actingAs($admin)->post(route('finance.resale.periods.reopen', $proposed->sqid))->assertRedirect()->assertSessionHas('success');
        $this->assertSame(PeriodStatus::Open, $proposed->fresh()?->status);
        $this->assertTrue($proposed->fresh()?->isDecidable() ?? false);
    }

    public function test_period_status_transitions_follow_the_contract(): void {
        $this->assertSame([PeriodStatus::Open], PeriodStatus::Waived->allowedTransitions());
        $this->assertSame([PeriodStatus::Open], PeriodStatus::Disputed->allowedTransitions());
        $this->assertTrue(PeriodStatus::Open->canTransitionTo(PeriodStatus::Billed));
        $this->assertTrue(PeriodStatus::Billed->canTransitionTo(PeriodStatus::Waived));
        $this->assertTrue(PeriodStatus::Partial->canTransitionTo(PeriodStatus::Disputed));
        $this->assertFalse(PeriodStatus::Waived->canTransitionTo(PeriodStatus::Billed));
        $this->assertFalse(PeriodStatus::Disputed->canTransitionTo(PeriodStatus::Waived));
        foreach (PeriodStatus::cases() as $status) {
            $this->assertNotContains($status, $status->allowedTransitions(), 'gleicher Status ist kein Übergang');
        }

        // Der Dienst lehnt ab, was der Vertrag nicht kennt — hier verzichtet → strittig an einer Periode ohne Entscheidungsstempel.
        $customer = $this->customer('Vertrag GmbH');
        $period = $this->subscription($customer)->periods()->firstOrFail();
        $period->forceFill(['status' => PeriodStatus::Waived, 'waived_reason' => 'Altbestand'])->save();
        $this->expectException(\RuntimeException::class);
        (new PeriodLinker)->decide($period, PeriodStatus::Disputed, 'Widerspruch', null);
    }
}
