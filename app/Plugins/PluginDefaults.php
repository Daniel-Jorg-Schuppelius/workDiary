<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginDefaults.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins;

/**
 * Default-Implementierungen für die optionalen Plugin-Lifecycle-Methoden.
 * Plugins können das Trait einbinden, um nur das zu überschreiben, was sie
 * tatsächlich brauchen (z. B. eigene Migrations oder einen HealthCheck).
 */
trait PluginDefaults {
    /** Plugins ohne eigenes Schema geben `null` zurück. */
    public function migrationsPath(): ?string {
        return null;
    }

    /** Semver-String für das eigene Plugin-Schema. */
    public function schemaVersion(): string {
        return '0.0.0';
    }

    /** Optional: Blade-View für die Settings-Seite (sonst Auto-Form). */
    public function settingsView(): ?string {
        return null;
    }

    /**
     * Healthcheck-Default: Plugins ohne externen Endpunkt sind „ok".
     * Plugins mit Remote-Anbindung sollten diese Methode überschreiben.
     */
    public function healthCheck(): PluginHealth {
        return PluginHealth::ok();
    }

    /**
     * Default: global (läuft einmalig auf der Instanz). Plugins mit
     * Konfiguration/API-Keys pro Organisation überschreiben dies mit `true`,
     * damit Healthcheck + Zustand je Organisation geführt werden.
     */
    public function isPerOrganization(): bool {
        return false;
    }

    /** @param array<string, mixed> $settings @return array<string, string> */
    public function validateSettings(array $settings): array {
        return [];
    }

    /** @param array<string, mixed> $settings */
    public function onSettingsSaved(int $organizationId, array $settings): void {}

    public function onActivate(int $organizationId): void {}

    public function onDeactivate(int $organizationId): void {}

    public function onInstall(): void {}

    public function onUninstall(): void {}
}
