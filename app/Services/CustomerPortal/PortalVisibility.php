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
use App\Models\{Customer, User};
use App\Services\Licensing\FeatureFlagResolver;

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

    public function __construct(private readonly FeatureFlagResolver $features) {}

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

    public function timeDetail(Customer $customer): PortalTimeDetail {
        if (! $this->allows($customer, PortalCapability::TimeEntries)) {
            return PortalTimeDetail::None;
        }

        return PortalTimeDetail::tryFrom((string) (($customer->portal_settings ?? [])['time_detail'] ?? ''))
            ?? PortalTimeDetail::None;
    }

    /** `published` (Standard) oder ausdrücklich gewählter Kompat-Scope `all`. */
    public function timeScope(Customer $customer): string {
        $scope = (string) (($customer->portal_settings ?? [])['time_scope'] ?? self::TIME_SCOPE_PUBLISHED);

        return $scope === self::TIME_SCOPE_ALL ? self::TIME_SCOPE_ALL : self::TIME_SCOPE_PUBLISHED;
    }

    /**
     * Speichert die Konfiguration und auditiert Akteur, alte und neue Freigabe.
     *
     * @param  array{enabled: bool, capabilities: array<int, string>, time_detail: string, time_scope: string}  $input
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
