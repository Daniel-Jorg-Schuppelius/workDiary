<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FeatureFlagResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Licensing;

use App\Models\{LicenseFlagOverride, Organization};
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Löst Feature-Flags auf (MVP-047 §4 / Folge zu MVP-047).
 *
 * Auflösungsreihenfolge (vereinfacht für diese Iteration):
 *  1. Env-Override `config('license.feature_overrides')` (assoc code → bool)
 *  2. Lizenz-Payload (`LicensePayload->features` als list<string>; enthalten = on)
 *  3. DB-Override `license_flag_overrides` (Option A, MVP-047):
 *     kann lizenzierte Flags lokal NUR deaktivieren — niemals zusätzlich
 *     freischalten. Pro Organisation (`currentOrganization` aus dem
 *     Container) plus plattformweite Overrides (`organization_id = NULL`).
 *  4. Default: off
 *
 * Request-Level-Caching reicht für den MVP — ein 60-s-Cache
 * (`Cache::remember`) lohnt sich erst, sobald die DB-Override-Quelle
 * massiv wächst.
 */
class FeatureFlagResolver {
    /** @var array<string, bool>|null */
    private ?array $resolved = null;

    public function __construct(private readonly LicenseService $licenses) {}

    public function isEnabled(string $code): bool {
        $map = $this->resolve();

        return $map[$code] ?? false;
    }

    /** @return array<string, bool> */
    public function all(): array {
        return $this->resolve();
    }

    /**
     * Modul-Code, dem eine Route zugeordnet ist (config plans.routes), oder null,
     * wenn die Route nicht gegatet ist (Core). Erste passende Regel gewinnt.
     */
    public function moduleForRoute(?string $routeName): ?string {
        if ($routeName === null) {
            return null;
        }
        /** @var array<string, string> $map */
        $map = (array) config('plans.routes', []);
        foreach ($map as $pattern => $module) {
            if (Str::is($pattern, $routeName)) {
                return (string) $module;
            }
        }

        return null;
    }

    /** Ist die Route fuer den aktuellen Plan/Lizenz erreichbar? (Core-Routen immer.) */
    public function routeEnabled(?string $routeName): bool {
        $module = $this->moduleForRoute($routeName);

        return $module === null || $this->isEnabled($module);
    }

    public function flush(): void {
        $this->resolved = null;
    }

    /** @return array<string, bool> */
    private function resolve(): array {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $map = [];

        $licenseResult = $this->licenses->current();
        $payload = $licenseResult->payload;
        if ($payload !== null && $licenseResult->isUsable()) {
            // Produktiv: die signierte Lizenz ist massgeblich.
            foreach ($payload->features as $code) {
                $code = (string) $code;
                if ($code !== '') {
                    $map[$code] = true;
                }
            }
        } else {
            // Dev / ohne nutzbare Lizenz: das DB-Feld organizations.plan steuert
            // ueber den Katalog (config/plans.php). organization->plan ist sonst
            // nur ein Label – hier dient es als Fallback.
            foreach ($this->planBaseline() as $code) {
                if ($code !== '') {
                    $map[$code] = true;
                }
            }
        }

        /** @var array<string, bool> $envOverrides */
        $envOverrides = (array) config('license.feature_overrides', []);
        foreach ($envOverrides as $code => $enabled) {
            if ($code === '') {
                continue;
            }
            $map[$code] = (bool) $enabled;
        }

        // MVP-047 Option A: DB-Overrides können nur DEAKTIVIEREN.
        // Lizenzierte Features lokal abschalten, niemals fremde Features
        // freischalten.
        foreach ($this->disabledFlags() as $flag) {
            if (($map[$flag] ?? false) === true) {
                $map[$flag] = false;
            }
        }

        return $this->resolved = $map;
    }

    /**
     * Modul-Codes der aktuellen Organisation gemaess ihrem Plan (config/plans.php).
     * Nur Dev-/Fallback-Ebene, wenn keine nutzbare Lizenz vorliegt.
     *
     * @return list<string>
     */
    private function planBaseline(): array {
        $plan = Organization::PLAN_FREE;
        if (app()->bound('currentOrganization')) {
            $org = app('currentOrganization');
            if ($org instanceof Organization && in_array($org->plan, Organization::$plans, true)) {
                $plan = (string) $org->plan;
            }
        }

        /** @var list<string> $codes */
        $codes = (array) config("plans.tiers.{$plan}", []);

        return $codes;
    }

    /**
     * Liest alle aktuell wirksamen Disable-Overrides:
     *  - plattformweite Einträge (`organization_id IS NULL`)
     *  - Einträge der aktuellen Organisation (aus dem Container)
     *
     * Robust auch dann, wenn die Tabelle (z. B. in Konsolen-Setups vor
     * Migration) noch nicht existiert.
     *
     * @return array<int, string>
     */
    private function disabledFlags(): array {
        if (! Schema::hasTable('license_flag_overrides')) {
            return [];
        }

        $orgId = null;
        if (app()->bound('currentOrganization')) {
            $org = app('currentOrganization');
            if ($org instanceof Organization) {
                $orgId = $org->id;
            }
        }

        return LicenseFlagOverride::query()
            ->where(function ($q) use ($orgId): void {
                $q->whereNull('organization_id');
                if ($orgId !== null) {
                    $q->orWhere('organization_id', $orgId);
                }
            })
            ->pluck('flag')
            ->all();
    }
}
