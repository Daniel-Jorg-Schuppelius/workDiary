<?php
/*
 * Created on   : Mon Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleStatusResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Licensing;

use App\Enums\Licensing\ModuleStatus;
use App\Models\{LicenseFlagOverride, Organization};
use Illuminate\Support\Facades\Schema;

/**
 * Zentrale, org-explizite Auflösung des fachlichen Modulstatus (MVP-052 §2/§3).
 *
 * Reihenfolge der Berechtigung:
 *  1. Plan + signierte (org-/global-) Lizenz bestimmen die lizenzierten Module.
 *  2. Gebuchte Add-ons erweitern die Lizenzmenge.
 *  3. Organisationsbezogene Disable-Overrides schränken nur ein.
 *  4. Eine System-/Env-Sperre (`license.feature_overrides[code] === false`)
 *     kann ein lizenziertes Modul zusätzlich blockieren.
 *
 * Bewusst NICHT vom request-gecachten {@see FeatureFlagResolver} abhängig:
 * der Status muss für eine BELIEBIGE Organisation bestimmbar sein (Admin-UI
 * über zwei Mandanten, Hintergrundjobs, Architekturtests).
 */
class ModuleStatusResolver {
    public function __construct(
        private readonly LicenseService $licenses,
        private readonly ModuleCatalog $catalog,
    ) {}

    /**
     * Vollständige Statusliste aller Katalogmodule für eine Organisation.
     *
     * @return list<array{code:string,label:string,description:string,status:ModuleStatus,source:?string,reason:?string,licensed:bool,available:bool}>
     */
    public function forOrganization(Organization $organization): array {
        $licensed = $this->licensedSources($organization);
        $disabled = $this->customerDisabledFlags($organization);
        $envOff = $this->systemBlockedFlags();

        $rows = [];
        foreach ($this->catalog->codes() as $code) {
            $source = $licensed[$code] ?? null;
            [$status, $reason] = $this->classify($code, $source !== null, in_array($code, $disabled, true), in_array($code, $envOff, true));

            $rows[] = [
                'code' => $code,
                'label' => $this->catalog->label($code),
                'description' => $this->catalog->description($code),
                'status' => $status,
                'source' => $source,
                'reason' => $reason,
                'licensed' => $status->isLicensed(),
                'available' => $status->isAvailable(),
            ];
        }

        return $rows;
    }

    /** Status eines einzelnen Moduls für eine Organisation. */
    public function statusFor(Organization $organization, string $code): ModuleStatus {
        $licensed = $this->licensedSources($organization);
        $disabled = $this->customerDisabledFlags($organization);
        $envOff = $this->systemBlockedFlags();

        [$status] = $this->classify(
            $code,
            isset($licensed[$code]),
            in_array($code, $disabled, true),
            in_array($code, $envOff, true),
        );

        return $status;
    }

    /** Ist das Modul für die Organisation effektiv verfügbar (z. B. für Jobs)? */
    public function isActiveFor(Organization $organization, string $code): bool {
        return $this->statusFor($organization, $code)->isAvailable();
    }

    /**
     * @return array{0: ModuleStatus, 1: ?string}
     */
    private function classify(string $code, bool $licensed, bool $customerDisabled, bool $systemBlocked): array {
        if (! $this->catalog->has($code) || ! $licensed) {
            return [ModuleStatus::NotLicensed, null];
        }
        if ($systemBlocked) {
            return [ModuleStatus::Blocked, __('Durch Systemkonfiguration gesperrt.')];
        }
        if ($customerDisabled) {
            return [ModuleStatus::InactiveByCustomer, null];
        }

        return [ModuleStatus::Active, null];
    }

    /**
     * Lizenzierte Module → Quelle (`plan` | `addon`). Repliziert die
     * Lizenzauflösung des {@see FeatureFlagResolver} org-explizit.
     *
     * @return array<string, string>
     */
    private function licensedSources(Organization $organization): array {
        $result = $this->licenses->forOrganization($organization);
        if (! $result->isUsable()) {
            $global = $this->licenses->current();
            if ($global->isUsable()) {
                $result = $global;
            }
        }
        $payload = $result->payload;
        $usable = $payload !== null && $result->isUsable();

        if ($usable && in_array($payload->plan, Organization::$plans, true)) {
            $plan = (string) $payload->plan;
        } elseif (! $usable && app()->environment('local', 'testing') && in_array($organization->plan, Organization::$plans, true)) {
            $plan = (string) $organization->plan;
        } else {
            $plan = Organization::PLAN_FREE;
        }

        $catalog = $this->catalog->codes();
        $sources = [];

        foreach ((array) config("plans.tiers.{$plan}", []) as $code) {
            $code = (string) $code;
            if ($code !== '' && in_array($code, $catalog, true)) {
                $sources[$code] = 'plan';
            }
        }

        if ($usable) {
            foreach ($payload->addons as $code) {
                $code = (string) $code;
                if ($code !== '' && in_array($code, $catalog, true)) {
                    $sources[$code] = 'addon';
                }
            }
            // Technische Features in der Lizenz, die zufällig Modulcodes sind.
            foreach ($payload->features as $code) {
                $code = (string) $code;
                if ($code !== '' && in_array($code, $catalog, true) && ! isset($sources[$code])) {
                    $sources[$code] = 'addon';
                }
            }
        }

        return $sources;
    }

    /**
     * Org-bezogene und plattformweite Disable-Overrides (Kundendeaktivierung).
     *
     * @return list<string>
     */
    private function customerDisabledFlags(Organization $organization): array {
        if (! Schema::hasTable('license_flag_overrides')) {
            return [];
        }

        return LicenseFlagOverride::query()
            ->where(function ($q) use ($organization): void {
                $q->whereNull('organization_id')
                    ->orWhere('organization_id', $organization->id);
            })
            ->pluck('flag')
            ->map(static fn($v): string => (string) $v)
            ->all();
    }

    /**
     * Module, die per Systemkonfiguration (`license.feature_overrides`) hart
     * abgeschaltet sind.
     *
     * @return list<string>
     */
    private function systemBlockedFlags(): array {
        $out = [];
        foreach ((array) config('license.feature_overrides', []) as $code => $enabled) {
            if ((string) $code !== '' && (bool) $enabled === false) {
                $out[] = (string) $code;
            }
        }

        return $out;
    }
}
