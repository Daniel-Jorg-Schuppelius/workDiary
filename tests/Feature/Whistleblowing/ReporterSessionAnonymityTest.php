<?php
/*
 * Created on   : Sun Sep 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReporterSessionAnonymityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Whistleblowing;

use App\Models\Organization;
use App\Models\Whistleblowing\Portal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sicherheitsaudit 2026-09-13: Die `sessions`-Tabelle führt zu jeder Sitzung
 * Adresse und Browserkennung. Auf dem Meldeportal steht in derselben Zeile die
 * Sitzung, über die der Fall geführt wird — wer die Tabelle liest, verbindet
 * Anschluss und Meldung. Genau diese Verknüpfung darf es dort nicht geben.
 */
class ReporterSessionAnonymityTest extends TestCase {
    use RefreshDatabase;

    private function portal(): Portal {
        $org = Organization::factory()->create();

        return Portal::query()->create([
            'organization_id' => $org->id,
            'public_slug' => 'melde-portal',
            'name' => 'Meldestelle',
            'is_active' => true,
        ]);
    }

    public function test_the_reporting_portal_stores_no_origin_in_the_session(): void {
        config(['session.driver' => 'database']);
        $portal = $this->portal();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->withHeaders(['User-Agent' => 'KanarienvogelBrowser/1.0'])
            ->get('/melden/' . $portal->public_slug);

        $rows = DB::table(config('session.table', 'sessions'))->get();
        $this->assertNotEmpty($rows, 'Ohne Sitzungszeile prueft der Test nichts.');

        foreach ($rows as $row) {
            $this->assertNull($row->ip_address, 'Die Adresse des Meldenden darf nicht in der Sitzung stehen.');
            $this->assertNull($row->user_agent, 'Die Browserkennung des Meldenden darf nicht in der Sitzung stehen.');
        }
    }

    public function test_the_internal_area_keeps_its_session_metadata(): void {
        config(['session.driver' => 'database']);

        // Die interne Oberflaeche braucht die Angaben fuer die Geraeteliste und
        // die Angriffserkennung — dort bleiben sie.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->withHeaders(['User-Agent' => 'InternBrowser/1.0'])
            ->get(route('login'));

        $rows = DB::table(config('session.table', 'sessions'))->get();
        $this->assertNotEmpty($rows);
        $this->assertSame('203.0.113.7', (string) $rows->first()->ip_address);
    }
}
