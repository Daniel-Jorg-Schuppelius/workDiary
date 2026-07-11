<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlScopePreflight.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\JtlWawi\Services;

use App\Models\JtlConnection;

/**
 * Scope-Preflight (Feature 078, MVP-317): prüft die gewährten Scopes gegen
 * die für den Betriebsmodus nötigen Rechte, BEVOR das Plugin aktiviert wird.
 * Fehlende Scopes ⇒ Blocked-State mit Anleitung — nie teilweise schreibend
 * aktivieren. Die JTL-Scope-Liste kennt für Bestände zwei Namensfamilien
 * (`inventory.*`/`inventories.*`) — jede der beiden genügt; `all.read`
 * deckt alle Lese-Scopes ab.
 */
class JtlScopePreflight {
    /** @var array<string, list<string>> Lese-Anforderungen: ein Scope je Gruppe genügt. */
    private const READ_REQUIREMENTS = [
        'items.read' => ['items.read', 'all.read'],
        'warehouse.read' => ['warehouse.read', 'all.read'],
        'inventory.read' => ['inventory.read', 'inventories.read', 'all.read'],
    ];

    /** @var array<string, list<string>> Schreib-Anforderungen (nur für inventory_mode = external). */
    private const WRITE_REQUIREMENTS = [
        'inventory.write' => ['inventory.write', 'inventories.write'],
    ];

    /**
     * @return array{ok: bool, unknown: bool, missing_read: list<string>, missing_write: list<string>}
     */
    public function check(JtlConnection $connection): array {
        $granted = array_values(array_map('strval', $connection->granted_scopes ?? []));

        if ($granted === []) {
            // Cloud-Token ohne Scope-Angabe: nicht prüfbar — der Healthcheck
            // erkennt fehlende Rechte an 403-Antworten der Probe-Aufrufe.
            return ['ok' => false, 'unknown' => true, 'missing_read' => [], 'missing_write' => []];
        }

        return [
            'ok' => $this->missing(self::READ_REQUIREMENTS, $granted) === [],
            'unknown' => false,
            'missing_read' => $this->missing(self::READ_REQUIREMENTS, $granted),
            'missing_write' => $this->missing(self::WRITE_REQUIREMENTS, $granted),
        ];
    }

    /**
     * Scopes, die WorkDiary bei der App-Registrierung als Pflicht anfordert.
     *
     * @return list<string>
     */
    public function mandatoryScopes(): array {
        return ['items.read', 'warehouse.read', 'inventory.read'];
    }

    /**
     * Optional angeforderte Scopes (Schreibpfad).
     *
     * @return list<string>
     */
    public function optionalScopes(): array {
        return ['inventory.write'];
    }

    /**
     * @param  array<string, list<string>>  $requirements
     * @param  list<string>  $granted
     * @return list<string>
     */
    private function missing(array $requirements, array $granted): array {
        $missing = [];
        foreach ($requirements as $label => $alternatives) {
            if (array_intersect($alternatives, $granted) === []) {
                $missing[] = $label;
            }
        }

        return $missing;
    }
}
