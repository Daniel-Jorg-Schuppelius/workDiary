<?php
/*
 * Created on   : Sun Nov 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleInboxTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Reselling\ResaleSubscription;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\{LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine};
use App\Services\Reselling\Register\PeriodPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Posteingang für beendete Abos ohne Halter (Pilot 2026-11-01): Ein beendetes
 * Abo mit fälligen offenen Perioden bleibt im Posteingang und in den Zählern,
 * und der Partner, in dessen Rechnungstexten die Firma steht, wird als
 * Zuordnung vorgeschlagen — sonst fand niemand seine Rechnungen.
 */
class ResaleInboxTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_ended_subscription_without_holder_is_offered_with_the_partner_from_the_invoice_text(): void {
        $this->setUpOrganization();
        $this->travelTo('2026-09-07');
        $admin = $this->orgAdmin();
        $article = LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-exo', 'name' => 'Exchange Online (Plan 1)', 'article_number' => 'ART-EXO',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '3.95', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        $partner = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Feldmark Systeme GmbH']);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-fm', 'referenceable_type' => $partner->getMorphClass(), 'referenceable_id' => $partner->getKey(),
        ]);
        foreach (['RE/2024/0961' => '2023-02-13', 'RE/2024/0962' => '2024-02-13'] as $number => $from) {
            $voucher = LexofficeVoucher::create([
                'organization_id' => $this->organization->id, 'external_id' => 'v-' . $number, 'contact_external_id' => 'c-fm', 'voucher_type' => 'invoice',
                'voucher_status' => 'paid', 'voucher_number' => $number, 'voucher_date' => '2024-05-23', 'total_amount' => 56.41, 'currency' => 'EUR', 'archived' => false,
                'voucher_text' => 'Microsoftdienste Hanna Quist', 'service_starts_on' => $from, 'service_ends_on' => now()->parse($from)->addYear()->subDay()->toDateString(), 'lines_synced_at' => now(),
            ]);
            LexofficeVoucherLine::create([
                'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => 1, 'type' => 'service',
                'external_article_id' => 'art-exo', 'lexoffice_article_id' => $article->id, 'name' => 'Exchange Online (Plan 1)',
                'quantity' => 12, 'unit_name' => 'Monat', 'unit_net' => '3.95', 'total_net' => '47.40', 'tax_rate' => 19, 'currency' => 'EUR',
            ]);
        }
        $ended = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'provider_account' => 'hanna quist',
            'company_name' => 'Hanna Quist', 'label' => 'Postfach Quist', 'quantity' => 1, 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'cancel',
            'status' => 'ended', 'currency' => 'EUR', 'sale_unit_price' => '47.40', 'article_ref' => 'lex:' . $article->id, 'starts_on' => '2023-02-13', 'ends_on' => '2026-02-13',
        ]);
        (new PeriodPlanner)->sync($ended);
        $this->assertGreaterThan(0, $ended->periods()->due()->count());
        // Gegenstück: beendet ohne fällige offene Periode — erledigt, kein Posteingang.
        $done = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'telekom_marketplace', 'provider_account' => 'alt',
            'company_name' => 'Altbestand Ohnefall', 'label' => 'Postfach Altbestand', 'quantity' => 1, 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'cancel',
            'status' => 'ended', 'currency' => 'EUR', 'sale_unit_price' => '47.40', 'article_ref' => 'lex:' . $article->id, 'starts_on' => '2020-02-13', 'ends_on' => '2021-02-13',
        ]);
        (new PeriodPlanner)->sync($done);
        $done->periods()->update(['status' => 'waived']);

        $assign = route('finance.resale.inbox.assign', ['company' => 'Hanna Quist', 'partner' => $partner->sqid]);
        $this->actingAs($admin)->get(route('finance.resale.inbox'))->assertOk()
            ->assertSee('Postfach Quist')->assertDontSee('Postfach Altbestand')
            ->assertSee(trans_choice('resale.inbox.mentioned', 2, ['customer' => 'Feldmark Systeme GmbH', 'count' => 2]))
            ->assertSee($assign);
        $this->actingAs($admin)->get(route('finance.resale.subscriptions', ['unassigned' => 1]))->assertOk()
            ->assertSee('Postfach Quist')->assertDontSee('Postfach Altbestand');

        // Der Vorschlag belegt den Dialog als Endkunde des Partners vor — auch ohne Partner im Aufruf, über den Rechnungstext.
        $this->actingAs($admin)->get($assign)->assertOk()
            ->assertSee('data-holder="partner"', false)->assertSee('data-customer="' . $partner->sqid . '"', false);
        $plain = route('finance.resale.inbox.assign', ['company' => 'Hanna Quist']);
        $this->actingAs($admin)->get($plain)->assertOk()
            ->assertSee(__('resale.inbox.mentioned_title'))->assertSee('data-customer="' . $partner->sqid . '"', false);
        // „Halter zuordnen“ dort, wo „Noch nicht zugeordnet“ steht; die Suche findet die Firma laut Anbieter.
        $this->actingAs($admin)->get(route('finance.resale.accounts.show', 'telekom_marketplace:hanna quist'))->assertOk()
            ->assertSee(__('resale.holder.assign'))->assertSee($plain);
        $this->actingAs($admin)->get(route('finance.resale.show', $ended->sqid))->assertOk()->assertSee($plain);
        $this->actingAs($admin)->get(route('finance.resale.subscriptions', ['q' => 'Quist', 'status' => 'ended']))->assertOk()->assertSee('Postfach Quist');
        $this->actingAs($admin)->post(route('finance.resale.inbox.store'), ['company' => 'Hanna Quist', 'mode' => 'partner', 'customer_id' => $partner->sqid])
            ->assertSessionHasNoErrors();
        $ended->refresh();
        $this->assertSame((int) $partner->id, (int) $ended->foreignCustomer?->customer_id);
        $this->assertSame((int) $partner->id, (int) $ended->billedTo()?->id, 'jetzt geht die Rechnung an den Partner, seine Rechnungen sind Kandidaten');
    }
}
