<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NumberAuthorityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Numbering;

use App\Enums\Numbering\NumberScope;
use App\Models\Customer\Customer;
use App\Models\Numbering\NumberFormat;
use App\Models\Platform\{Organization, PluginSetting};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Services\LexofficeNumberAuthority;
use App\Services\Numbering\{NumberAuthority, NumberSequenceService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NumberAuthorityTest extends TestCase {
    use RefreshDatabase;

    private NumberAuthority $authority;

    private NumberSequenceService $numbers;

    private Organization $org;

    protected function setUp(): void {
        parent::setUp();
        $this->authority = new NumberAuthority;
        $this->numbers = new NumberSequenceService;
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
    }

    private function enableLexoffice(): void {
        PluginSetting::create([
            'organization_id' => $this->org->id,
            'plugin_id' => LexofficePlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'k'],
        ]);
    }

    public function test_local_is_default_for_accounting_scopes(): void {
        $this->assertFalse($this->authority->isExternal($this->org, NumberScope::Customer));
        $this->assertFalse($this->authority->isExternal($this->org, NumberScope::Invoice));
    }

    public function test_non_accounting_scopes_are_never_external(): void {
        $this->enableLexoffice();
        $this->numbers->setFormat($this->org, NumberScope::ServiceTicket, ['source' => LexofficePlugin::ID]);

        $this->assertFalse($this->authority->isExternal($this->org, NumberScope::ServiceTicket));
        $this->assertFalse($this->authority->isExternal($this->org, NumberScope::Asset));
    }

    public function test_lexoffice_switch_only_covers_document_scopes(): void {
        $this->enableLexoffice();
        (new LexofficeNumberAuthority($this->numbers))->apply($this->org, true);

        $this->assertSame(LexofficePlugin::ID, $this->authority->sourceFor($this->org, NumberScope::Invoice));
        $this->assertTrue($this->authority->isExternal($this->org, NumberScope::CreditNote));
        $this->assertTrue($this->authority->isExternal($this->org, NumberScope::Cancellation));
        // Kunden- und Lieferantennummern wählt man auf der Seite „Nummernkreise“ (MVP-1130).
        $this->assertFalse($this->authority->isExternal($this->org, NumberScope::Customer));
        $this->assertFalse($this->authority->isExternal($this->org, NumberScope::Supplier));
    }

    public function test_switching_lexoffice_off_resets_only_its_own_scopes(): void {
        $this->enableLexoffice();
        $helper = new LexofficeNumberAuthority($this->numbers);
        $helper->apply($this->org, true);
        $this->numbers->setFormat($this->org, NumberScope::Customer, ['source' => LexofficePlugin::ID]);

        $helper->apply($this->org, false);

        $this->assertFalse($this->authority->isExternal($this->org, NumberScope::Invoice));
        $this->assertTrue($this->authority->isExternal($this->org, NumberScope::Customer));
    }

    public function test_inactive_program_falls_back_to_local_numbers(): void {
        $this->numbers->setFormat($this->org, NumberScope::Customer, ['source' => LexofficePlugin::ID]);

        $this->assertSame(LexofficePlugin::ID, $this->authority->configuredSource($this->org, NumberScope::Customer));
        $this->assertSame(NumberAuthority::SOURCE_LOCAL, $this->authority->sourceFor($this->org, NumberScope::Customer));

        $customer = Customer::factory()->create(['organization_id' => $this->org->id, 'number' => null]);
        $this->assertSame('local', $customer->number_source);
        $this->assertFalse(NumberSequenceService::isDraft($customer->number));
    }

    public function test_customer_records_the_issuing_program_as_number_source(): void {
        $this->enableLexoffice();
        $this->numbers->setFormat($this->org, NumberScope::Customer, ['source' => LexofficePlugin::ID]);

        $customer = Customer::factory()->create(['organization_id' => $this->org->id, 'number' => null]);

        $this->assertSame(LexofficePlugin::ID, $customer->number_source);
        $this->assertTrue(NumberSequenceService::isDraft($customer->number));
    }

    public function test_setting_only_the_source_keeps_the_format(): void {
        $this->numbers->setFormat($this->org, NumberScope::Invoice, ['prefix' => 'RE', 'padding' => 6, 'starts_at' => 500]);

        (new LexofficeNumberAuthority($this->numbers))->apply($this->org, true);

        /** @var NumberFormat $format */
        $format = NumberFormat::query()->where('organization_id', $this->org->id)->where('scope', NumberScope::Invoice->value)->firstOrFail();
        $this->assertSame('RE', $format->prefix);
        $this->assertSame(6, $format->padding);
        $this->assertSame(500, $format->starts_at);
        $this->assertSame(LexofficePlugin::ID, $format->source);
    }

    public function test_migration_moves_external_to_the_lexoffice_id(): void {
        $format = $this->numbers->setFormat($this->org, NumberScope::Customer, ['source' => 'external']);

        $migration = require database_path('migrations/2027_03_18_100000_number_formats_source_plugin_id.php');
        $migration->up();

        $this->assertSame(LexofficePlugin::ID, $format->fresh()?->source);
    }

    public function test_next_returns_draft_number_when_external(): void {
        Carbon::setTestNow('2026-06-01 10:00:00');
        $this->enableLexoffice();
        (new LexofficeNumberAuthority($this->numbers))->apply($this->org, true);

        $number = $this->numbers->next($this->org, NumberScope::Invoice);

        $this->assertStringStartsWith('ENTWURF-', $number);
    }

    public function test_next_returns_regular_number_for_non_accounting_scope_even_when_external(): void {
        Carbon::setTestNow('2026-06-01 10:00:00');
        $this->enableLexoffice();
        (new LexofficeNumberAuthority($this->numbers))->apply($this->org, true);

        $number = $this->numbers->next($this->org, NumberScope::ServiceTicket);

        $this->assertStringStartsWith('ST-2026-', $number);
    }
}
