<?php
/*
 * Created on   : Sat Sep 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FindClearedValueObjectsCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Console;

use App\Models\Customer\Customer;
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * audit:cleared-values (2026-09-19): findet Wertobjekt-Felder, die ein
 * Formular-Speichern still geleert hat — gestützt auf das Audit-Log.
 */
class FindClearedValueObjectsCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actingAs(User::factory()->admin()->create(['organization_id' => $this->organization->id]));
    }

    public function test_lists_cleared_rate_with_previous_value(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Geleert GmbH', 'hourly_rate' => '85.00']);
        $customer->update(['hourly_rate' => null]);

        Artisan::call('audit:cleared-values', ['--since' => now()->subDay()->toDateString()]);
        $output = Artisan::output();

        $this->assertStringContainsString('Geleert GmbH', $output);
        $this->assertStringContainsString('hourly_rate', $output);
        $this->assertStringContainsString('85.00 EUR', $output);
    }

    public function test_refilled_rate_is_only_listed_with_all(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Wieder befüllt AG', 'hourly_rate' => '70.00']);
        $customer->update(['hourly_rate' => null]);
        $customer->update(['hourly_rate' => '72.00']);

        Artisan::call('audit:cleared-values', ['--since' => now()->subDay()->toDateString()]);
        $output = Artisan::output();
        $this->assertStringNotContainsString('Wieder befüllt AG', $output);
        $this->assertStringContainsString('1 weitere Felder', $output);

        Artisan::call('audit:cleared-values', ['--since' => now()->subDay()->toDateString(), '--all' => true]);
        $this->assertStringContainsString('Wieder befüllt AG', Artisan::output());
    }

    public function test_changes_before_since_are_ignored(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Alt KG', 'hourly_rate' => '60.00']);
        $customer->update(['hourly_rate' => null]);

        Artisan::call('audit:cleared-values', ['--since' => now()->addDay()->toDateString()]);
        $this->assertStringContainsString('Keine Treffer', Artisan::output());
    }
}
