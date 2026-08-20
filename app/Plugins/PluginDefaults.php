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

use App\Models\Organization;
use App\Plugins\Support\PluginOrgContext;

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
     * Healthcheck-Default: bewusst „degraded" statt „ok" (Review 2026-08, A15) —
     * ein pauschales Grün ohne jede Prüfung wäre eine falsche Gesundmeldung.
     * Plugins mit Remote-Anbindung überschreiben die Methode (z. B. via
     * {@see PluginHealth::pingHealth()}); rein lokale Plugins dürfen explizit
     * `PluginHealth::ok('kein externer Endpunkt')` zurückgeben.
     */
    public function healthCheck(): PluginHealth {
        return PluginHealth::degraded(__('Kein Healthcheck implementiert — Zustand unbestimmt.'), code: 'not_implemented');
    }

    /**
     * Org-Kontext des laufenden Healthchecks oder das Ergebnis fuer den
     * kontextlosen Systemlauf (Audit 2026-08, W2.7).
     *
     * Der Guard stand in acht Plugins wortgleich am Anfang von healthCheck().
     * Bewusst NUR dieser Teil ist geteilt: Config-Pruefung, Probe und
     * Status-Mapping tragen ueberall fachlich eigene Meldungstexte
     * (Rate-Limit-Grenzen, Token-Fristen, Sonderzustaende) - ein Skelett mit
     * Text-Parametern waere schwerer zu lesen als der Bestand und haette
     * beim Vereinheitlichen die Texte veraendert.
     *
     * Aufrufmuster:
     *   $org = $this->healthOrgContext();
     *   if ($org instanceof PluginHealth) { return $org; }
     */
    protected function healthOrgContext(): Organization|PluginHealth {
        $organization = PluginOrgContext::currentOrNull();

        return $organization instanceof Organization
            ? $organization
            : PluginHealth::ok(__('Keine Organisation im Kontext.'));
    }

    /**
     * Default: keine Untergrenze. Plugins, die eine Mindest-Kernversion
     * benötigen, geben hier einen SemVer-String zurück (z. B. '1.2.0').
     */
    public function minAppVersion(): ?string {
        return null;
    }

    /**
     * Default: keine Obergrenze. Plugins, die nur bis zu einer bestimmten
     * Kernversion getestet sind, geben hier einen SemVer-String zurück.
     */
    public function maxAppVersion(): ?string {
        return null;
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
