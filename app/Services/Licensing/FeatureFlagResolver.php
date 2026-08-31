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

        foreach ($this->routeNameVariants($routeName) as $candidate) {
            foreach ($map as $pattern => $module) {
                if (Str::is($pattern, $candidate)) {
                    return (string) $module;
                }
            }
        }

        return null;
    }

    /**
     * Die REST-API erbt die Modulzuordnung der gleichnamigen Web-Route
     * (Sicherheitsscan 2026-08-23, S-12).
     *
     * `config('plans.routes')` ordnet Namenspräfixe einem Modul zu — bis 2026-08-31
     * nur für die Oberfläche. Über einen Sanctum-Token blieb ein nicht gebuchtes
     * Modul damit nutzbar, obwohl die Weboberfläche es mit 423 verweigerte.
     *
     * Abgeleitet statt gespiegelt: 70 zusätzliche Einträge in der Konfiguration
     * wären dieselbe Aussage ein zweites Mal — und liefen beim nächsten neuen
     * Modul auseinander. Welcher Bereich zu welchem Modul gehört, ist eine
     * fachliche Entscheidung; dass die API demselben Zuschnitt folgt, ist keine.
     *
     * Bereiche ohne Web-Zuordnung (Tagebuch, Aufgaben, Anwesenheit, Stoppuhr …)
     * bleiben auch hier ungegated — sie sind Kern, nicht Modul.
     *
     * @return list<string>
     */
    private function routeNameVariants(string $routeName): array {
        $variants = [$routeName];

        foreach (['api.legacy.', 'api.'] as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                $variants[] = substr($routeName, strlen($prefix));
                break;
            }
        }

        return $variants;
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

        // Org-gebundene Lizenz hat Vorrang; ohne nutzbare eigene Lizenz greift
        // die installationsweite (globale) als Fallback, erst wenn BEIDE fehlen
        // produktiv hart free.
        $org = $this->currentOrg();
        $licenseResult = $org !== null ? $this->licenses->forOrganization($org) : $this->licenses->current();
        if (! $licenseResult->isUsable() && $org !== null) {
            $global = $this->licenses->current();
            if ($global->isUsable()) {
                $licenseResult = $global;
            }
        }
        $payload = $licenseResult->payload;
        $usable = $payload !== null && $licenseResult->isUsable();

        // Effektiver Plan (Tier): eine nutzbare Lizenz trägt das Tier. Ohne
        // nutzbare Lizenz produktiv HART free; in local/testing dient
        // organizations.plan als Fallback (sonst verlören Dev/Tests ihre Module).
        if ($usable && in_array($payload->plan, Organization::$plans, true)) {
            $plan = (string) $payload->plan;
        } elseif (! $usable && app()->environment('local', 'testing')) {
            $plan = $this->orgPlan();
        } else {
            $plan = Organization::PLAN_FREE;
        }

        // Tier-Basis-Module aus dem Katalog.
        foreach ((array) config("plans.tiers.{$plan}", []) as $code) {
            if ((string) $code !== '') {
                $map[(string) $code] = true;
            }
        }

        // Einzeln gebuchte Module (Add-ons) + technische Feature-Flags (z. B.
        // protocols.signed, reports.export) additiv ueber das Tier hinaus.
        if ($usable) {
            foreach (array_merge($payload->addons, $payload->features) as $code) {
                if ((string) $code !== '') {
                    $map[(string) $code] = true;
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

        // Modul-Abhängigkeiten (Feature 065): ein abhängiges Modul ist nur
        // wirksam, wenn seine Voraussetzung aktiv ist (z. B. service_desk
        // setzt helpdesk voraus) — greift NACH allen Overrides, damit auch
        // ein deaktiviertes Basis-Modul das abhängige mitzieht.
        foreach ((array) config('plans.requires', []) as $module => $requirement) {
            if (($map[$module] ?? false) === true && ($map[(string) $requirement] ?? false) !== true) {
                $map[$module] = false;
            }
        }

        return $this->resolved = $map;
    }

    /** Aktuelle Organisation aus dem Container (oder null im konsolenweiten Kontext). */
    private function currentOrg(): ?Organization {
        if (app()->bound('currentOrganization')) {
            $org = app('currentOrganization');
            if ($org instanceof Organization) {
                return $org;
            }
        }

        return null;
    }

    /**
     * Plan-Label der aktuellen Organisation (organizations.plan). Nur Dev-/Test-
     * Fallback, wenn keine nutzbare Lizenz vorliegt.
     */
    private function orgPlan(): string {
        $org = $this->currentOrg();
        if ($org !== null && in_array($org->plan, Organization::$plans, true)) {
            return (string) $org->plan;
        }

        return Organization::PLAN_FREE;
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
