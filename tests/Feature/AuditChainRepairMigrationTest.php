<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditChainRepairMigrationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, DB};
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Sichert die Reparatur-Migration der ValueObject-Cast-Regression ab
 * (2026_11_08_rechain_audit_hashes_after_value_object_casts): Zeilen, die mit
 * `ip`-als-{}-Semantik gehasht wurden, werden nachweisgeführt neu verkettet;
 * echte Manipulation bricht die Migration ohne Rewrite ab.
 */
class AuditChainRepairMigrationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private function makeEntry(string $event): AuditLog {
        return AuditLog::create([
            'organization_id' => null,
            'user_id' => null,
            'event' => $event,
            'auditable_type' => 'TestModel',
            'auditable_id' => 1,
            'changes' => ['after' => ['x' => 1]],
            'ip' => '10.0.0.7',
            'user_agent' => 'phpunit',
        ]);
    }

    /** Hash in der defekten Cast-Semantik: `ip` als leeres Objekt ({}). */
    private function brokenHash(?string $prev, AuditLog $entry): string {
        $payload = $entry->hashPayload();
        $payload['ip'] = new \stdClass;

        return hash('sha256', (string) $prev . '|' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function runRepair(): void {
        $migration = require database_path('migrations/2026_11_08_100000_rechain_audit_hashes_after_value_object_casts.php');
        $migration->up();
    }

    public function test_cast_regression_rows_are_rechained(): void {
        $a = $this->makeEntry('created');
        $b = $this->makeEntry('updated');
        $c = $this->makeEntry('deleted');

        // Zeilen 2+3 in den Zustand der Cast-Regression zurückversetzen: live
        // mit ip-als-{} gehasht, Verkettung untereinander konsistent.
        $bBroken = $this->brokenHash($a->hash, $b);
        $cBroken = $this->brokenHash($bBroken, $c);
        DB::table('audit_logs')->where('id', $b->id)->update(['hash' => $bBroken]);
        DB::table('audit_logs')->where('id', $c->id)->update(['prev_hash' => $bBroken, 'hash' => $cBroken]);
        DB::table('audit_chain_heads')->where('chain', 'audit_logs')->update(['head_hash' => $cBroken]);

        $this->assertSame(1, Artisan::call('audit:verify'), 'Regressions-Zustand muss die Prüfung brechen.');

        $this->runRepair();

        $this->assertSame(0, Artisan::call('audit:verify'), 'Nach der Reparatur ist die Kette wieder prüfbar.');

        $head = DB::table('audit_chain_heads')->where('chain', 'audit_logs');
        $this->assertSame(DB::table('audit_logs')->where('id', $c->id)->value('hash'), $head->value('head_hash'));
        $this->assertSame(3, (int) $head->value('height'));

        // Zeile 1 war korrekt und bleibt byte-identisch stehen.
        $this->assertSame($a->hash, DB::table('audit_logs')->where('id', $a->id)->value('hash'));
    }

    public function test_intact_chain_is_left_untouched(): void {
        $a = $this->makeEntry('created');
        $b = $this->makeEntry('updated');

        $this->runRepair();

        $this->assertSame($a->hash, DB::table('audit_logs')->where('id', $a->id)->value('hash'));
        $this->assertSame($b->hash, DB::table('audit_logs')->where('id', $b->id)->value('hash'));
        $this->assertSame(0, Artisan::call('audit:verify'));
    }

    /**
     * ON DELETE SET NULL auf audit_logs.organization_id: Das Löschen einer Org
     * nullt Bestandszeilen an der Kette vorbei (Prod: Orgs 2/3, 11 Zeilen).
     * Die Nullung wird als Artefakt anerkannt und neu verkettet; die Spalte
     * bleibt NULL, der Originalwert steht im Nachweis-Protokoll.
     */
    public function test_fk_nulled_organization_rows_are_rechained(): void {
        $this->setUpOrganization();
        $this->makeEntry('created');
        $b = AuditLog::create([
            'organization_id' => $this->organization->id,
            'user_id' => null,
            'event' => 'created',
            'auditable_type' => 'TestModel',
            'auditable_id' => 2,
            'changes' => ['organization_id' => $this->organization->id, 'name' => 'X'],
            'ip' => '10.0.0.7',
            'user_agent' => 'phpunit',
        ]);

        // FK-Kaskade nachstellen: Spalte nullen, Hash bleibt der alte.
        DB::table('audit_logs')->where('id', $b->id)->update(['organization_id' => null]);

        $this->assertSame(1, Artisan::call('audit:verify'), 'Genullte FK-Spalte muss die Prüfung brechen.');

        $this->runRepair();

        $this->assertSame(0, Artisan::call('audit:verify'), 'Nach der Reparatur ist die Kette wieder prüfbar.');
        $this->assertNull(
            DB::table('audit_logs')->where('id', $b->id)->value('organization_id'),
            'Die Spalte bleibt NULL — nur die Kette wird fortgeschrieben.',
        );
    }

    public function test_genuine_tampering_aborts_without_rewrite(): void {
        $this->makeEntry('created');
        $b = $this->makeEntry('updated');

        // Inhalts-Manipulation: entspricht weder korrekter noch Cast-defekter Semantik.
        DB::table('audit_logs')->where('id', $b->id)->update(['event' => 'tampered']);

        try {
            $this->runRepair();
            $this->fail('Manipulation muss die Reparatur abbrechen.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('echte Veränderung', $e->getMessage());
        }

        // Rollback: der (jetzt inkonsistente) Bestand wurde nicht überschrieben.
        $this->assertSame($b->hash, DB::table('audit_logs')->where('id', $b->id)->value('hash'));
        $this->assertSame(1, Artisan::call('audit:verify'), 'Befund bleibt sichtbar statt wegrepariert.');
    }
}
