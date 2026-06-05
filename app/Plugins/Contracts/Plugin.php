<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Plugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Contracts;

use App\Plugins\PluginHealth;

/**
 * Base contract for every plugin shipped with or added to workDiary.
 * A plugin is a thin glue layer between an external service (e.g. Lexoffice)
 * and the application's domain models.
 *
 * Implementations should be stateless and resolved through the container.
 *
 * Für die optionalen Lifecycle-Methoden (`migrationsPath`, `schemaVersion`,
 * `settingsView`, `healthCheck`) gibt es das {@see \App\Plugins\PluginDefaults}-
 * Trait, das vernünftige Standard-Implementierungen liefert.
 */
interface Plugin {
    /** Unique stable identifier (e.g. "lexoffice"). Used for config + DB refs. */
    public function id(): string;

    /** Human-friendly name (UI label). */
    public function name(): string;

    /** Semantic version string of the plugin implementation. */
    public function version(): string;

    /** Short description for the plugin admin overview. */
    public function description(): string;

    /** True if the plugin is configured and ready to be invoked. */
    public function isEnabled(): bool;

    /**
     * Capability identifiers this plugin advertises.
     *
     * @return array<int, string>
     */
    public function capabilities(): array;

    /**
     * Optional admin/settings panel descriptor. Return null to hide.
     *
     * @return array{route?: string, label?: string, icon?: string}|null
     */
    public function adminPanel(): ?array;

    /**
     * FQCN eines Plugin-eigenen ServiceProviders. Wenn vorhanden, lädt der
     * Core-PluginServiceProvider den Provider und gibt ihm die Möglichkeit,
     * eigene Routes/Views/Migrations/Commands zu registrieren.
     *
     * @return class-string|null
     */
    public function serviceProvider(): ?string;

    /**
     * Schema-Beschreibung der konfigurierbaren Plugin-Settings (für die UI).
     * Jedes Feld: ['key' => 'api_key', 'label' => 'API-Key', 'type' => 'password|text|select|boolean', 'options' => [...]?, 'help' => '...'?, 'required' => bool?].
     *
     * @return array<int, array{key: string, label: string, type: string, options?: array<string, string>, help?: string, required?: bool, default?: mixed}>
     */
    public function settingsSchema(): array;

    /**
     * Optionale eigene Blade-View für die Settings-Seite. Gibt das Plugin
     * eine View-Kennung zurück (z. B. "lexoffice::admin.settings"), nutzt
     * der Admin-Controller diese View statt der Auto-Form aus {@see settingsSchema()}.
     * Default: `null` → Auto-Form.
     */
    public function settingsView(): ?string;

    /**
     * Absoluter Pfad zu plugin-eigenen Migrations (z. B. `__DIR__ . '/database/migrations'`).
     * Wird vom {@see \App\Plugins\PluginSchemaManager} per `artisan migrate --path=` ausgeführt.
     * Default: `null` → Plugin liefert kein eigenes Schema.
     */
    public function migrationsPath(): ?string;

    /**
     * Semver des Plugin-Schemas. Erhöhen, wenn neue Migrations hinzukommen,
     * damit der SchemaManager `needsUpgrade()` korrekt erkennt.
     */
    public function schemaVersion(): string;

    /**
     * Health-Check für die Plugin-Hauptfunktion (z. B. API-Ping).
     * Wird stündlich vom Scheduler aufgerufen (`plugin:healthcheck`) und manuell
     * über die Admin-UI. Plugins ohne externe Abhängigkeit dürfen `PluginHealth::ok()` liefern.
     */
    public function healthCheck(): PluginHealth;

    /**
     * Ob das Plugin pro Organisation betrieben wird (eigene Konfiguration /
     * API-Keys je Org) statt global auf der Instanz. Steuert den geplanten
     * Healthcheck: bei `true` wird er je Organisation mit gebundenem Kontext
     * ausgeführt und der Zustand pro (plugin_id, organization_id) gespeichert;
     * bei `false` einmalig global (organization_id = null).
     */
    public function isPerOrganization(): bool;
}
