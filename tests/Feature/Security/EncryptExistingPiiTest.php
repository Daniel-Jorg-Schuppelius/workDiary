<?php
/*
 * Created on   : Sun Jun 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EncryptExistingPiiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Crypt, DB};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Sichert die Bestands-Verschlüsselung vorhandener Klartext-PII ab
 * (security:encrypt-existing). Kritischer, APP_KEY-abhängiger Einmal-Lauf:
 * muss Klartext verschlüsseln, bereits verschlüsselte Werte unangetastet
 * lassen (idempotent) und im Dry-Run nichts schreiben.
 */
class EncryptExistingPiiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** Schreibt Klartext roh in die (sonst encrypted-gecasteten) Spalten. */
    private function userWithPlaintextPii(string $taxId, string $ssn): User {
        /** @var User $user */
        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        DB::table('users')->where('id', $user->id)->update([
            'tax_identification_number' => $taxId,
            'social_security_number' => $ssn,
        ]);

        return $user;
    }

    /** Roher (an den Casts vorbei gelesener) Spaltenwert. */
    private function rawValue(int $userId, string $column): ?string {
        $value = DB::table('users')->where('id', $userId)->value($column);

        return $value === null ? null : (string) $value;
    }

    public function test_encrypts_plaintext_pii_and_keeps_value_recoverable(): void {
        $user = $this->userWithPlaintextPii('DE123456789', '65 170839 K 003');

        $this->artisan('security:encrypt-existing')->assertExitCode(0);

        // Rohwert ist nicht mehr Klartext, lässt sich aber zum Original entschlüsseln.
        $rawTax = (string) $this->rawValue($user->id, 'tax_identification_number');
        $this->assertNotSame('DE123456789', $rawTax);
        $this->assertSame('DE123456789', Crypt::decryptString($rawTax));

        // Über den Modell-Cast gelesen erscheint wieder der Klartext.
        $user->refresh();
        $this->assertSame('DE123456789', $user->tax_identification_number);
        $this->assertSame('65 170839 K 003', $user->social_security_number);
    }

    public function test_is_idempotent_and_does_not_double_encrypt(): void {
        $user = $this->userWithPlaintextPii('DE111', 'SSN-1');

        $this->artisan('security:encrypt-existing')->assertExitCode(0);
        $afterFirst = (string) $this->rawValue($user->id, 'tax_identification_number');

        // Zweiter Lauf darf bereits verschlüsselte Werte nicht erneut verschlüsseln.
        $this->artisan('security:encrypt-existing')->assertExitCode(0);
        $afterSecond = (string) $this->rawValue($user->id, 'tax_identification_number');

        $this->assertSame($afterFirst, $afterSecond, 'Bereits verschlüsselt → unverändert.');
        // Genau eine Entschlüsselungsstufe: Ergebnis ist wieder Klartext, kein Cipher.
        $plain = Crypt::decryptString($afterSecond);
        $this->assertSame('DE111', $plain);
        $this->assertFalse($this->isCipher($plain), 'Kein doppelt verschlüsselter Wert.');
    }

    public function test_dry_run_writes_nothing(): void {
        $user = $this->userWithPlaintextPii('DE999', 'SSN-9');

        $this->artisan('security:encrypt-existing', ['--dry-run' => true])->assertExitCode(0);

        // Klartext bleibt im Dry-Run unangetastet.
        $this->assertSame('DE999', $this->rawValue($user->id, 'tax_identification_number'));
    }

    private function isCipher(string $value): bool {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
}
