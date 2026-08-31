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

use App\Models\{AuditLog, Organization, OrganizationAuditLog};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, DB};
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Sichert die GoBD-Unveränderbarkeit der Audit-Ketten ab: Hash-Verkettung,
 * append-only Guard, Manipulationserkennung (`audit:verify`), die zweite Kette
 * (organization_audit_logs) und die Kettenkopf-Fortschreibung.
 */
class AuditLogChainTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

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

    public function test_deleting_a_row_breaks_prev_hash_link(): void {
        $this->makeEntry('created', 1);
        $second = $this->makeEntry('updated', 1);
        $this->makeEntry('deleted', 1);

        $this->assertSame(0, $this->runVerify(), 'Unveränderte Kette ist gültig.');

        // Mittlere Zeile unter Umgehung des Append-only-Guards entfernen: die
        // dritte Zeile verweist nun per prev_hash ins Leere → prev_hash-Bruch
        // (anderer Erkennungszweig als die Inhalts-Manipulation).
        DB::table('audit_logs')->where('id', $second->id)->delete();

        $this->assertSame(1, $this->runVerify(), 'Gelöschte Zeile muss die Kette brechen.');
    }

    public function test_verify_accepts_explicit_chain_option(): void {
        $this->makeEntry('created', 1);

        $this->assertSame(0, $this->artisan('audit:verify', ['--chain' => 'audit_logs'])->run());
        $this->assertSame(
            \Symfony\Component\Console\Command\Command::INVALID,
            $this->artisan('audit:verify', ['--chain' => 'does_not_exist'])->run(),
            'Unbekannte Kette wird als INVALID zurückgewiesen.',
        );
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

        // Kette je Organisation (MVP-722); ohne Organisation ist es `:0`.
        $head = DB::table('audit_chain_heads')->where('chain', 'audit_logs:0')->first();
        $this->assertSame($second->hash, $head->head_hash, 'Kopf zeigt auf die letzte Zeile.');
        $this->assertSame(2, (int) $head->height);
        $this->assertNotSame($first->hash, $head->head_hash);
    }

    /**
     * MVP-722 (Vollscan A5): Zwei Organisationen schreiben verschränkt. Jede
     * führt ihre eigene Kette — sonst sperrten sie denselben Kettenkopf und
     * verklemmten sich (gemessen: 445 von 900 Einfügungen abgebrochen).
     */
    public function test_two_organizations_keep_separate_chains(): void {
        $one = Organization::factory()->create();
        $two = Organization::factory()->create();
        // Das Anlegen der Organisation schreibt selbst schon Audit-Zeilen.
        $headOne = $this->chainHead($one);
        $headTwo = $this->chainHead($two);

        $a1 = $this->makeOrgEntry($one, 'created', 1);
        $b1 = $this->makeOrgEntry($two, 'created', 2);
        $a2 = $this->makeOrgEntry($one, 'updated', 1);
        $b2 = $this->makeOrgEntry($two, 'updated', 2);

        // Jede Zeile hängt am Kopf IHRER Organisation, nicht an der Vorzeile der Tabelle.
        $this->assertSame($headOne, $a1->prev_hash);
        $this->assertSame($headTwo, $b1->prev_hash, 'Die zweite Organisation führt eine eigene Kette.');
        $this->assertSame($a1->hash, $a2->prev_hash);
        $this->assertSame($b1->hash, $b2->prev_hash);
        $this->assertNotSame($a1->hash, $b1->prev_hash);

        foreach ([[$one, $a2], [$two, $b2]] as [$organization, $last]) {
            $head = DB::table('audit_chain_heads')->where('chain', 'audit_logs:' . $organization->id)->first();
            $this->assertNotNull($head, 'Jede Organisation hat einen eigenen Kettenkopf.');
            $this->assertSame($last->hash, $head->head_hash);
            $this->assertSame(
                AuditLog::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->count(),
                (int) $head->height,
            );
        }

        $this->assertSame(0, $this->runVerify(), 'Beide Ketten müssen einzeln verifizieren.');
    }

    /** Ein Bruch in Kette A darf Kette B nicht mitreißen — und muss auffallen. */
    public function test_tampering_is_detected_within_the_owning_chain(): void {
        $one = Organization::factory()->create();
        $two = Organization::factory()->create();

        $this->makeOrgEntry($one, 'created', 1);
        $victim = $this->makeOrgEntry($two, 'created', 2);
        $this->makeOrgEntry($one, 'updated', 1);

        $this->assertSame(0, $this->runVerify());

        DB::table('audit_logs')->where('id', $victim->id)->update(['event' => 'tampered']);

        $this->assertSame(1, $this->runVerify(), 'Manipulation in der zweiten Kette muss erkannt werden.');
    }

    private function chainHead(Organization $organization): ?string {
        $value = DB::table('audit_chain_heads')->where('chain', 'audit_logs:' . $organization->id)->value('head_hash');

        return $value === null ? null : (string) $value;
    }

    private function makeOrgEntry(Organization $organization, string $event, int $auditableId): AuditLog {
        return AuditLog::create([
            'organization_id' => $organization->id,
            'user_id' => null,
            'event' => $event,
            'auditable_type' => 'TestModel',
            'auditable_id' => $auditableId,
            'changes' => ['before' => ['x' => 1], 'after' => ['x' => 2]],
            'ip' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);
    }

    /**
     * Byte-genauer Pin gegen die IpAddressCast-Regression (1e6320f0): `ip` muss
     * als Roh-String in den Hash eingehen — als Cast-Objekt würde json_encode
     * `{}` serialisieren und alle Bestands-Hashes unlesbar machen.
     */
    public function test_ip_is_hashed_as_raw_string_not_as_cast_object(): void {
        $entry = $this->makeEntry('created', 1);

        $payload = [
            'user_id' => null,
            'organization_id' => null,
            'event' => 'created',
            'auditable_type' => 'TestModel',
            'auditable_id' => 1,
            'changes' => ['before' => ['x' => 1], 'after' => ['x' => 2]],
            'ip' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'created_at' => $entry->created_at?->format('Y-m-d H:i:s'),
        ];
        $expected = hash('sha256', '|' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->assertSame($expected, $entry->hash, 'ip muss als String gehasht werden, nicht als ValueObject.');
    }

    /**
     * Der Hash muss NACH allen attributmutierenden creating-Listenern rechnen:
     * Die BelongsToOrganization-Auto-Befüllung setzte organization_id früher
     * erst nach der Hash-Berechnung — die Zeile war ab Geburt unprüfbar.
     */
    public function test_auto_filled_organization_is_part_of_the_hash(): void {
        $this->setUpOrganization();
        $entry = $this->makeEntry('created', 1); // organization_id null → Auto-Befüllung greift

        $this->assertSame((int) $this->organization->id, (int) $entry->organization_id);
        $this->assertSame(0, Artisan::call('audit:verify'), 'Auto-befüllte organization_id muss im Hash stecken.');
    }

    /**
     * S-36 (Sicherheitsscan 2026-08-23): Wer die letzten Zeilen einer Kette
     * löscht und `head_hash`/`height` mitzieht, hinterlässt eine Kette, die
     * sich zeilenweise fehlerfrei nachrechnen lässt — sie ist nur kürzer.
     * Ohne Abgleich gegen den Kettenkopf bliebe das unentdeckt, und ein
     * grünes `audit:verify` würde eine Zusage geben, die es nicht hält.
     */
    public function test_abschneiden_am_ende_wird_erkannt(): void {
        $this->makeEntry('created', 1);
        $last = $this->makeEntry('updated', 1);

        $this->assertSame(0, $this->runVerify(), 'Vorbedingung: die Kette ist intakt.');

        // Nur die Zeile entfernen — der Kopf zeigt noch auf sie.
        DB::table('audit_logs')->where('id', $last->id)->delete();

        $this->assertSame(1, $this->runVerify(), 'Fehlende Endzeile muss auffallen.');
    }

    /**
     * Der Kettenkopf liegt in einer eigenen Tabelle, die die DB-Härtung
     * bewusst beschreibbar lässt — wer beides konsistent fälscht, fällt über
     * die Höhe.
     */
    public function test_mitgezogener_kettenkopf_faellt_ueber_die_hoehe_auf(): void {
        $first = $this->makeEntry('created', 1);
        $last = $this->makeEntry('updated', 1);

        $chain = $last->chainName();
        DB::table('audit_logs')->where('id', $last->id)->delete();
        DB::table('audit_chain_heads')->where('chain', $chain)->update([
            'head_hash' => $first->hash,
            // Höhe absichtlich NICHT mitgezogen: genau daran hängt der Nachweis.
        ]);

        $this->assertSame(1, $this->runVerify(), 'Die Kettenhöhe muss den Verlust zeigen.');
    }

    /** Ein entfernter Kettenkopf ist selbst schon der Befund. */
    public function test_fehlender_kettenkopf_wird_erkannt(): void {
        $entry = $this->makeEntry('created', 1);

        DB::table('audit_chain_heads')->where('chain', $entry->chainName())->delete();

        $this->assertSame(1, $this->runVerify(), 'Ohne Kettenkopf fehlt der Anker.');
    }

    public function test_payload_object_value_is_rejected(): void {
        $this->expectException(\InvalidArgumentException::class);
        AuditLog::chainHash(null, ['ip' => new \stdClass]);
    }

    private function runVerify(): int {
        return $this->artisan('audit:verify')->run();
    }
}
