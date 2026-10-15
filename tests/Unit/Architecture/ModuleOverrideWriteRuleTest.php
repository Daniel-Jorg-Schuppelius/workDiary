<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleOverrideWriteRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Der Modulstatus einer Organisation wird nur über den ModuleScopeService
 * geschrieben (Feature 165, MVP-1188): Nur so kennt der Dienst die bewusst
 * gewählten Module und kann Lizenzzuwachs abgleichen. Plattformweite Flags
 * (organization_id NULL) sind keine Umfangsentscheidung und bleiben beim
 * Lizenz-Admin.
 */
class ModuleOverrideWriteRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Datei => Grund */
    private const ALLOWED = [
        'app/Services/Licensing/ModuleScopeService.php' => 'Die eine Schreibstelle des Org-Modulstatus.',
        'app/Http/Controllers/Admin/LicenseAdminController.php' => 'toggleFlag(): plattformweite Flags ohne Organisation (Plattform-Admin), kein Org-Umfang.',
    ];

    public function test_license_flag_overrides_are_written_only_by_the_scope_service(): void {
        $pattern = '/LicenseFlagOverride::(query\(\)->)?[^;]*?->(create|firstOrCreate|updateOrCreate|delete|forceDelete)\(|LicenseFlagOverride::(create|firstOrCreate|updateOrCreate)\(/s';
        $violations = [];
        foreach ($this->phpFiles('app') as $file) {
            $relative = $this->relativePath($file);
            if (isset(self::ALLOWED[$relative])) {
                continue;
            }
            $source = (string) file_get_contents($file);
            if (! str_contains($source, 'LicenseFlagOverride')) {
                continue;
            }
            if (preg_match($pattern, $source) === 1) {
                $violations[] = $relative;
            }
        }
        sort($violations);

        $this->assertSame([], $violations, "Org-Modulstatus außerhalb von ModuleScopeService geschrieben (MVP-1188):\n" . implode("\n", $violations));
    }

    public function test_allowlist_is_still_needed(): void {
        foreach (self::ALLOWED as $relative => $reason) {
            $this->assertFileExists($this->repoRoot() . '/' . $relative, $reason);
        }
    }
}
