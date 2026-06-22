<?php
/*
 * Created on   : Mon Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleCatalog.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Licensing;

/**
 * Lese-Zugriff auf den Modulkatalog aus `config/plans.php` (MVP-052 §3:
 * config bleibt die technische Single Source of Truth für Modulcodes,
 * Labels, Beschreibungen und Routenzuordnung).
 *
 * Als „konfigurierbarer" Katalog gelten genau die `module.*`-Codes mit
 * Label. Technische Feature-Flags (z. B. `protocols.signed`) sind keine
 * Module und bleiben Permissions/Flags.
 */
class ModuleCatalog {
    /** @return list<string> Alle konfigurierbaren Modulcodes (module.*). */
    public function codes(): array {
        $codes = [];
        foreach (array_keys((array) config('plans.labels', [])) as $code) {
            $code = (string) $code;
            if (str_starts_with($code, 'module.')) {
                $codes[] = $code;
            }
        }
        sort($codes);

        return $codes;
    }

    public function has(string $code): bool {
        return in_array($code, $this->codes(), true);
    }

    public function label(string $code): string {
        return (string) (config('plans.labels')[$code] ?? $code);
    }

    public function description(string $code): string {
        return (string) (config('plans.descriptions')[$code] ?? '');
    }

    /**
     * Modulcode, dem ein Route-Namen-Muster zugeordnet ist (config plans.routes).
     *
     * @return array<string, string>
     */
    public function routeMap(): array {
        /** @var array<string, string> $map */
        $map = (array) config('plans.routes', []);

        return $map;
    }
}
