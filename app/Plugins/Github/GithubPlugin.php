<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GithubPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Github;

use App\Models\Organization;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{Plugin, PluginCapability, TaskSyncer};
use App\Plugins\Github\Api\{GithubApiException, GithubClientFactory};
use App\Plugins\Github\Services\GithubIssueImporter;
use Throwable;

/**
 * Ticketsystem-Anbindung GitHub Issues (Feature 060, MVP-129, Bauturbo A6):
 * zweiter Provider gegen den {@see TaskSyncer}-Vertrag nach dem
 * Zammad-Referenzmuster.
 *
 * - Issues EINES konfigurierten Repos (owner/repo je Organisation, Fine-grained
 *   PAT verschlüsselt in plugin_settings — Auto-Form der Plugin-Karte, kein
 *   eigener Verbindungsfluss nötig) kommen als WorkDiary-Aufgaben an; GitHub
 *   bleibt führend. Pull Requests werden gefiltert (`pull_request`-Schlüssel).
 * - Import ist **idempotent** über {@see \App\Models\ExternalReference}
 *   (Plugin `github`, Typ `issue`, Schlüssel `owner/repo#number`).
 * - Polling ({@see Console\GithubSyncCommand}, `since`-Aufholpunkt) ist die
 *   verlässliche Quelle; der Webhook
 *   ({@see Http\Controllers\GithubWebhookController}, HMAC-SHA256) stößt nur
 *   an — GitHub liefert Webhooks nicht automatisch nach (kein Auto-Redelivery),
 *   das Polling schließt die Lücke.
 */
class GithubPlugin extends AbstractPlugin implements TaskSyncer {
    public const ID = 'github';

    public const SERVICE_PROVIDER = GithubServiceProvider::class;

    /** ExternalReference-Typ dieses Plugins. */
    public const EXT_TYPE_ISSUE = 'issue';

    public function name(): string {
        return 'GitHub Issues';
    }

    public function version(): string {
        return '1.0.0';
    }

    public function description(): string {
        return __('Importiert GitHub-Issues eines Repositories als Aufgaben (idempotent je Issue, Pull Requests werden gefiltert): Zeiterfassung und Abrechnung in WorkDiary, GitHub bleibt führend. Polling mit since-Aufholpunkt, Webhook-Anstoß optional.');
    }

    public function isEnabled(): bool {
        return GithubConfig::resolve()['enabled'];
    }

    public function capabilities(): array {
        return [
            PluginCapability::TaskSync,
        ];
    }

    /**
     * Einheitlicher Sync-Einstieg (TaskSyncer): Issue-Import der Organisation.
     * Einbahnig — `created`/`updated` aus dem Import, `unchanged` =
     * übersprungene (unveränderte) Issues; `conflicts` bleibt 0.
     */
    public function syncTasks(Organization $organization): array {
        $counters = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'conflicts' => 0, 'inbox' => 0, 'failed' => 0];

        $config = GithubConfig::resolve((int) $organization->id);
        if (! $config['enabled'] || ! GithubConfig::isConfigured((int) $organization->id)) {
            return $counters;
        }

        try {
            $client = app(GithubClientFactory::class)->for((int) $organization->id);
            $result = app(GithubIssueImporter::class)->import($organization, $client, $config);
            $counters['created'] += $result['created'];
            $counters['updated'] += $result['updated'];
            $counters['unchanged'] += $result['skipped'];
            $counters['inbox'] += $result['inbox'];
        } catch (Throwable) {
            $counters['failed']++;
        }

        return $counters;
    }

    /** @return array<int, array{key: string, label: string, type: string, options?: array<string, string>, help?: string, required?: bool, default?: mixed}> */
    public function settingsSchema(): array {
        return [
            ['key' => 'repo_owner', 'label' => __('Repository-Besitzer (Owner)'), 'type' => 'text', 'required' => true, 'help' => __('GitHub-Benutzer oder -Organisation, z. B. „acme".')],
            ['key' => 'repo_name', 'label' => __('Repository-Name'), 'type' => 'text', 'required' => true, 'help' => __('Nur die Issues dieses Repositories werden importiert.')],
            ['key' => 'api_token', 'label' => __('API-Token'), 'type' => 'password', 'required' => true, 'help' => __('Fine-grained Personal Access Token mit Lesezugriff auf die Issues des Repositories.')],
            ['key' => 'webhook_secret', 'label' => __('Webhook-Secret'), 'type' => 'password', 'help' => __('Optional: Shared-Secret des GitHub-Webhooks (issues-Events, X-Hub-Signature-256). Ohne Secret bleibt der Webhook-Endpunkt deaktiviert; das Polling holt alles nach.')],
            ['key' => 'default_project', 'label' => __('Standard-Projekt (Sqid)'), 'type' => 'text', 'help' => __('Optional: Projekt-Kennung aus der Projekt-URL; importierte Aufgaben landen dort, sonst als globale Aufgabe.')],
            ['key' => 'writeback', 'label' => __('Erledigung zurückschreiben'), 'type' => 'boolean', 'default' => false, 'help' => __('Schließt das verknüpfte Issue (mit Notiz), wenn die Aufgabe in workDiary erledigt wird, und öffnet es beim Wiedereröffnen. Titel und Beschreibung bleiben quellsystem-geführt. Der Token braucht Schreibzugriff auf Issues.')],
        ];
    }

    /**
     * Health-Check je Organisation: billige Probe `GET /user` mit dem
     * hinterlegten Token (Token gültig + API erreichbar).
     */
    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }

        if (! GithubConfig::isConfigured((int) $organization->id)) {
            return PluginHealth::degraded(__('GitHub ist nicht konfiguriert (Repository oder API-Token fehlt).'), 'not_configured');
        }

        try {
            $user = app(GithubClientFactory::class)->for((int) $organization->id)->user();

            return PluginHealth::ok(__('Verbunden mit GitHub als :login.', ['login' => (string) ($user['login'] ?? '?')]));
        } catch (GithubApiException $e) {
            if ($e->isAuthError()) {
                return PluginHealth::failing(__('GitHub lehnt den API-Token ab (:status) — Fine-grained PAT prüfen.', ['status' => $e->status]), 'auth');
            }

            return PluginHealth::degraded(__('GitHub-API antwortet mit Fehlerstatus :status.', ['status' => $e->status]), 'api_error');
        } catch (Throwable) {
            return PluginHealth::failing(__('GitHub-API nicht erreichbar.'), 'unreachable');
        }
    }
}
