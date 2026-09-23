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

use App\Modules\ModuleRegistry;

/**
 * Lese-Zugriff auf den Modulkatalog (MVP-052 §3, seit MVP-861 aus den
 * Manifesten unter `app/Modules/Manifests` statt aus `config/plans.php`).
 *
 * Als „konfigurierbarer" Katalog gelten genau die `module.*`-Codes mit
 * Manifest-Eigentümer. Technische Feature-Flags (z. B. `protocols.signed`)
 * sind keine Module und bleiben Permissions/Flags.
 */
class ModuleCatalog {
    public function __construct(private readonly ModuleRegistry $registry) {}

    /** @return list<string> Alle konfigurierbaren Modulcodes (module.*). */
    public function codes(): array {
        return array_keys($this->registry->labels());
    }

    public function has(string $code): bool {
        return in_array($code, $this->codes(), true);
    }

    public function label(string $code): string {
        return $this->registry->labels()[$code] ?? $code;
    }

    public function description(string $code): string {
        return $this->registry->descriptions()[$code] ?? '';
    }

    /** @return array<string, string> Lizenzcode → Label */
    public function labels(): array {
        return $this->registry->labels();
    }

    /**
     * Modulcode, dem ein Route-Namen-Muster zugeordnet ist (Manifeste).
     *
     * @return array<string, string>
     */
    public function routeMap(): array {
        return $this->registry->routeMap();
    }
}
