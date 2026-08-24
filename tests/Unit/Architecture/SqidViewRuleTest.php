<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SqidViewRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate für die Sqid-Konvention in Views (Vollscan 2026-08-23,
 * D9/E11/I7): Formulare und Routen transportieren Sqids, keine rohen
 * Datenbank-IDs (Memory „Sqid statt roher IDs"; Route-Binding-Tests prüfen nur
 * die Routen, nicht die Views). Neue Verstöße kamen mit MVP-604 (Rabattgruppen)
 * und dem Entsorger-Dialog.
 *
 * Regel: `value="{{ $x->id }}"` / `:value="$x->id"` und `route('…', $x->id)`
 * (rohe ID als Routenparameter) sind verboten — `$x->sqid` bzw.
 * Sqid::encode() verwenden; Controller/Requests dekodieren über
 * DecodesSqidInputs. Bekannte Altfälle stehen mit Welle-Verweis in der ALLOW_LIST.
 */
class SqidViewRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Pfad-Präfix → Begründung / Nachzieh-Welle */
    private const ALLOW_LIST = [
        'resources/views/legacy/' => 'Legacy-Modul, wird abgelöst.',
        // Welle 3/4 (I7): View sendet rohe IDs, Controller erwartet Sqids.
        'resources/views/tours/edit.blade.php' => 'Welle 4 (I7): order_ids[] auf Sqid.',
        'resources/views/articles/sales_discount_groups.blade.php' => 'Welle 4 (I7): Overrides auf Sqid + DecodesSqidInputs.',
        'resources/views/disposal/_handover_dialog.blade.php' => 'Welle 4 (I7): Entsorger-Select auf Sqid.',
        'resources/views/admin/access/members/_form_dialog.blade.php' => 'Welle 4 (D9): Rollen-IDs (Spatie) — Sqid-Encoder für Role prüfen.',
        'resources/views/admin/access/groups/_form_dialog.blade.php' => 'Welle 4 (D9): Rollen-IDs (Spatie).',
        'resources/views/admin/cloud-intake/index.blade.php' => 'Welle 4 (D9): Container-Picker.',
        'resources/views/admin/audit-diff/index.blade.php' => 'Welle 4 (D9): Audit-Diff-Auswahl (Plattform-Admin).',
        // refactoring-backlog 3.3: Roh-{id}-Routen admin/privacy + api-tokens (bewusst, Admin-only).
        'resources/views/admin/privacy/index.blade.php' => 'refactoring-backlog 3.3: Sessions/Tokens-Routen mit Roh-ID (Plattform-Admin).',
        // Benachrichtigungen tragen UUIDs — keine aufzählbaren Integer-IDs.
        'resources/views/notifications/index.blade.php' => 'Notification-IDs sind UUIDs, keine Sqid-Kandidaten.',
    ];

    public function test_views_do_not_expose_raw_database_ids(): void {
        $violations = [];

        foreach ($this->bladeFiles() as $file) {
            $relative = $this->relativePath($file);
            if ($this->isAllowListed($relative, self::ALLOW_LIST)) {
                continue;
            }

            $source = $this->stripBladeComments((string) file_get_contents($file));

            // Formularwerte: value="{{ $x->id }}" bzw. :value="$x->id".
            if (preg_match_all('/(?::value="\$[a-zA-Z_]+->id"|value="\{\{\s*\$[a-zA-Z_]+->id\s*\}\}")/', $source, $matches, PREG_OFFSET_CAPTURE) > 0) {
                foreach ($matches[0] as [$match, $offset]) {
                    $violations[] = sprintf('%s:%d — %s', $relative, $this->lineOf($source, (int) $offset), $match);
                }
            }

            // Routenparameter: route('…', $x->id) — Sqid::encode(…) und
            // Array-Indizes ($map[$x->id]) sind keine Verstöße.
            $routes = (string) preg_replace('/Sqid::encode\([^()]*(?:\([^()]*\)[^()]*)*\)/', 'Sqid::encode(…)', $source);
            $routes = (string) preg_replace('/\[\$[a-zA-Z_]+->id\]/', '[…]', $routes);
            if (preg_match_all('/route\([^)\n]*\$[a-zA-Z_]+->id(?![\w(])/', $routes, $matches, PREG_OFFSET_CAPTURE) > 0) {
                foreach ($matches[0] as [$match, $offset]) {
                    $violations[] = sprintf('%s:%d — %s', $relative, $this->lineOf($routes, (int) $offset), trim($match));
                }
            }
        }

        sort($violations);

        $this->assertSame([], $violations, "Rohe Datenbank-ID in einer View — Sqid-Konvention verletzt.\n"
            . "Formulare: \$model->sqid als value; Routen: Route-Model-Binding mit dem Modell oder Sqid::encode().\n"
            . "Request-Seite über DecodesSqidInputs (\$sqidFields) + ExistsInCurrentOrganization.\n\n"
            . implode("\n", $violations));
    }
}
