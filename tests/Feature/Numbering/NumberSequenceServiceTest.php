<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NumberSequenceServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Numbering;

use App\Enums\Numbering\NumberScope;
use App\Models\{NumberSequence, Organization};
use App\Services\Numbering\NumberSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NumberSequenceServiceTest extends TestCase {
    use RefreshDatabase;

    private NumberSequenceService $service;

    private Organization $org;

    protected function setUp(): void {
        parent::setUp();
        $this->service = new NumberSequenceService;
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
    }

    public function test_default_format_matches_legacy_pattern_for_service_ticket(): void {
        Carbon::setTestNow('2026-06-01 10:00:00');

        $this->assertSame('ST-2026-00001', $this->service->next($this->org, NumberScope::ServiceTicket));
        $this->assertSame('ST-2026-00002', $this->service->next($this->org, NumberScope::ServiceTicket));
    }

    public function test_default_format_matches_legacy_pattern_for_asset_and_invoice(): void {
        Carbon::setTestNow('2026-06-01 10:00:00');

        $this->assertSame('AS-2026-0001', $this->service->next($this->org, NumberScope::Asset));
        $this->assertSame('R2026-0001', $this->service->next($this->org, NumberScope::Invoice));
        $this->assertSame('G2026-0001', $this->service->next($this->org, NumberScope::CreditNote));
    }

    public function test_customer_format_has_no_year_and_does_not_reset(): void {
        Carbon::setTestNow('2026-06-01 10:00:00');
        $this->assertSame('K-0001', $this->service->next($this->org, NumberScope::Customer));
        $this->assertSame('K-0002', $this->service->next($this->org, NumberScope::Customer));

        Carbon::setTestNow('2027-01-01 10:00:00');
        $this->assertSame('K-0003', $this->service->next($this->org, NumberScope::Customer));
    }

    public function test_reset_per_year_starts_at_one_in_new_year(): void {
        Carbon::setTestNow('2026-06-01 10:00:00');
        $this->assertSame('AS-2026-0001', $this->service->next($this->org, NumberScope::Asset));

        Carbon::setTestNow('2027-01-01 10:00:00');
        $this->assertSame('AS-2027-0001', $this->service->next($this->org, NumberScope::Asset));
    }

    public function test_isolation_between_organizations(): void {
        $other = Organization::factory()->create();
        Carbon::setTestNow('2026-06-01 10:00:00');

        $this->assertSame('AS-2026-0001', $this->service->next($this->org, NumberScope::Asset));
        $this->assertSame('AS-2026-0001', $this->service->next($other, NumberScope::Asset));
        $this->assertSame('AS-2026-0002', $this->service->next($this->org, NumberScope::Asset));
    }

    public function test_set_format_overrides_defaults(): void {
        Carbon::setTestNow('2026-06-01 10:00:00');

        $this->service->setFormat($this->org, NumberScope::Asset, [
            'prefix' => 'INV',
            'padding' => 6,
        ]);

        $this->assertSame('INV-2026-000001', $this->service->next($this->org, NumberScope::Asset));
    }

    public function test_starts_at_offsets_first_value(): void {
        Carbon::setTestNow('2026-06-01 10:00:00');

        $this->service->setFormat($this->org, NumberScope::ServiceTicket, [
            'starts_at' => 1000,
        ]);

        $this->assertSame('ST-2026-01001', $this->service->next($this->org, NumberScope::ServiceTicket));
    }

    public function test_peek_next_does_not_increment(): void {
        Carbon::setTestNow('2026-06-01 10:00:00');

        $this->assertSame('AS-2026-0001', $this->service->peekNext($this->org, NumberScope::Asset));
        $this->assertSame('AS-2026-0001', $this->service->peekNext($this->org, NumberScope::Asset));
        $this->assertSame('AS-2026-0001', $this->service->next($this->org, NumberScope::Asset));
        $this->assertSame('AS-2026-0002', $this->service->peekNext($this->org, NumberScope::Asset));
    }

    public function test_existing_sequence_is_continued(): void {
        Carbon::setTestNow('2026-06-01 10:00:00');

        NumberSequence::create([
            'organization_id' => $this->org->id,
            'scope' => NumberScope::Asset->value,
            'period' => '2026',
            'last_value' => 42,
        ]);

        $this->assertSame('AS-2026-0043', $this->service->next($this->org, NumberScope::Asset));
    }
}
