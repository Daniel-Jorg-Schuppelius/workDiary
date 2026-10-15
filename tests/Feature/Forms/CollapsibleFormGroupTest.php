<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CollapsibleFormGroupTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Forms;

use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\FeatureFlagResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kompakte Kernformulare und kaufmännischer Abschluss (Feature 165,
 * MVP-1193/1194): Zusatzgruppen sind zu, solange nichts drinsteht; Werte
 * und Fehler öffnen sie; Angebot und Rechnung lassen sich aus dem Auftrag
 * heraus mit vorbelegtem Kunden anlegen.
 */
class CollapsibleFormGroupTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->enterprise()->create();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        app()->instance('currentOrganization', $this->org);
        app(FeatureFlagResolver::class)->flush();
    }

    /** @return list<array{legend: string, open: bool, error: bool}> */
    private function formGroups(string $html): array {
        preg_match_all('/<details([^>]*data-form-group[^>]*)>\s*<summary[^>]*>.*?<span>([^<]+)<\/span>/s', $html, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $match) {
            $out[] = [
                'legend' => html_entity_decode($match[2]),
                'open' => str_contains($match[1], ' open'),
                'error' => str_contains($match[1], 'data-form-group-error'),
            ];
        }

        return $out;
    }

    /** @param list<array{legend: string, open: bool, error: bool}> $groups */
    private function formGroup(array $groups, string $legend): array {
        foreach ($groups as $group) {
            if ($group['legend'] === $legend) {
                return $group;
            }
        }
        $this->fail("Gruppe „{$legend}“ fehlt im Formular.");
    }

    public function test_new_customer_form_keeps_extra_groups_closed(): void {
        $html = $this->actingAs($this->admin)->get(route('customers.create', ['dialog' => 1]))->assertOk()->getContent();
        $groups = $this->formGroups($html);

        $this->assertNotEmpty($groups);
        $this->assertFalse($this->formGroup($groups, __('Bankverbindung'))['open']);
        $this->assertFalse($this->formGroup($groups, __('Abrechnung & Darstellung'))['open']);
        $this->assertFalse($this->formGroup($groups, __('Portal & Sonstiges'))['open']);
        // Kernfelder stehen weiterhin im Formular (nicht in einer Zusatzgruppe).
        $this->assertStringContainsString('name="address_street"', $html);
        $this->assertStringContainsString('name="email"', $html);
    }

    public function test_edit_form_opens_groups_with_values_and_keeps_them_in_the_dom(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id, 'bank_iban' => 'DE02120300000000202051', 'comment' => 'Stammkunde']);

        $html = $this->actingAs($this->admin)->get(route('customers.edit', [$customer, 'dialog' => 1]))->assertOk()->getContent();
        $groups = $this->formGroups($html);

        $this->assertTrue($this->formGroup($groups, __('Bankverbindung'))['open']);
        $this->assertTrue($this->formGroup($groups, __('Portal & Sonstiges'))['open']);
        $this->assertStringContainsString('DE02120300000000202051', $html);
        // Zugeklappte Gruppe: Feld bleibt im DOM und wird mitgesendet.
        $this->assertFalse($this->formGroup($groups, __('Anfahrt (Übersteuerung)'))['open']);
        $this->assertStringContainsString('name="travel_settings[mode]"', $html);
    }

    public function test_validation_error_opens_the_group_and_marks_it(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);

        $this->actingAs($this->admin)
            ->from(route('customers.edit', [$customer, 'dialog' => 1]))
            ->put(route('customers.update', $customer), ['name' => $customer->name, 'currency' => 'EUR', 'skonto_percent' => '999'])
            ->assertSessionHasErrors('skonto_percent')
            ->assertRedirect(route('customers.edit', [$customer, 'dialog' => 1]));

        // Folge-Request wie nach dem Redirect: Fehler-Bag in Array-Form der
        // JSON-Sitzung (ohne Cookie bliebe das Objekt in der Test-Sitzung und
        // würde beim Marshalling geleert).
        $html = $this->actingAs($this->admin)
            ->withSession([
                'errors' => ['default' => ['format' => ':message', 'messages' => ['skonto_percent' => ['Skonto darf höchstens 100 % betragen.']]]],
                '_old_input' => ['skonto_percent' => '999'],
            ])
            ->get(route('customers.edit', [$customer, 'dialog' => 1]))->assertOk()->getContent();
        $billing = $this->formGroup($this->formGroups($html), __('Abrechnung & Darstellung'));
        $this->assertTrue($billing['open']);
        $this->assertStringContainsString('value="999"', $html);
        $this->assertTrue($billing['error']);
        $this->assertStringContainsString(__('fields.collapsible.has_errors'), $html);
    }

    public function test_editing_a_customer_keeps_values_of_closed_groups(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id, 'bank_iban' => 'DE02120300000000202051', 'bank_name' => 'Testbank']);

        // Nur der Kern wird geändert; die Bankfelder werden unverändert mitgesendet.
        $this->actingAs($this->admin)->put(route('customers.update', $customer), [
            'name' => 'Neuer Name',
            'currency' => 'EUR',
            'bank_iban' => 'DE02120300000000202051',
            'bank_name' => 'Testbank',
        ])->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertSame('Neuer Name', $customer->name);
        $this->assertSame('DE02120300000000202051', $customer->bank_iban);
        $this->assertSame('Testbank', $customer->bank_name);
    }

    public function test_order_offers_quote_and_invoice_with_prefilled_customer(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id, 'name' => 'Vorbelegt GmbH']);
        $entry = DiaryEntry::factory()->for($this->admin)->create(['organization_id' => $this->org->id, 'customer_id' => $customer->id]);

        $html = $this->actingAs($this->admin)->get(route('diary.show', $entry))->assertOk()->getContent();
        $this->assertStringContainsString(route('quotes.create', ['customer_id' => $customer->sqid]), $html);
        $this->assertStringContainsString(route('invoices.create', ['customer_id' => $customer->sqid]), $html);

        $quote = $this->actingAs($this->admin)->get(route('quotes.create', ['customer_id' => $customer->sqid, 'dialog' => 1]))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="' . preg_quote($customer->sqid, '/') . '"[^>]*selected/', $quote);

        $invoice = $this->actingAs($this->admin)->get(route('invoices.create', ['customer_id' => $customer->sqid, 'dialog' => 1]))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="' . preg_quote($customer->sqid, '/') . '"[^>]*selected/', $invoice);
    }

    public function test_foreign_customer_is_not_prefilled(): void {
        $other = Organization::factory()->enterprise()->create();
        $foreign = Customer::factory()->create(['organization_id' => $other->id]);

        $html = $this->actingAs($this->admin)->get(route('quotes.create', ['customer_id' => $foreign->sqid, 'dialog' => 1]))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/<option value="' . preg_quote($foreign->sqid, '/') . '"[^>]*selected/', $html);
    }

    public function test_order_without_sales_module_shows_no_billing_actions(): void {
        $free = Organization::factory()->free()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $free->id]);
        app()->instance('currentOrganization', $free);
        app(FeatureFlagResolver::class)->flush();
        $customer = Customer::factory()->create(['organization_id' => $free->id]);
        $entry = DiaryEntry::factory()->for($admin)->create(['organization_id' => $free->id, 'customer_id' => $customer->id]);

        $html = $this->actingAs($admin)->get(route('diary.show', $entry))->assertOk()->getContent();
        $this->assertStringNotContainsString('/angebote/neu', $html);
        $this->assertStringNotContainsString('/invoices/create', $html);
    }
}
