<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleHttpReviewTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\{LinkOrigin, PeriodStatus, SubscriptionProvider};
use App\Enums\User\Permission;
use App\Models\{Customer, ExternalReference, LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine, Organization, User};
use App\Models\Reselling\{ResaleImport, ResalePeriodLink, ResalePurchaseEntry, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Reselling\Register\{MarketplaceImporter, PeriodPlanner};
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * HTTP-Schicht des Reselling-Registers nach dem Review 2026-09-10 (G):
 * Rechte je Aktion, Org-Grenzen an Routen und Eingaben, FormRequests statt
 * stiller Redirects (422 im Dialog), gesperrte Felder, Import-Befunde in der
 * Inbox und das `data-confirm`-Gate.
 */
class ResaleHttpReviewTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private LexofficeArticle $exchange;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-10');
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->exchange = LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-exo', 'name' => 'Exchange Online (Plan 1)', 'article_number' => 'ART-EXO',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '3.95', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
    }

    /** Nutzer mit reselling.view, ohne manage/invoice. */
    private function viewer(): User {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $user->givePermissionTo(Permission::ResellingView->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function customerWithContact(string $name, string $contactId, ?Organization $organization = null): Customer {
        $organization ??= $this->organization;
        $customer = Customer::factory()->create(['organization_id' => $organization->id, 'name' => $name]);
        ExternalReference::create([
            'organization_id' => $organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => $contactId, 'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->getKey(),
        ]);

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function subscription(array $attributes, ?Organization $organization = null): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create(array_merge([
            'organization_id' => ($organization ?? $this->organization)->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'label' => 'Exchange Online (Plan 1)',
            'lexoffice_article_id' => $this->exchange->id, 'quantity' => 5, 'starts_on' => '2025-01-01', 'term_months' => 12, 'interval' => 'yearly',
            'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR', 'sale_unit_price' => '47.40',
        ], $attributes));
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    private function voucherLine(string $contactId, string $number, ?Organization $organization = null): LexofficeVoucherLine {
        $organization ??= $this->organization;
        $voucher = LexofficeVoucher::create([
            'organization_id' => $organization->id, 'external_id' => 'v-' . $number, 'contact_external_id' => $contactId, 'voucher_type' => 'invoice',
            'voucher_status' => 'paid', 'voucher_number' => $number, 'voucher_date' => '2025-01-10', 'total_amount' => 237, 'currency' => 'EUR', 'archived' => false, 'lines_synced_at' => now(),
        ]);

        return LexofficeVoucherLine::create([
            'organization_id' => $organization->id, 'voucher_id' => $voucher->id, 'position' => 1, 'type' => 'service',
            'external_article_id' => 'art-exo', 'lexoffice_article_id' => $this->exchange->id, 'name' => 'Exchange Online (Plan 1)',
            'quantity' => 60, 'unit_name' => 'Monat', 'unit_net' => '3.95', 'total_net' => '237.00', 'tax_rate' => 19, 'currency' => 'EUR',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ResaleSubscription $subscription, array $overrides = []): array {
        return array_merge([
            'label' => $subscription->label, 'kind' => 'license', 'provider' => $subscription->provider->value, 'external_id' => $subscription->external_id,
            'holder' => $subscription->customer_id !== null ? 'customer' : 'none', 'customer_id' => $subscription->customer?->sqid,
            // Artikel mitschicken wie der Dialog — ein fehlendes Feld löscht ihn (bewusst, kein Fehler).
            'lexoffice_article_id' => $subscription->lexoffice_article_id !== null ? Sqid::encode(LexofficeArticle::class, $subscription->lexoffice_article_id) : '',
            'quantity' => $subscription->quantity, 'starts_on' => $subscription->starts_on->toDateString(), 'ends_on' => $subscription->ends_on?->toDateString() ?? '',
            'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'sale_unit_price' => '47.40', 'status' => 'active',
        ], $overrides);
    }

    public function test_view_only_user_reads_subpages_but_cannot_change_anything(): void {
        $viewer = $this->viewer();
        $customer = $this->customerWithContact('Klimpel Bäder GmbH', 'c-kl');
        $subscription = $this->subscription(['customer_id' => $customer->id]);
        $period = $subscription->periods()->firstOrFail();
        $line = $this->voucherLine('c-kl', 'RE/2025/0001');
        $link = ResalePeriodLink::query()->create([
            'organization_id' => $this->organization->id, 'period_id' => $period->id, 'subscription_id' => $subscription->id,
            'linkable_type' => $line->getMorphClass(), 'linkable_id' => $line->id, 'origin' => LinkOrigin::Proposed, 'months' => 60, 'voucher_number' => 'RE/2025/0001', 'voucher_date' => '2025-01-10',
        ]);

        // Lesen: Liste mit den Unterseiten-Links, aber ohne Import/Neu (C11); Perioden und Abgleich erreichbar.
        $this->actingAs($viewer)->get(route('finance.resale.index'))->assertOk()
            ->assertSee(route('finance.resale.periods.index'), false)
            ->assertSee(route('finance.resale.reconcile.index'), false)
            ->assertSee(route('finance.resale.report.index'), false)
            ->assertDontSee(route('finance.resale.import.create'), false)
            ->assertDontSee(route('finance.resale.inbox'), false);
        $this->actingAs($viewer)->get(route('finance.resale.periods.index'))->assertOk()->assertDontSee(route('finance.resale.periods.draft.create'), false);
        $this->actingAs($viewer)->get(route('finance.resale.reconcile.index'))->assertOk();
        $this->actingAs($viewer)->get(route('finance.resale.reconcile.show', $customer))->assertOk();
        $this->actingAs($viewer)->get(route('finance.resale.show', $subscription->sqid))->assertOk();

        // Schreiben: alles 403.
        $this->actingAs($viewer)->delete(route('finance.resale.destroy', $subscription->sqid))->assertForbidden();
        $this->actingAs($viewer)->post(route('finance.resale.periods.propose'))->assertForbidden();
        $this->actingAs($viewer)->post(route('finance.resale.periods.confirm', $period->sqid))->assertForbidden();
        $this->actingAs($viewer)->post(route('finance.resale.reconcile.rehome', $customer), ['period_id' => $period->sqid, 'target_id' => $customer->sqid])->assertForbidden();
        $this->actingAs($viewer)->post(route('finance.resale.reconcile.assign', $customer), ['period_id' => $period->sqid, 'line_id' => $line->sqid, 'months' => 12])->assertForbidden();
        $this->actingAs($viewer)->post(route('finance.resale.periods.link.store', $period->sqid), ['line_id' => $line->sqid, 'months' => 12])->assertForbidden();
        $this->actingAs($viewer)->post(route('finance.resale.links.quick', $subscription->sqid), ['period_id' => $period->sqid, 'line_id' => $line->sqid, 'months' => 12])->assertForbidden();
        $this->actingAs($viewer)->post(route('finance.resale.transfer.store', $subscription->sqid), ['mode' => 'customer', 'customer_id' => $customer->sqid, 'quantity' => 1, 'starts_on' => '2025-01-01'])->assertForbidden();
        $this->actingAs($viewer)->delete(route('finance.resale.links.destroy', $link->sqid))->assertForbidden();
        $this->actingAs($viewer)->get(route('finance.resale.edit', $subscription->sqid))->assertForbidden();
        // Rechnungsentwurf: eigenes Recht (A9) — auch reselling.manage allein reicht nicht.
        $this->actingAs($viewer)->get(route('finance.resale.periods.draft.create'))->assertForbidden();
        $manager = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $manager->givePermissionTo(Permission::ResellingView->value, Permission::ResellingManage->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($manager)->get(route('finance.resale.periods.draft.create'))->assertForbidden();
        $this->actingAs($manager)->get(route('finance.resale.periods.index'))->assertOk()->assertDontSee(route('finance.resale.periods.draft.create'), false);
        $this->assertNotSame('reselling.invoice', __('access.permission.' . Permission::ResellingInvoice->value));
    }

    public function test_foreign_organization_routes_and_inputs_are_rejected(): void {
        $admin = $this->orgAdmin();
        $other = Organization::factory()->create();
        $mine = $this->customerWithContact('Eigener Kunde', 'c-mine');
        $subscription = $this->subscription(['customer_id' => $mine->id]);
        $period = $subscription->periods()->firstOrFail();
        $theirs = $this->customerWithContact('Fremder Kunde', 'c-theirs', $other);
        $theirSubscription = $this->subscription(['customer_id' => $theirs->id], $other);
        // Fremde Organisation: der Org-Scope der aktuellen Organisation sähe die Periode nicht.
        $theirPeriod = $theirSubscription->periods()->withoutGlobalScopes()->firstOrFail();
        $theirLine = $this->voucherLine('c-theirs', 'RE/2025/9999', $other);
        $theirLink = ResalePeriodLink::query()->create([
            'organization_id' => $other->id, 'period_id' => $theirPeriod->id, 'subscription_id' => $theirSubscription->id,
            'linkable_type' => $theirLine->getMorphClass(), 'linkable_id' => $theirLine->id, 'origin' => LinkOrigin::Manual, 'months' => 12, 'voucher_number' => 'RE/2025/9999', 'voucher_date' => '2025-01-10',
        ]);

        // Routenparameter fremder Organisationen: 404.
        $this->actingAs($admin)->post(route('finance.resale.periods.confirm', $theirPeriod->sqid))->assertNotFound();
        $this->actingAs($admin)->get(route('finance.resale.periods.link.create', $theirPeriod->sqid))->assertNotFound();
        $this->actingAs($admin)->delete(route('finance.resale.links.destroy', $theirLink->sqid))->assertNotFound();
        $this->actingAs($admin)->get(route('finance.resale.reconcile.show', $theirs))->assertNotFound();

        // Fremde IDs in Eingaben: org-gescopte Existenzregel → Feldfehler, nichts geschrieben.
        $this->actingAs($admin)->from(route('finance.resale.show', $subscription->sqid))->post(route('finance.resale.links.quick', $subscription->sqid), [
            'period_id' => $period->sqid, 'line_id' => $theirLine->sqid, 'months' => 12,
        ])->assertRedirect(route('finance.resale.show', $subscription->sqid))->assertSessionHasErrors('line_id');
        $this->actingAs($admin)->from(route('finance.resale.show', $subscription->sqid))->post(route('finance.resale.links.quick', $subscription->sqid), [
            'period_id' => $theirPeriod->sqid, 'line_id' => $this->voucherLine('c-mine', 'RE/2025/0002')->sqid, 'months' => 12,
        ])->assertSessionHasErrors('period_id');
        $this->actingAs($admin)->from(route('finance.resale.reconcile.show', $mine))->post(route('finance.resale.reconcile.rehome', $mine), [
            'period_id' => $period->sqid, 'target_id' => $theirs->sqid,
        ])->assertSessionHasErrors('target_id');
        $this->actingAs($admin)->from(route('finance.resale.reconcile.show', $mine))->post(route('finance.resale.reconcile.assign', $mine), [
            'period_id' => $period->sqid, 'line_id' => $theirLine->sqid, 'months' => 12,
        ])->assertSessionHasErrors('line_id');
        $this->assertSame(0, $period->links()->count());
        $this->assertSame($mine->id, $subscription->fresh()?->customer_id);

        // Der Dialog fragt als JSON: 422 mit Feldfehlern statt stillem Redirect (C1).
        $this->actingAs($admin)->postJson(route('finance.resale.periods.link.store', $period->sqid), ['line_id' => $theirLine->sqid, 'months' => 12])
            ->assertStatus(422)->assertJsonValidationErrors(['line_id']);
    }

    public function test_edit_dialog_locks_fields_and_update_respects_assignments(): void {
        $admin = $this->orgAdmin();
        $schub = $this->customerWithContact('Schub- und Schleppreederei', 'c-schub');
        $maerkische = $this->customerWithContact('Märkische Bunker', 'c-mb');
        $contract = $this->subscription(['customer_id' => $schub->id, 'external_id' => 'ent-9', 'quantity' => 9]);
        $this->actingAs($admin)->post(route('finance.resale.transfer.store', $contract->sqid), [
            'mode' => 'customer', 'customer_id' => $maerkische->sqid, 'quantity' => 5, 'starts_on' => '2025-01-01',
        ])->assertRedirect(route('finance.resale.show', $contract->sqid));
        $assignment = ResaleSubscription::query()->where('parent_id', $contract->id)->firstOrFail();

        // Bearbeiten-Dialog: Abtretung mit gesperrtem Produkt/Anbieter/Rhythmus und Hinweis auf den Vertrag.
        $this->actingAs($admin)->get(route('finance.resale.edit', $assignment->sqid))->assertOk()
            ->assertSee(__('resale.dialog.title_edit'))
            ->assertSee(__('resale.edit_hint.assignment', ['contract' => $contract->holderLabel() . ' · ' . $contract->identityLabel()]))
            ->assertSee('name="interval"', false)->assertSee('disabled', false)
            ->assertDontSee('value="' . SubscriptionProvider::DomainReselling->value . '"', false);

        // Kind-Menge über dem Rest des Vertrags (9 − 0 andere = 9; 5 sind frei für dieses Kind): 10 → 422.
        $this->actingAs($admin)->putJson(route('finance.resale.update', $assignment->sqid), $this->payload($assignment, ['holder' => 'customer', 'customer_id' => $maerkische->sqid, 'quantity' => 10]))
            ->assertStatus(422)->assertJsonValidationErrors(['quantity']);
        // Vertrag unter die Abtretung (5) senken: 4 → 422; 6 geht.
        $this->actingAs($admin)->putJson(route('finance.resale.update', $contract->sqid), $this->payload($contract, ['quantity' => 4]))
            ->assertStatus(422)->assertJsonValidationErrors(['quantity']);
        $this->actingAs($admin)->put(route('finance.resale.update', $contract->sqid), $this->payload($contract, ['quantity' => 6]))
            ->assertRedirect(route('finance.resale.show', $contract->sqid));
        $this->assertSame(6, $contract->fresh()?->quantity);

        // Gesperrte Felder der Abtretung kommen vom Vertrag, auch wenn der Client anderes schickt.
        $this->actingAs($admin)->put(route('finance.resale.update', $assignment->sqid), $this->payload($assignment, [
            'holder' => 'customer', 'customer_id' => $maerkische->sqid, 'quantity' => 4, 'provider' => 'manual', 'interval' => 'monthly', 'lexoffice_article_id' => '',
        ]))->assertRedirect(route('finance.resale.show', $assignment->sqid));
        $assignment->refresh();
        $this->assertSame(4, $assignment->quantity);
        $this->assertSame(SubscriptionProvider::TelekomMarketplace, $assignment->provider);
        $this->assertSame('yearly', $assignment->interval->value);
        $this->assertSame($this->exchange->id, $assignment->lexoffice_article_id);

        // „DomainReselling" ist von Hand nie wählbar (B7); Domain-Abos behalten Anbieter und Kennung.
        $this->actingAs($admin)->postJson(route('finance.resale.store'), $this->payload($contract, ['provider' => SubscriptionProvider::DomainReselling->value, 'external_id' => 'dom-1']))
            ->assertStatus(422)->assertJsonValidationErrors(['provider']);
        $domain = $this->subscription(['provider' => SubscriptionProvider::DomainReselling->value, 'external_id' => 'dom:example.de', 'label' => 'example.de', 'kind' => 'domain', 'customer_id' => $schub->id]);
        $this->actingAs($admin)->get(route('finance.resale.edit', $domain->sqid))->assertOk()->assertSee(__('resale.edit_hint.domain'))->assertSee('readonly', false);
        $this->actingAs($admin)->put(route('finance.resale.update', $domain->sqid), $this->payload($domain, ['provider' => 'manual', 'external_id' => 'geändert', 'kind' => 'domain']))
            ->assertRedirect(route('finance.resale.show', $domain->sqid));
        $this->assertSame('dom:example.de', $domain->fresh()?->external_id);
        $this->assertSame(SubscriptionProvider::DomainReselling, $domain->fresh()?->provider);
        $this->actingAs($admin)->delete(route('finance.resale.destroy', $domain->sqid))->assertRedirect(route('finance.resale.show', $domain->sqid))->assertSessionHas('error', __('resale.delete_error.is_domain'));

        // Importiertes Abo: Hinweis und gesperrte Kennung (A14).
        $import = ResaleImport::query()->create(['organization_id' => $this->organization->id, 'provider' => 'telekom_marketplace', 'kind' => ResaleImport::KIND_PURCHASES, 'file_name' => 'purchases.csv', 'status' => 'done', 'rows_total' => 1]);
        $contract->forceFill(['import_id' => $import->id])->save();
        $this->actingAs($admin)->get(route('finance.resale.edit', $contract->sqid))->assertOk()->assertSee(__('resale.edit_hint.imported'));
        $contract->refresh();
        $this->actingAs($admin)->put(route('finance.resale.update', $contract->sqid), $this->payload($contract, ['external_id' => 'ent-99']))->assertRedirect();
        $this->assertSame('ent-9', $contract->fresh()?->external_id);
    }

    public function test_transfer_dialog_tolerates_bad_dates_and_checks_the_whole_term(): void {
        $admin = $this->orgAdmin();
        $schub = $this->customerWithContact('Schub- und Schleppreederei', 'c-schub');
        $maerkische = $this->customerWithContact('Märkische Bunker', 'c-mb');
        $contract = $this->subscription(['customer_id' => $schub->id, 'external_id' => 'ent-9', 'quantity' => 9]);

        // Ungültige Query-Daten: kein 500, Vorbelegung fällt auf den Vertrag zurück.
        $this->actingAs($admin)->get(route('finance.resale.transfer.create', ['subscription' => $contract->sqid, 'starts_on' => 'x', 'ends_on' => '??', 'quantity' => 'abc']))
            ->assertOk()->assertSee('2025-01-01');

        // Abtretung 01.07.2025 – 31.12.2025 über 5; danach eine ab 01.01.2025 offen über 5: am Starttag frei (9),
        // aber ab Juli nur 4 — die Prüfung gilt für die ganze Laufzeit (B8).
        $this->actingAs($admin)->post(route('finance.resale.transfer.store', $contract->sqid), [
            'mode' => 'customer', 'customer_id' => $maerkische->sqid, 'quantity' => 5, 'starts_on' => '2025-07-01', 'ends_on' => '2025-12-31',
        ])->assertRedirect(route('finance.resale.show', $contract->sqid));
        $this->actingAs($admin)->from(route('finance.resale.show', $contract->sqid))->post(route('finance.resale.transfer.store', $contract->sqid), [
            'mode' => 'customer', 'customer_id' => $maerkische->sqid, 'quantity' => 5, 'starts_on' => '2025-01-01',
        ])->assertSessionHasErrors('quantity');
        $this->actingAs($admin)->post(route('finance.resale.transfer.store', $contract->sqid), [
            'mode' => 'customer', 'customer_id' => $maerkische->sqid, 'quantity' => 4, 'starts_on' => '2025-01-01',
        ])->assertRedirect(route('finance.resale.show', $contract->sqid));
        // Suffix aus dem höchsten `#n` (B10): #1 gelöscht → die nächste ist #3, nicht #2.
        $this->assertSame(['ent-9#1', 'ent-9#2'], $contract->assignments()->reorder('id')->pluck('external_id')->all());
        $first = $contract->assignments()->where('external_id', 'ent-9#1')->firstOrFail();
        $this->actingAs($admin)->delete(route('finance.resale.destroy', $first->sqid))->assertRedirect(route('finance.resale.index'));
        $this->actingAs($admin)->post(route('finance.resale.transfer.store', $contract->sqid), [
            'mode' => 'customer', 'customer_id' => $maerkische->sqid, 'quantity' => 1, 'starts_on' => '2025-07-01', 'ends_on' => '2025-12-31',
        ])->assertRedirect(route('finance.resale.show', $contract->sqid));
        $this->assertSame(['ent-9#2', 'ent-9#3'], $contract->assignments()->reorder('id')->pluck('external_id')->all());

        // Fremdkunde eines anderen Kunden als Halter: abgelehnt.
        $foreign = \App\Models\ForeignCustomer::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $schub->id, 'name' => 'Endkunde von Schub']);
        $this->actingAs($admin)->postJson(route('finance.resale.transfer.store', $contract->sqid), [
            'mode' => 'foreign', 'customer_id' => $maerkische->sqid, 'foreign_customer_id' => $foreign->sqid, 'quantity' => 1, 'starts_on' => '2025-01-01',
        ])->assertStatus(422)->assertJsonValidationErrors(['foreign_customer_id']);
    }

    public function test_delete_is_blocked_by_purchase_entries_and_propose_lock_is_a_warning(): void {
        $admin = $this->orgAdmin();
        $customer = $this->customerWithContact('Klimpel Bäder GmbH', 'c-kl');
        $subscription = $this->subscription(['customer_id' => $customer->id]);
        $period = $subscription->periods()->firstOrFail();
        ResalePurchaseEntry::query()->create([
            'organization_id' => $this->organization->id, 'subscription_id' => $subscription->id, 'period_id' => $period->id,
            'provider' => 'telekom_marketplace', 'source' => 'manual', 'document_number' => 'EK-1', 'entry_date' => '2025-01-15',
            'description' => 'Exchange Online', 'net_amount' => '197.50', 'currency' => 'EUR', 'raw_hash' => 'h1',
        ]);
        $this->actingAs($admin)->delete(route('finance.resale.destroy', $subscription->sqid))
            ->assertRedirect(route('finance.resale.show', $subscription->sqid))
            ->assertSessionHas('error', __('resale.delete_error.has_purchases'));
        $this->assertNotNull($subscription->fresh());

        // Vorschlagslauf läuft schon: Hinweis statt 500.
        $lock = Cache::lock('resale:propose:' . $this->organization->id, 30);
        $this->assertTrue($lock->get());
        try {
            $this->actingAs($admin)->from(route('finance.resale.periods.index'))->post(route('finance.resale.periods.propose'))
                ->assertRedirect(route('finance.resale.periods.index'))->assertSessionHas('warning', __('resale.propose.locked'));
        } finally {
            $lock->release();
        }
    }

    public function test_inbox_shows_import_issues_and_own_holding_survives_a_reimport(): void {
        $admin = $this->orgAdmin();
        $files = [ResaleImport::KIND_PURCHASES => ['name' => 'purchases.csv', 'path' => MarketplaceImporterTest::FIXTURE]];
        $records = app(MarketplaceImporter::class)->import($this->organization, $admin, $files);
        $record = $records[0];
        $record->forceFill(['issues' => ['Zeile 7 (Test AG): Beginn "31.02.2026" nicht lesbar - übersprungen.']])->save();

        // Inbox: Befunde je Lauf als Zähler und Aufklapper (A1).
        $assign = static fn(string $company): string => route('finance.resale.inbox.assign', ['company' => $company]);
        $this->actingAs($admin)->get(route('finance.resale.inbox'))->assertOk()
            ->assertSee(__('resale.import_review.skipped'))
            ->assertSee(trans_choice('resale.import_review.issues_title', 1, ['count' => 1]))
            ->assertSee('Zeile 7 (Test AG)')
            ->assertSee($assign('Unbekannt UG'), false);

        // „Eigener Bestand" aus der Inbox überlebt den Re-Import: Halterentscheidung bleibt, Firma taucht nicht wieder auf.
        $this->actingAs($admin)->post(route('finance.resale.inbox.store'), ['company' => 'Unbekannt UG', 'mode' => 'own'])->assertRedirect(route('finance.resale.inbox'));
        $this->assertTrue(ResaleSubscription::query()->where('company_name', 'Unbekannt UG')->value('is_own_holding'));
        app(MarketplaceImporter::class)->import($this->organization, $admin, $files);
        $this->assertTrue(ResaleSubscription::query()->where('company_name', 'Unbekannt UG')->value('is_own_holding'));
        $this->assertSame(0, ResaleSubscription::query()->unassigned()->where('company_name', 'Unbekannt UG')->count());
        $this->actingAs($admin)->get(route('finance.resale.inbox'))->assertOk()->assertDontSee($assign('Unbekannt UG'), false)->assertSee($assign('Muster Bau GmbH'), false);

        // Inbox-Dialog: Kunde ohne Auswahl → Feldfehler, nichts zugeordnet.
        $before = ResaleSubscription::query()->unassigned()->where('company_name', 'Muster Bau GmbH')->count();
        $this->assertGreaterThan(0, $before);
        $this->actingAs($admin)->postJson(route('finance.resale.inbox.store'), ['company' => 'Muster Bau GmbH', 'mode' => 'customer'])
            ->assertStatus(422)->assertJsonValidationErrors(['customer_id']);
        $this->assertSame($before, ResaleSubscription::query()->unassigned()->where('company_name', 'Muster Bau GmbH')->count());
    }

    public function test_own_holding_never_counts_as_open_and_widget_respects_the_module(): void {
        $admin = $this->orgAdmin();
        $customer = $this->customerWithContact('Klimpel Bäder GmbH', 'c-kl');
        $this->subscription(['customer_id' => $customer->id, 'label' => 'Kundenabo Exchange']);
        $this->subscription(['is_own_holding' => true, 'label' => 'Hausinternes Exchange']);
        $this->assertSame(PeriodStatus::Open, ResaleSubscription::query()->where('label', 'Hausinternes Exchange')->firstOrFail()->periods()->firstOrFail()->status);

        // Liste: Kopfzahl zählt nur fremde Halter; der Eigenbestand fällt aus „nur offene" heraus (B14).
        $page = $this->actingAs($admin)->get(route('finance.resale.index', ['open' => 1]))->assertOk();
        $page->assertSee('Kundenabo Exchange')->assertDontSee('Hausinternes Exchange');
        $this->assertSame(2, ResaleSubscription::query()->planning()->count());
        $this->assertSame(2, \App\Models\Reselling\ResalePeriod::query()->due()->count(), 'nur die zwei Perioden des Kundenabos (2025, 2026)');

        // Kachel und Kundenakte-Panel hängen am Modul-Gate (C10).
        $widget = new \App\Dashboard\Widgets\ResalePeriodsWidget;
        $this->assertSame('module.reselling', $widget->requiredModule());
        $this->assertSame(Permission::ResellingView->value, $widget->requiredAbility());
        $this->assertTrue($widget->availableFor($admin));
        $rendered = $widget->render($admin);
        $this->assertStringContainsString('474,00', is_string($rendered) ? $rendered : $rendered->render(), 'offener Betrag anteilig je Währung: 2 Perioden × 5 × 47,40');
        $this->actingAs($admin)->get(route('customers.show', $customer))->assertOk()->assertSee('id="customer-resale"', false);

        config(['license.feature_overrides' => ['module.reselling' => false]]);
        app(\App\Services\Licensing\FeatureFlagResolver::class)->flush();
        $this->assertFalse($widget->availableFor($admin));
        $this->actingAs($admin)->get(route('customers.show', $customer))->assertOk()->assertDontSee('id="customer-resale"', false);
        config(['license.feature_overrides' => []]);
    }
}
