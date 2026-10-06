<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginClientFactoryRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Plugins\Support\PluginHttpFactory;
use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Gate zum Konsolidierungs-Audit 2026-10 (k2-02): ein Plugin baut seinen
 * HTTP-Client in einer Client- oder Fabrikklasse — nicht in jedem Dienst neu.
 * Lexoffice tat das an 17 Stellen; Anfrageabstand, Wiederholungsbudget und
 * Authentifizierung griffen dadurch jeweils nur an einem Teil.
 *
 * Erlaubt ist {@see PluginHttpFactory} unter `Api/` sowie in Dateien
 * `*Client.php` und `*Factory.php`.
 */
class PluginClientFactoryRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Datei => Grund */
    private const ALLOWED = [
        'app/Plugins/PhoneDirectory/Services/PhoneDirectoryResolver.php' => 'Einzige HTTP-Stelle des Plugins: Abfrage einer frei konfigurierten Verzeichnis-URL, kein API-Client.',
    ];

    public function test_plugins_build_http_clients_only_in_client_or_factory_classes(): void {
        $violations = [];
        $seen = [];
        foreach ($this->phpFiles('app/Plugins') as $file) {
            $relative = $this->relativePath($file);
            if (str_starts_with($relative, 'app/Plugins/Support/') || preg_match('~/Api/|Client\.php$|Factory\.php$~', $relative) === 1) {
                continue;
            }
            if (! $this->referencesHttpFactory((string) file_get_contents($file))) {
                continue;
            }
            if (isset(self::ALLOWED[$relative])) {
                $seen[$relative] = true;

                continue;
            }
            $violations[] = $relative;
        }

        $this->assertSame([], $violations, "HTTP-Client außerhalb einer Client-/Fabrikklasse gebaut — in die Fabrik des Plugins (`Api/<Name>ClientFactory`) verlegen:\n" . implode("\n", $violations));
        $this->assertSame([], array_values(array_diff(array_keys(self::ALLOWED), array_keys($seen))), 'Veraltete Ausnahmen in ALLOWED.');
    }

    /** Kommentare zählen nicht — nur Code, der die Fabrik benutzt. */
    private function referencesHttpFactory(string $source): bool {
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true) && str_ends_with($token[1], 'PluginHttpFactory')) {
                return true;
            }
        }

        return false;
    }
}
