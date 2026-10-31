<?php
/*
 * Created on   : Sat Oct 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReconcileWorkspaceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\LinkOrigin;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Reselling\{ResalePlanLink, ResaleSubscription};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\{LexofficeArticle, LexofficeRecurringTemplate};
use App\Plugins\Lexoffice\Services\LexofficeRecurringTemplateSync;
use App\Services\Reselling\Register\{PeriodPlanner, RecipientReconciler};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakePluginHttp, InteractsWithPlugins};
use Tests\TestCase;

/**
 * Arbeitsmappe je Rechnungsempfänger (Phase 165): Abos mit Halter-Aktionen in
 * der Mappe, Dialoge kehren dorthin zurück, Entwurfsdialog mit vorgewähltem
 * Empfänger.
 */
class ReconcileWorkspaceTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-07');
    }

    /** @param array<string, mixed> $attributes */
    private function subscription(array $attributes): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'label' => 'Exchange Online (Plan 1)', 'quantity' => 1,
            'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR', 'sale_unit_price' => '47.40',
        ], $attributes));
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    public function test_workspace_lists_subscriptions_with_actions_and_dialogs_return_to_it(): void {
        $admin = $this->orgAdmin();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Nordlicht Systems GmbH']);
        $other = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Nordufer Tank GmbH']);
        $contract = $this->subscription(['customer_id' => $customer->id, 'quantity' => 3, 'starts_on' => '2025-08-12']);
        $workspace = route('finance.resale.reconcile.show', $customer);
        $otherWorkspace = route('finance.resale.reconcile.show', $other);

        $page = $this->actingAs($admin)->get($workspace)->assertOk();
        $page->assertSee(__('resale.reconcile.subscriptions.title'))
            ->assertSee('Exchange Online (Plan 1)')
            ->assertSee(route('finance.resale.edit', $contract->sqid), false)
            ->assertSee(route('finance.resale.transfer.create', $contract->sqid), false)
            ->assertSee(route('finance.resale.periods.draft.create', ['customer' => $customer->sqid]), false);

        // Abtretung aus der Mappe: zurück in die Mappe; die Abtretung steht in der Mappe des neuen Halters mit Rücknahme.
        $this->actingAs($admin)->from($workspace)->post(route('finance.resale.transfer.store', $contract->sqid), [
            'mode' => 'customer', 'customer_id' => $other->sqid, 'quantity' => 1, 'starts_on' => '2025-08-12',
        ])->assertRedirect($workspace);
        $assignment = ResaleSubscription::query()->where('parent_id', $contract->id)->firstOrFail();
        $this->actingAs($admin)->get($otherWorkspace)->assertOk()
            ->assertSee(route('finance.resale.destroy', $assignment->sqid), false)
            ->assertSee(__('resale.transfer.from'))
            ->assertDontSee(route('finance.resale.transfer.create', $assignment->sqid), false);

        // Rücknahme aus der Mappe: zurück in die Mappe, der Vertrag plant wieder mit allen Lizenzen.
        $this->actingAs($admin)->from($otherWorkspace)->delete(route('finance.resale.destroy', $assignment->sqid))->assertRedirect($otherWorkspace);
        $this->assertNull($assignment->fresh());
        $this->assertSame(3, $contract->fresh()?->billableQuantityOn(\Carbon\CarbonImmutable::parse('2026-01-01')));

        // Löschen von der eigenen Abo-Seite: die Seite gibt es danach nicht mehr → Abo-Liste.
        $this->actingAs($admin)->from(route('finance.resale.show', $contract->sqid))->post(route('finance.resale.transfer.store', $contract->sqid), [
            'mode' => 'customer', 'customer_id' => $other->sqid, 'quantity' => 1, 'starts_on' => '2025-08-12',
        ])->assertRedirect(route('finance.resale.show', $contract->sqid));
        $second = ResaleSubscription::query()->where('parent_id', $contract->id)->firstOrFail();
        $this->actingAs($admin)->from(route('finance.resale.show', $second->sqid))->delete(route('finance.resale.destroy', $second->sqid))
            ->assertRedirect(route('finance.resale.subscriptions'));
    }

    /** Lexoffice-Serienvorlage des Kontakts mit einer Exchange-Position, über den Spiegel geholt. */
    private function lexofficeTemplate(string $contactId, string $id): void {
        $entry = [
            'id' => $id, 'organizationId' => 'org', 'title' => 'Rechnung Exchange', 'createdDate' => '2025-10-01T10:00:00.000+02:00', 'updatedDate' => '2026-10-01T10:00:00.000+02:00',
            'address' => ['contactId' => $contactId, 'name' => 'Nordlicht Systems GmbH'],
            'totalPrice' => ['currency' => 'EUR', 'totalNetAmount' => 47.4, 'totalGrossAmount' => 56.41],
            'recurringTemplateSettings' => [
                'id' => 'settings-' . $id, 'startDate' => '2025-08-12T00:00:00.000+02:00', 'endDate' => null, 'finalize' => true, 'shippingType' => 'serviceperiod',
                'executionInterval' => 'ANNUALLY', 'nextExecutionDate' => '2027-08-12T00:00:00.000+02:00', 'lastExecutionFailed' => false, 'lastExecutionErrorMessage' => null, 'executionStatus' => 'ACTIVE',
            ],
        ];
        $lines = [['type' => 'service', 'id' => 'art-exo', 'name' => 'Exchange Online (Plan 1)', 'quantity' => 12, 'unitName' => 'Monat', 'unitPrice' => ['currency' => 'EUR', 'netAmount' => 3.95, 'taxRatePercentage' => 19.0]]];
        FakePluginHttp::fake([
            'https://api.lexware.io/v1/recurring-templates/' . $id => FakePluginHttp::response($entry + ['lineItems' => $lines, 'taxConditions' => ['taxType' => 'net']]),
            'https://api.lexware.io/v1/recurring-templates?*' => FakePluginHttp::response(['content' => [$entry], 'first' => true, 'last' => true, 'totalPages' => 1, 'totalElements' => 1, 'numberOfElements' => 1, 'size' => 250, 'number' => 0, 'sort' => []]),
        ]);
        (new LexofficeRecurringTemplateSync($this->organization->id))->sync($this->organization);
    }

    public function test_workspace_shows_the_recipients_recurring_templates_and_links_them_to_its_subscriptions(): void {
        // MVP-1444: Vorlagen, Zuordnung und Befunde stehen in der Mappe — dasselbe Teilstück wie im Lizenzkonto.
        $admin = $this->orgAdmin();
        $this->enablePluginFor($this->organization, LexofficePlugin::ID, ['api_key' => 'lex-key', 'request_interval' => '0']);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Nordlicht Systems GmbH']);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-nordlicht', 'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->getKey(),
        ]);
        $article = LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-exo', 'name' => 'Exchange Online (Plan 1)', 'article_number' => 'EXO',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '3.95', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        $subscription = $this->subscription(['customer_id' => $customer->id, 'article_ref' => 'lex:' . $article->id, 'starts_on' => '2025-08-12']);
        $stranger = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Fremde GmbH']);
        $foreign = $this->subscription(['customer_id' => $stranger->id, 'article_ref' => 'lex:' . $article->id, 'starts_on' => '2025-08-12']);
        $this->lexofficeTemplate('c-nordlicht', 'tpl-w');
        $position = (int) LexofficeRecurringTemplate::query()->firstOrFail()->lines()->firstOrFail()->position;
        $workspace = route('finance.resale.reconcile.show', $customer);

        $page = $this->actingAs($admin)->get($workspace)->assertOk();
        $page->assertSee(__('resale.plan.title'))->assertSee('Rechnung Exchange')
            ->assertSee(route('finance.resale.reconcile.plan.store', $customer), false)
            ->assertSee(__('resale.plan.finding.subscription_without_plan_short'));

        // Zuordnung aus der Mappe: nur Abos dieses Empfängers; zurück in die Mappe, danach vollständig zugeordnet.
        $this->actingAs($admin)->post(route('finance.resale.reconcile.plan.store', $customer), [
            'subscription_id' => $foreign->sqid, 'plan_key' => 'lexoffice:tpl-w', 'line_position' => $position, 'quantity' => 1,
        ])->assertSessionHasErrors(['subscription_id']);
        $this->actingAs($admin)->post(route('finance.resale.reconcile.plan.store', $customer), [
            'subscription_id' => $subscription->sqid, 'plan_key' => 'lexoffice:tpl-w', 'line_position' => $position, 'quantity' => 1,
        ])->assertRedirect($workspace)->assertSessionHas('success');
        $link = ResalePlanLink::query()->firstOrFail();
        $this->assertSame($subscription->id, (int) $link->subscription_id);
        $this->assertSame(LinkOrigin::Manual, $link->origin);
        $this->actingAs($admin)->get($workspace)->assertOk()
            ->assertSee(__('resale.plan.fully_assigned'))
            ->assertDontSee(__('resale.plan.finding.subscription_without_plan_short'));
        // Lösen über dieselbe Route wie im Lizenzkonto, zurück zur Mappe.
        $this->actingAs($admin)->from($workspace)->delete(route('finance.resale.accounts.plan.destroy', $link->sqid))->assertRedirect($workspace);
        $this->assertNull($link->fresh());
    }

    public function test_worklist_orders_by_open_amount_marks_done_recipients_and_next_leads_through_it(): void {
        // MVP-1445: Reihenfolge nach offenem Betrag, fertig = nichts Fälliges ohne Entscheidung, Weiter führt durch die Liste.
        $admin = $this->orgAdmin();
        $big = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Große GmbH']);
        $small = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Kleine GmbH']);
        $done = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Fertige GmbH']);
        $this->subscription(['customer_id' => $small->id, 'quantity' => 1, 'starts_on' => '2025-08-12']);
        $this->subscription(['customer_id' => $big->id, 'quantity' => 3, 'starts_on' => '2025-08-12']);
        $this->subscription(['customer_id' => $done->id, 'quantity' => 1, 'starts_on' => '2026-12-01']);

        $rows = app(RecipientReconciler::class)->overview($this->organization);
        $this->assertSame(['Große GmbH', 'Kleine GmbH', 'Fertige GmbH'], array_column($rows, 'name'));
        $this->assertSame([false, false, true], array_column($rows, 'done'));
        $this->assertSame('284,40 €', $rows[0]['open_amount']?->format(), '2 fällige Perioden × 3 × 47,40');
        $this->assertSame('94,80 €', $rows[1]['open_amount']?->format());
        $this->assertNull($rows[2]['open_amount'], 'nichts fällig');
        $this->assertSame(['Große GmbH', 'Kleine GmbH'], array_column(app(RecipientReconciler::class)->worklist($this->organization), 'name'));

        // Weiter: zum ersten offenen Empfänger; die Mappe zeigt Position und Nachfolger.
        $this->actingAs($admin)->get(route('finance.resale.reconcile.next'))->assertRedirect(route('finance.resale.reconcile.show', $big));
        $this->actingAs($admin)->get(route('finance.resale.reconcile.show', $big))->assertOk()
            ->assertSee(__('resale.reconcile.worklist.position', ['position' => 1, 'total' => 2]))
            ->assertSee('284,40 €')
            ->assertSee(route('finance.resale.reconcile.show', $small), false)
            ->assertDontSee(__('resale.reconcile.worklist.prev'));
        $this->actingAs($admin)->get(route('finance.resale.reconcile.show', $small))->assertOk()
            ->assertSee(__('resale.reconcile.worklist.position', ['position' => 2, 'total' => 2]))
            ->assertSee(route('finance.resale.reconcile.show', $big), false)
            ->assertSee(__('resale.reconcile.worklist.all_done'));
        // Fertiger Empfänger: Abzeichen, „Nächster“ ist der erste offene.
        $this->actingAs($admin)->get(route('finance.resale.reconcile.show', $done))->assertOk()
            ->assertSee(__('resale.reconcile.worklist.done'))
            ->assertSee(route('finance.resale.reconcile.show', $big), false);
        // Liste: fertige Empfänger nur mit „alle“; Überblick hat den Einstieg; die Aufgabe „fällig“ führt zur Mappe.
        $this->actingAs($admin)->get(route('finance.resale.reconcile.index'))->assertOk()->assertSee('Große GmbH')->assertDontSee('Fertige GmbH');
        $this->actingAs($admin)->get(route('finance.resale.reconcile.index', ['show' => 'all']))->assertOk()->assertSee('Fertige GmbH')->assertSee(__('resale.reconcile.worklist.done'));
        $this->actingAs($admin)->get(route('finance.resale.index'))->assertOk()->assertSee(route('finance.resale.reconcile.next'), false);

        // Alles fertig: Weiter führt zur Liste mit Hinweis — im zuletzt gemerkten Listenzustand (show=all).
        ResaleSubscription::query()->whereIn('customer_id', [$big->id, $small->id])->get()->each(static fn(ResaleSubscription $s) => $s->periods()->delete() && $s->delete());
        $this->actingAs($admin)->get(route('finance.resale.reconcile.next'))->assertRedirect(route('finance.resale.reconcile.index', ['show' => 'all']))->assertSessionHas('info');
    }

    public function test_draft_dialog_preselects_the_recipient_from_the_workspace(): void {
        $admin = $this->orgAdmin();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Nordlicht Systems GmbH']);
        $this->subscription(['customer_id' => $customer->id, 'starts_on' => '2025-08-12']);

        $this->actingAs($admin)->get(route('finance.resale.periods.draft.create', ['customer' => $customer->sqid]))->assertOk()
            ->assertSee('value="' . $customer->sqid . '" selected', false)
            ->assertSee(route('finance.resale.periods.draft.create', ['until' => '2027-09-07', 'customer' => $customer->sqid]));
        $this->actingAs($admin)->get(route('finance.resale.periods.draft.create'))->assertOk()
            ->assertDontSee('value="' . $customer->sqid . '" selected', false);
    }
}
