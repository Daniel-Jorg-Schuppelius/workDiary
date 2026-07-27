<?php
/*
 * Created on   : Mon Jul 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdentifierIssueHintTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Stammdaten;

use App\Models\{Customer, User};
use App\Services\Stammdaten\IdentifierIssueDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Der Hinweis in der Oberfläche ist der Weg, auf dem fehlerhafte Stammdaten
 * (die aus Lexoffice stammen) dort korrigiert werden, wo jemand sie kennt.
 */
class IdentifierIssueHintTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private function detector(): IdentifierIssueDetector {
        return app(IdentifierIssueDetector::class);
    }

    public function test_tax_number_in_vat_field_is_reported_with_its_reason(): void {
        $customer = new Customer(['vat_id' => '16/526/00164']);

        $issues = $this->detector()->forModel($customer);

        $this->assertCount(1, $issues);
        $this->assertSame('vat_id', $issues[0]['field']);
        $this->assertSame(__('stammdaten.identifier.reason.tax_number_in_vat_field'), $issues[0]['reason']);
    }

    public function test_invalid_vat_id_is_reported_as_checksum_problem(): void {
        $issues = $this->detector()->forModel(new Customer(['vat_id' => 'DE112499262']));

        $this->assertCount(1, $issues);
        $this->assertSame(__('stammdaten.identifier.reason.vat_invalid'), $issues[0]['reason']);
    }

    public function test_iban_with_one_digit_too_many_gets_a_unique_suggestion(): void {
        $issues = $this->detector()->forModel(new Customer(['bank_iban' => 'DE791005000001068540253']));

        $this->assertCount(1, $issues);
        $this->assertSame('DE79100500001068540253', $issues[0]['suggestion']);
    }

    public function test_malformed_bic_is_reported_without_guessing_a_correction(): void {
        // Realfall aus der Produktion: zehnstellig, ein Buchstabe fehlt.
        $issues = $this->detector()->forModel(new Customer(['bank_bic' => 'BYLDEM1001']));

        $this->assertCount(1, $issues);
        $this->assertSame(__('stammdaten.identifier.reason.bic_invalid'), $issues[0]['reason']);
        $this->assertNull($issues[0]['suggestion'], 'ohne Bankverzeichnis kein Rateversuch');
    }

    public function test_valid_master_data_reports_nothing(): void {
        $customer = new Customer([
            'vat_id' => 'DE811907980',
            'bank_iban' => 'DE89370400440532013000',
            'bank_bic' => 'DEUTDEFF',
        ]);

        $this->assertSame([], $this->detector()->forModel($customer));
    }

    public function test_hint_is_rendered_on_the_customer_page(): void {
        $this->setUpOrganization();
        $user = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'vat_id' => '16/526/00164',
        ]);

        $this->actingAs($user)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee(__('stammdaten.identifier.heading'))
            ->assertSee('16/526/00164');
    }
}
