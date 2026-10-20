<?php
/*
 * Created on   : Mon Aug 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalVisibility.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\CustomerPortal;

use App\Enums\CustomerPortal\{PortalCapability, PortalTimeDetail};
use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Services\Licensing\FeatureFlagResolver;
use App\Settings\SettingsRegistry;

/**
 * Zentrale Freigabeentscheidung des Kundenportals (MVP-511): Navigation,
 * Dashboard, Routen-Gates, Listen, Details und Downloads fragen ALLE hier.
 *
 * Default-Deny: ohne Konfiguration (portal_settings NULL) ist das Portal für
 * den Kunden aus; neue Capabilities sind für Bestandskunden aus, bis sie
 * ausdrücklich freigegeben werden. Bereichsfreigaben umgehen nie die
 * objektbezogenen Gates (documents.customer_visible, OpenIssueVisibility, …).
 */
class PortalVisibility {
    /** Zeit-Scope „nur veröffentlichte Einträge" (Standard). */
    public const TIME_SCOPE_PUBLISHED = 'published';

    /** Kompatibilitäts-Scope „alle kundenbezogenen Zeiten" (bewusste Wahl). */
    public const TIME_SCOPE_ALL = 'all';

    /** Jahresgrenze der Portalzeiten (MVP-1237): 0 = unbegrenzt, Obergrenze wie die Registry. */
    public const TIME_YEARS_UNLIMITED = 0;

    public const TIME_YEARS_MAX = 50;

    /** Registry-Schlüssel des Organisationsstandards. */
    public const TIME_YEARS_SETTING = 'customer_portal.time_entry_years';

    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly SettingsRegistry $settings,
    ) {}

    /** Globaler Schalter „Kundenportal aktiv" je Kunde. */
    public function enabled(Customer $customer): bool {
        return (bool) (($customer->portal_settings ?? [])['enabled'] ?? false);
    }

    /** Doppelte Bedingung: Kunde hat freigegeben UND das Modul ist lizenziert. */
    public function allows(Customer $customer, PortalCapability $capability): bool {
        if (! $this->enabled($customer) || ! $this->capabilityAvailable($capability)) {
            return false;
        }

        $granted = (array) (($customer->portal_settings ?? [])['capabilities'] ?? []);

        return in_array($capability->value, array_map(strval(...), $granted), true);
    }

    /** Ist die Capability in dieser Installation überhaupt lizenziert/verfügbar? */
    public function capabilityAvailable(PortalCapability $capability): bool {
        $flag = $capability->moduleFlag();

        return $flag === null || $this->features->isEnabled($flag);
    }

    /** @return array<int, PortalCapability> nur lizenzierte Capabilities (für die Konfigurations-UI) */
    public function availableCapabilities(): array {
        return array_values(array_filter(
            PortalCapability::cases(),
            fn (PortalCapability $c): bool => $this->capabilityAvailable($c),
        ));
    }

    /**
     * Eigene Objekte des Kunden, sofern „Objekte" freigegeben ist — Auswahl im
     * IT-Eingang (MVP-1077), sonst leer.
     *
     * @return \Illuminate\Support\Collection<int, Asset>
     */
    public function assetsFor(Customer $customer): \Illuminate\Support\Collection {
        if (! $this->allows($customer, PortalCapability::Assets)) {
            return collect();
        }

        return Asset::query()->withoutGlobalScopes()
            ->where('organization_id', (int) $customer->organization_id)
            ->where('customer_id', (int) $customer->id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function timeDetail(Customer $customer): PortalTimeDetail {
        if (! $this->allows($customer, PortalCapability::TimeEntries)) {
            return PortalTimeDetail::None;
        }

        return PortalTimeDetail::tryFrom((string) (($customer->portal_settings ?? [])['time_detail'] ?? ''))
            ?? PortalTimeDetail::None;
    }

    /**
     * Sichtbare Portalzeiten in Kalenderjahren einschließlich des laufenden
     * (MVP-1237): Abweichung je Kunde (`portal_settings.time_years`), sonst der
     * Organisationsstandard; 0 = unbegrenzt. Eine Sichtgrenze, keine Löschung —
     * gilt für Liste, Summen, Zähler und Auswertungen gleichermaßen.
     */
    public function timeYears(Customer $customer): int {
        $own = self::customerTimeYears($customer);

        return $own ?? $this->organizationTimeYears($customer);
    }

    /** Organisationsstandard aus der Registry (`customer_portal.time_entry_years`). */
    public function organizationTimeYears(Customer $customer): int {
        $value = $this->settings->effective(self::TIME_YEARS_SETTING, $customer->organization)->value;

        return self::clampYears(is_numeric($value) ? (int) $value : 3);
    }

    /** Abweichung des Kunden oder null = Organisationsstandard. */
    public static function customerTimeYears(Customer $customer): ?int {
        $own = ($customer->portal_settings ?? [])['time_years'] ?? null;

        return is_numeric($own) ? self::clampYears((int) $own) : null;
    }

    private static function clampYears(int $years): int {
        return max(self::TIME_YEARS_UNLIMITED, min(self::TIME_YEARS_MAX, $years));
    }

    /** `published` (Standard) oder ausdrücklich gewählter Kompat-Scope `all`. */
    public function timeScope(Customer $customer): string {
        $scope = (string) (($customer->portal_settings ?? [])['time_scope'] ?? self::TIME_SCOPE_PUBLISHED);

        return $scope === self::TIME_SCOPE_ALL ? self::TIME_SCOPE_ALL : self::TIME_SCOPE_PUBLISHED;
    }

    /**
     * Speichert die Konfiguration und auditiert Akteur, alte und neue Freigabe.
     *
     * @param  array{enabled: bool, capabilities: array<int, string>, time_detail: string, time_scope: string, time_years?: int|string|null}  $input
     */
    public function update(Customer $customer, array $input, User $actor): void {
        $available = array_map(static fn (PortalCapability $c): string => $c->value, $this->availableCapabilities());
        $capabilities = array_values(array_intersect(
            array_map(strval(...), $input['capabilities']),
            $available,
        ));

        $before = $customer->portal_settings;
        $settings = [
            'enabled' => (bool) $input['enabled'],
            'capabilities' => $capabilities,
            'time_detail' => (PortalTimeDetail::tryFrom((string) $input['time_detail']) ?? PortalTimeDetail::None)->value,
            'time_scope' => $input['time_scope'] === self::TIME_SCOPE_ALL ? self::TIME_SCOPE_ALL : self::TIME_SCOPE_PUBLISHED,
        ];
        // Jahresgrenze nur als ausdrückliche Abweichung speichern; leer = Organisationsstandard.
        if (is_numeric($input['time_years'] ?? null)) {
            $settings['time_years'] = self::clampYears((int) $input['time_years']);
        }
        // Herkunftsmarker der Einmal-Migration erhalten (Nachvollziehbarkeit).
        if (isset($before['migrated_legacy_at'])) {
            $settings['migrated_legacy_at'] = $before['migrated_legacy_at'];
        }

        $customer->forceFill(['portal_settings' => $settings])->save();

        $customer->audit('portal.visibility.updated', [
            'by' => (int) $actor->id,
            'before' => $before,
            'after' => $settings,
        ]);
    }
}
