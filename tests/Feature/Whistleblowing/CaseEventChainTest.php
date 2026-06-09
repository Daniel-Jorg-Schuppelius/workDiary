<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CaseEventChainTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Whistleblowing;

use App\Services\Whistleblowing\WhistleblowingEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Die Fall-Event-Kette nutzt den HashChained-Trait und ist als dritte Kette in
 * config('audit.chains') registriert → append-only, manipulationsnachweisbar.
 */
class CaseEventChainTest extends TestCase {
    use RefreshDatabase;

    private function svc(): WhistleblowingEventService {
        return app(WhistleblowingEventService::class);
    }

    public function test_events_are_chained_and_verifiable(): void {
        $a = $this->svc()->recordSystem(1, WhistleblowingEventService::CASE_SUBMITTED, ['k' => 'v']);
        $b = $this->svc()->recordSystem(1, WhistleblowingEventService::CASE_VIEWED);

        $this->assertNull($a->prev_hash);
        $this->assertSame($a->hash, $b->prev_hash);

        $this->assertSame(0, $this->artisan('audit:verify')->run());
    }

    public function test_events_are_append_only(): void {
        $event = $this->svc()->recordSystem(1, WhistleblowingEventService::CASE_SUBMITTED);

        $this->expectException(RuntimeException::class);
        $event->update(['event' => 'tampered']);
    }

    public function test_tampering_breaks_verification(): void {
        $this->svc()->recordSystem(1, WhistleblowingEventService::CASE_SUBMITTED);
        $second = $this->svc()->recordSystem(1, WhistleblowingEventService::CASE_VIEWED);
        $this->svc()->recordSystem(1, WhistleblowingEventService::CASE_ACKNOWLEDGED);

        $this->assertSame(0, $this->artisan('audit:verify')->run());

        DB::table('whistleblowing_case_events')->where('id', $second->id)->update(['event' => 'tampered']);

        $this->assertSame(1, $this->artisan('audit:verify')->run());
    }
}
