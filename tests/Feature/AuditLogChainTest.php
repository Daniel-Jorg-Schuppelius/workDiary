<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditLogChainTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{AuditLog, OrganizationAuditLog};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Sichert die GoBD-Unveränderbarkeit der Audit-Ketten ab: Hash-Verkettung,
 * append-only Guard, Manipulationserkennung (`audit:verify`), die zweite Kette
 * (organization_audit_logs) und die Kettenkopf-Fortschreibung.
 */
class AuditLogChainTest extends TestCase {
    use RefreshDatabase;

    private function makeEntry(string $event, int $auditableId): AuditLog {
        return AuditLog::create([
            'organization_id' => null,
            'user_id' => null,
            'event' => $event,
            'auditable_type' => 'TestModel',
            'auditable_id' => $auditableId,
            'changes' => ['before' => ['x' => 1], 'after' => ['x' => 2]],
            'ip' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);
    }

    public function test_entries_are_hash_chained(): void {
        $a = $this->makeEntry('created', 1);
        $b = $this->makeEntry('updated', 1);

        $this->assertNull($a->prev_hash, 'Erste Zeile hat keinen Vorgänger.');
        $this->assertNotEmpty($a->hash);
        $this->assertSame($a->hash, $b->prev_hash, 'Zweite Zeile verkettet auf die erste.');

        $this->assertSame(0, $this->runVerify());
    }

    public function test_update_is_blocked(): void {
        $entry = $this->makeEntry('created', 1);

        $this->expectException(RuntimeException::class);
        $entry->update(['event' => 'tampered']);
    }

    public function test_delete_is_blocked(): void {
        $entry = $this->makeEntry('created', 1);

        $this->expectException(RuntimeException::class);
        $entry->delete();
    }

    public function test_tampering_breaks_verification(): void {
        $this->makeEntry('created', 1);
        $second = $this->makeEntry('updated', 1);
        $this->makeEntry('deleted', 1);

        $this->assertSame(0, $this->runVerify(), 'Unveränderte Kette ist gültig.');

        // Manipulation am Inhalt unter Umgehung des Modell-Guards (DB::table).
        DB::table('audit_logs')->where('id', $second->id)->update(['event' => 'tampered']);

        $this->assertSame(1, $this->runVerify(), 'Manipulation muss erkannt werden.');
    }

    public function test_organization_audit_log_is_chained(): void {
        $a = OrganizationAuditLog::create([
            'organization_id' => 1,
            'action' => OrganizationAuditLog::ACTION_DEACTIVATE,
        ]);
        $b = OrganizationAuditLog::create([
            'organization_id' => 1,
            'action' => OrganizationAuditLog::ACTION_REACTIVATE,
        ]);

        $this->assertNull($a->prev_hash);
        $this->assertNotEmpty($a->hash);
        $this->assertSame($a->hash, $b->prev_hash);
        $this->assertSame(0, $this->runVerify(), 'Beide Ketten müssen intakt sein.');
    }

    public function test_chain_head_advances(): void {
        $first = $this->makeEntry('created', 1);
        $second = $this->makeEntry('updated', 1);

        $head = DB::table('audit_chain_heads')->where('chain', 'audit_logs')->first();
        $this->assertSame($second->hash, $head->head_hash, 'Kopf zeigt auf die letzte Zeile.');
        $this->assertSame(2, (int) $head->height);
        $this->assertNotSame($first->hash, $head->head_hash);
    }

    private function runVerify(): int {
        return $this->artisan('audit:verify')->run();
    }
}
