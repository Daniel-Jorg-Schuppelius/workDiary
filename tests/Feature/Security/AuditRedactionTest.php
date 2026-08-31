<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditRedactionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\{AuditLog, AuditRedaction, Customer, SickLeave, User};
use App\Services\Audit\{AuditChainVerifier, AuditRedactionService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * S-21 (Sicherheitsscan 2026-08-23): Bank- und Gesundheitsdaten standen im
 * Klartext in den Stammtabellen und dauerhaft in `audit_logs.changes`.
 */
class AuditRedactionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $actor;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actor = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->actor);
    }

    public function test_bankdaten_liegen_verschluesselt_in_der_spalte(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'bank_iban' => 'DE89370400440532013000',
        ]);

        $raw = (string) DB::table('customers')->where('id', $customer->id)->value('bank_iban');

        $this->assertNotSame('DE89370400440532013000', $raw, 'IBAN liegt im Klartext in der Spalte.');
        $this->assertSame('DE89370400440532013000', $customer->fresh()->bank_iban, 'IBAN lässt sich nicht zurücklesen.');
    }

    public function test_gesundheitsdaten_liegen_verschluesselt_in_der_spalte(): void {
        $sickLeave = SickLeave::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->actor->id,
            'au_number' => 'AU-4711',
            'doctor_name' => 'Dr. Meier',
        ]);

        $row = DB::table('sick_leaves')->where('id', $sickLeave->id)->first();

        $this->assertNotSame('AU-4711', (string) $row->au_number);
        $this->assertNotSame('Dr. Meier', (string) $row->doctor_name);
        $this->assertSame('AU-4711', $sickLeave->fresh()->au_number);
    }

    public function test_protokoll_haelt_die_aenderung_fest_aber_nicht_den_wert(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'bank_iban' => 'DE89370400440532013000',
        ]);

        $customer->update(['bank_iban' => 'DE02120300000000202051']);

        $log = AuditLog::query()
            ->where('auditable_type', $customer->getMorphClass())
            ->where('auditable_id', $customer->id)
            ->where('event', 'updated')
            ->latest('id')->firstOrFail();

        $changes = $log->changes;

        // Die Änderung selbst bleibt sichtbar …
        $this->assertArrayHasKey('bank_iban', $changes['after'] ?? []);
        // … aber weder alter noch neuer Wert stehen darin, auch nicht als Chiffretext.
        $this->assertSame(AuditLog::REDACTED, $changes['after']['bank_iban']);
        $this->assertSame(AuditLog::REDACTED, $changes['before']['bank_iban']);
        $this->assertStringNotContainsString('DE0212030000', json_encode($changes, JSON_THROW_ON_ERROR));
    }

    public function test_schwaerzung_entfernt_altbestand_und_laesst_die_kette_pruefbar(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        // Altbestand nachstellen: eine Protokollzeile, wie sie vor dem Fix
        // entstanden ist — mit dem Wert im Klartext, korrekt verkettet.
        $log = AuditLog::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->actor->id,
            'event' => 'updated',
            'auditable_type' => $customer->getMorphClass(),
            'auditable_id' => $customer->id,
            'changes' => ['before' => ['bank_iban' => null], 'after' => ['bank_iban' => 'DE89370400440532013000']],
        ]);

        // Danach entstehen weitere Zeilen: die Kette muss ab der geschwärzten
        // Zeile vollständig nachwandern, sonst bricht sie hinten.
        Customer::factory()->count(2)->create(['organization_id' => $this->organization->id]);

        $this->assertTrue(app(AuditChainVerifier::class)->verify('audit_logs', AuditLog::class)['ok']);

        $result = app(AuditRedactionService::class)->redact(
            chainTable: 'audit_logs',
            auditableType: $customer->getMorphClass(),
            auditableId: $customer->id,
            fields: ['bank_iban'],
            reason: 'Löschverlangen Art. 17 DSGVO',
            requestReference: 'DSR-2026-0007',
            actor: $this->actor,
        );

        $this->assertSame(1, $result['rows']);

        $stored = (string) DB::table('audit_logs')->where('id', $log->id)->value('changes');
        $this->assertStringNotContainsString('DE89370400440532013000', $stored);

        // Der entscheidende Punkt: die Kette ist danach wieder rechenbar —
        // eine Schwärzung darf audit:verify nicht dauerhaft rot färben.
        $verification = app(AuditChainVerifier::class)->verify('audit_logs', AuditLog::class);
        $this->assertTrue($verification['ok'], 'Kette nach der Schwärzung nicht mehr prüfbar.');

        // … und der Eingriff ist belegt, mit Kettenkopf davor und danach.
        $redaction = AuditRedaction::query()->latest('id')->firstOrFail();
        $this->assertSame(['bank_iban'], $redaction->fields);
        $this->assertSame('DSR-2026-0007', $redaction->request_reference);
        $this->assertNotSame($redaction->head_before, $redaction->head_after);
        $this->assertTrue(app(AuditChainVerifier::class)->verify('audit_redactions', AuditRedaction::class)['ok']);
    }

    public function test_schwaerzung_ohne_begruendung_wird_abgelehnt(): void {
        $this->artisan('audit:redact', [
            '--type' => 'customer',
            '--id' => 1,
            '--fields' => 'bank_iban',
        ])->assertExitCode(2);
    }

    public function test_probelauf_aendert_nichts(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        AuditLog::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->actor->id,
            'event' => 'updated',
            'auditable_type' => $customer->getMorphClass(),
            'auditable_id' => $customer->id,
            'changes' => ['before' => ['bank_iban' => null], 'after' => ['bank_iban' => 'DE89370400440532013000']],
        ]);

        $this->artisan('audit:redact', [
            '--type' => $customer->getMorphClass(),
            '--id' => $customer->id,
            '--fields' => 'bank_iban',
            '--reason' => 'Probe',
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(0, AuditRedaction::query()->count());
    }
}
