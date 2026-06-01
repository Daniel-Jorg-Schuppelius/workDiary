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
use App\Models\Organization;
use App\Plugins\Lexoffice\LexofficeNumberAuthority;
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

    public function test_local_is_default_for_accounting_scopes(): void {
        $this->assertFalse($this->authority->isExternal($this->org, NumberScope::Customer));
        $this->assertFalse($this->authority->isExternal($this->org, NumberScope::Invoice));
    }

    public function test_non_accounting_scopes_are_never_external(): void {
        (new LexofficeNumberAuthority($this->numbers))->apply($this->org, true);

        $this->assertFalse($this->authority->isExternal($this->org, NumberScope::ServiceTicket));
        $this->assertFalse($this->authority->isExternal($this->org, NumberScope::Asset));
    }

    public function test_lexoffice_authority_marks_accounting_scopes_external(): void {
        (new LexofficeNumberAuthority($this->numbers))->apply($this->org, true);

        $this->assertTrue($this->authority->isExternal($this->org, NumberScope::Customer));
        $this->assertTrue($this->authority->isExternal($this->org, NumberScope::Supplier));
        $this->assertTrue($this->authority->isExternal($this->org, NumberScope::Invoice));
        $this->assertTrue($this->authority->isExternal($this->org, NumberScope::CreditNote));
    }

    public function test_disabling_lexoffice_authority_resets_to_local(): void {
        $helper = new LexofficeNumberAuthority($this->numbers);
        $helper->apply($this->org, true);
        $helper->apply($this->org, false);

        $this->assertFalse($this->authority->isExternal($this->org, NumberScope::Customer));
    }

    public function test_next_returns_draft_number_when_external(): void {
        Carbon::setTestNow('2026-06-01 10:00:00');
        (new LexofficeNumberAuthority($this->numbers))->apply($this->org, true);

        $number = $this->numbers->next($this->org, NumberScope::Invoice);

        $this->assertStringStartsWith('ENTWURF-', $number);
    }

    public function test_next_returns_regular_number_for_non_accounting_scope_even_when_external(): void {
        Carbon::setTestNow('2026-06-01 10:00:00');
        (new LexofficeNumberAuthority($this->numbers))->apply($this->org, true);

        $number = $this->numbers->next($this->org, NumberScope::ServiceTicket);

        $this->assertStringStartsWith('ST-2026-', $number);
    }
}
