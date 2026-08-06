<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph;

use App\Plugins\Support\PluginSettingsResolver;

/**
 * Microsoft-365-Konfiguration (MVP-328, Bauturbo A8; Feature 102 Variante B):
 * `client_id`/`client_secret`/`tenant` kommen aus dem Plugin-Settings-Overlay
 * der Organisation (verschlüsselt in `plugin_settings`, Dialog auf der
 * Plugin-Seite) mit Fallback auf die installationsweite ENV — jede Org kann
 * eine EIGENE App-Registrierung hinterlegen, ohne Overlay gilt die
 * Instanz-App. `$organizationId`: null = Request-Org-Kontext,
 * {@see self::INSTANCE} = ausdrücklich NUR die Instanz-App (plattformweite
 * Verbraucher wie das Backupziel).
 *
 * Sicherheitsleitplanke: Endpunkte (`authorize_url`/`token_url`/`api_base`)
 * und die Scope-Sätze bleiben BEWUSST config-only — sonst könnte ein Admin
 * den Token-Fluss umlenken oder Scopes eskalieren (Muster: nicht
 * konfigurierbare Endpoint-Allowlist des DomainReselling-Plugins).
 * Der Tenant-Platzhalter in den Endpunkt-URLs wird hier aufgelöst
 * (Default 'common' = Multi-Tenant).
 */
class MsgraphConfig {
    /** Sentinel: Instanz-App aus der ENV erzwingen (kein Org-Overlay). */
    public const INSTANCE = 0;

    /** @return array{client_id: string, client_secret: string, tenant: string, api_base: string, authorize_url: string, token_url: string, scopes: string, intake_scopes: string, intake_page_size: int, backup_scopes: string, mail_scopes: string, contacts_scopes: string, tasks_scopes: string} */
    public static function resolve(?int $organizationId = null): array {
        $overlay = $organizationId === self::INSTANCE
            ? null
            : PluginSettingsResolver::for(MsgraphPlugin::ID, $organizationId);

        $string = static fn (string $key, string $default = ''): string => $overlay !== null
            ? (string) $overlay->string($key, $default, trim: true)
            : (trim((string) (config('plugins.msgraph.' . $key) ?? '')) ?: $default);

        $tenant = $string('tenant', 'common') ?: 'common';

        return [
            'client_id' => $string('client_id'),
            'client_secret' => $string('client_secret'),
            'tenant' => $tenant,
            'api_base' => rtrim((string) config('plugins.msgraph.api_base', 'https://graph.microsoft.com/v1.0'), '/'),
            'authorize_url' => str_replace('{tenant}', $tenant, (string) config('plugins.msgraph.authorize_url', 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize')),
            'token_url' => str_replace('{tenant}', $tenant, (string) config('plugins.msgraph.token_url', 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token')),
            'scopes' => (string) config('plugins.msgraph.scopes', 'offline_access Calendars.ReadWrite'),
            // Cloud-Dokumenteingang (Feature 080, MVP-354).
            'intake_scopes' => (string) config('plugins.msgraph.intake_scopes', 'offline_access Files.Read.All Sites.Read.All'),
            'intake_page_size' => (int) config('plugins.msgraph.intake_page_size', 200),
            // Cloud-Backupziel (Feature 017 Phase 32, MVP-363).
            'backup_scopes' => (string) config('plugins.msgraph.backup_scopes', 'offline_access User.Read Files.ReadWrite'),
            // Graph-Mail-Versand (Feature 102).
            'mail_scopes' => (string) config('plugins.msgraph.mail_scopes', 'offline_access User.Read Mail.Send'),
            // Kontakt-Push (Feature 102, Schnitt D).
            'contacts_scopes' => (string) config('plugins.msgraph.contacts_scopes', 'offline_access User.Read Contacts.ReadWrite'),
            // To-Do-Sync (Feature 102, Schnitt E).
            'tasks_scopes' => (string) config('plugins.msgraph.tasks_scopes', 'offline_access User.Read Tasks.ReadWrite'),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['client_id'] !== '' && $config['client_secret'] !== '';
    }
}
