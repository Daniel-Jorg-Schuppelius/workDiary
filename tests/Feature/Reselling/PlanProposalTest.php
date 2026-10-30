<?php
/*
 * Created on   : Fri Oct 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanProposalTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Finance\BillingMode;
use App\Enums\Reselling\LinkOrigin;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Reselling\{ResalePlanLink, ResaleSubscription};
use App\Plugins\Lexoffice\Models\LexofficeArticle;
use App\Services\Reselling\Register\{PeriodPlanner, ResaleDraftRun};
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Vorlagen-Vorschlag (Feature 185, MVP-1440): je externem Empfänger die Abos
 * ohne Serienvorlage mit Artikel, Menge, Einheit, Preis, Intervall und erster
 * Ausführung — was von Hand in die Vorlage des Buchhaltungsprogramms gehört.
 */
class PlanProposalTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-04');
    }

    /** @param array<string, mixed> $attributes */
    private function subscription(array $attributes): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'quantity' => 1,
            'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR',
        ], $attributes));
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    public function test_page_lists_unplanned_subscriptions_of_external_recipients_with_template_data(): void {
        $admin = $this->orgAdmin();
        $lexoffice = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Musterkanzlei Beispiel', 'billing_mode' => BillingMode::Lexoffice]);
        $local = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Lokal-Kunde', 'billing_mode' => BillingMode::Workdiary]);
        $article = LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-bb', 'name' => 'Microsoft 365 Business Basic', 'article_number' => 'BB',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '5.60', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        // Domain, eine Lizenz, nächste Periode am 15.11.2026 → „1 Jahr“ zu 9,00 jährlich.
        $this->subscription(['kind' => 'domain', 'provider' => 'domainreselling', 'label' => 'beispiel-kanzlei.de', 'customer_id' => $lexoffice->id, 'starts_on' => '2025-11-15', 'sale_unit_price' => '9.00']);
        // Monatsartikel, drei Lizenzen, Jahreslaufzeit → „36 Monat“ zu 5,60; die laufende Periode begann am 01.01.2026 (fällig seit).
        $this->subscription(['label' => 'Microsoft 365 Business Basic', 'article_ref' => 'lex:' . $article->id, 'customer_id' => $lexoffice->id, 'quantity' => 3, 'starts_on' => '2026-01-01', 'sale_unit_price' => '67.20']);
        // Mit Vorlage, lokaler Empfänger, eigener Bestand: nicht in der Liste.
        $planned = $this->subscription(['label' => 'Exchange Online (Plan 1)', 'customer_id' => $lexoffice->id, 'starts_on' => '2026-01-01', 'sale_unit_price' => '47.40']);
        ResalePlanLink::query()->create(['organization_id' => $this->organization->id, 'subscription_id' => $planned->id, 'plan_key' => 'lexoffice:tpl-9', 'line_position' => 1, 'quantity' => 1, 'origin' => LinkOrigin::Confirmed]);
        $this->subscription(['label' => 'Lokal-Abo', 'customer_id' => $local->id, 'starts_on' => '2026-01-01', 'sale_unit_price' => '10.00']);
        $this->subscription(['label' => 'Eigenes Abo', 'is_own_holding' => true, 'company_name' => 'Eigene GmbH', 'starts_on' => '2026-01-01', 'sale_unit_price' => '10.00']);

        $page = $this->actingAs($admin)->get(route('finance.resale.plan.proposal'))->assertOk();
        $page->assertSee('Musterkanzlei Beispiel')
            ->assertSeeInOrder(['beispiel-kanzlei.de', '>1<', __('resale.draft.unit_year'), '9,00', __('resale.plan.interval.ANNUALLY'), '15.11.2026'], false)
            ->assertSeeInOrder(['Microsoft 365 Business Basic', '>36<', 'Monat', '5,60', __('resale.plan.interval.ANNUALLY'), __('resale.plan.proposal.due_since', ['date' => '01.01.2026'])], false)
            ->assertDontSee('Exchange Online (Plan 1)')
            ->assertDontSee('Lokal-Kunde')
            ->assertDontSee('Eigene GmbH')
            ->assertDontSee(__('resale.plan.proposal.covered'));

        // Serienlauf nach außen aktiv: der Empfänger ist markiert — eine Vorlage lohnt nur für den Versand.
        // Der erste Request hat currentOrganization frisch gebunden; der Schalter muss an dieser Instanz gesetzt werden.
        Setting::set(ResaleDraftRun::SETTING_EXTERNAL_ENABLED, true, SettingScope::Organization, app('currentOrganization'));
        $this->actingAs($admin)->get(route('finance.resale.plan.proposal'))->assertOk()->assertSee(__('resale.plan.proposal.covered'));

        // Einstieg aus der Periodenliste; ohne Register-Recht kein Zugriff.
        $this->actingAs($admin)->get(route('finance.resale.periods.index'))->assertOk()->assertSee(route('finance.resale.plan.proposal'));
        $this->actingAs(User::factory()->create(['organization_id' => $this->organization->id]))->get(route('finance.resale.plan.proposal'))->assertForbidden();
    }

    public function test_page_without_candidates_shows_the_empty_state(): void {
        $this->actingAs($this->orgAdmin())->get(route('finance.resale.plan.proposal'))->assertOk()->assertSee(__('resale.plan.proposal.empty'));
    }
}
