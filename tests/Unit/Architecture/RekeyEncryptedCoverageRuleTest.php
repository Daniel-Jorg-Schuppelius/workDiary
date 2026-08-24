<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RekeyEncryptedCoverageRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use App\Console\Commands\SecurityRekeyEncryptedCommand;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „Rekey deckt alle verschlüsselten Felder" (Vollscan
 * 2026-08-23, D1): Die Karte des Commands ist aus den encrypted-Casts
 * abgeleitet — driften kann nur noch, wer Crypt::encryptString DIREKT in
 * einen persistierten Pfad schreibt, ohne ihn in DIRECT_USERS zu
 * registrieren. Genau das fängt dieser Sweep.
 */
class RekeyEncryptedCoverageRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Pfad → Begründung (transient/kein DB-Feld) */
    private const ALLOW_LIST = [
        'app/Console/Commands/SecurityRekeyEncryptedCommand.php' => 'der Rekey selbst',
        'app/Console/Commands/EncryptExistingPii.php' => 'einmaliges Migrationswerkzeug — verschlüsselt Felder, die danach encrypted-Casts tragen',
        'app/Casts/ValueObjectCast.php' => 'generischer Cast; encrypted-Option derzeit von keinem Modell genutzt (sonst greift die Cast-Ableitung nicht — hier registrieren!)',
        'app/Http/Controllers/B2bCatalog/B2bPunchoutController.php' => 'transienter Session-Token, nicht persistiert',
        'app/Services/Applications/CareerFormState.php' => 'transienter Formular-Token, nicht persistiert',
        'app/Services/Shipping/CarrierTokenCache.php' => 'Cache-Eintrag — nach Rotation schlicht neu geholt',
        'app/Models/SystemSetting.php' => 'in DIRECT_USERS registriert (value, is_sensitive=1)',
    ];

    public function test_direct_crypt_users_are_registered_or_transient(): void {
        $violations = [];
        foreach ($this->phpFiles('app') as $file) {
            $relative = $this->relativePath($file);
            if ($this->isAllowListed($relative, self::ALLOW_LIST)) {
                continue;
            }
            $source = $this->stripComments((string) file_get_contents($file));
            if (preg_match('/Crypt::encryptString\(/', $source, $m, PREG_OFFSET_CAPTURE) === 1) {
                $violations[] = sprintf('%s:%d', $relative, $this->lineOf($source, (int) $m[0][1]));
            }
        }

        $this->assertSame([], $violations, "Crypt::encryptString außerhalb der bekannten Stellen — wird der Wert persistiert, MUSS er in\n"
            . "SecurityRekeyEncryptedCommand::DIRECT_USERS registriert werden (sonst überlebt er keine Key-Rotation);\n"
            . "ist er transient, gehört die Datei mit Begründung in die ALLOW_LIST dieses Gates.\n\n" . implode("\n", $violations));
    }

    public function test_derived_map_spans_the_encrypted_casts(): void {
        $map = SecurityRekeyEncryptedCommand::encryptedFieldMap();

        $this->assertGreaterThanOrEqual(45, count($map), 'Ableitung eingebrochen? Es gab 47 Modelle mit encrypted-Casts + SystemSetting.');
        foreach ([
            \App\Models\User::class => 'two_factor_secret',
            \App\Models\ContactBankAccount::class => 'iban',
            \App\Models\IncomingEInvoice::class => 'creditor_iban',
            \App\Models\SystemSetting::class => 'value',
        ] as $class => $field) {
            $this->assertArrayHasKey($field, $map[$class] ?? [], $class);
        }
    }
}
