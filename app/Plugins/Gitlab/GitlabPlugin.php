<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GitlabPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Gitlab;

use App\Models\Organization;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{Plugin, PluginCapability, TaskSyncer};
use App\Plugins\Gitlab\Api\{GitlabApiException, GitlabClientFactory};
use App\Plugins\Gitlab\Services\GitlabIssueImporter;
use Throwable;

/**
 * Ticketsystem-Anbindung GitLab Issues (Feature 060, MVP-129, Bauturbo A6):
 * dritter Provider gegen den {@see TaskSyncer}-Vertrag nach dem
 * Zammad-Referenzmuster.
 *
 * - Issues EINES konfigurierten Projekts (Projekt-ID + Token je Organisation,
 *   verschlüsselt in plugin_settings — Auto-Form der Plugin-Karte) kommen als
 *   WorkDiary-Aufgaben an; GitLab bleibt führend. Self-hosted Instanzen über
 *   die konfigurierbare Instanz-URL (SSRF-Leitplanke mit ausdrücklicher
 *   Freigabe privater Adressen, Muster JTL-Wawi).
 * - Import ist **idempotent** über {@see \App\Models\ExternalReference}
 *   (Plugin `gitlab`, Typ `issue`, Schlüssel `project_id#iid` — nie die
 *   globale `id`).
 * - Polling ({@see Console\GitlabSyncCommand}, `updated_after`-Aufholpunkt)
 *   ist die verlässliche Quelle; der Webhook
 *   ({@see Http\Controllers\GitlabWebhookController}, statischer
 *   `X-Gitlab-Token`) stößt nur an — GitLab deaktiviert Hooks nach
 *   wiederholten Fehlern selbst, das Polling schließt jede Lücke.
 */
class GitlabPlugin extends AbstractPlugin implements TaskSyncer {
    public const ID = 'gitlab';

    public const SERVICE_PROVIDER = GitlabServiceProvider::class;

    /** ExternalReference-Typ dieses Plugins. */
    public const EXT_TYPE_ISSUE = 'issue';

    public function name(): string {
        return 'GitLab Issues';
    }

    public function version(): string {
        return '1.0.0';
    }

    public function description(): string {
        return __('Importiert GitLab-Issues eines Projekts als Aufgaben (idempotent über iid + Projekt-ID, self-hosted Instanzen unterstützt): Zeiterfassung und Abrechnung in WorkDiary, GitLab bleibt führend. Polling mit updated_after-Aufholpunkt, Webhook-Anstoß optional.');
    }

    public function isEnabled(): bool {
        return GitlabConfig::resolve()['enabled'];
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

        $config = GitlabConfig::resolve((int) $organization->id);
        if (! $config['enabled'] || ! GitlabConfig::isConfigured((int) $organization->id)) {
            return $counters;
        }

        try {
            $client = app(GitlabClientFactory::class)->for((int) $organization->id);
            $result = app(GitlabIssueImporter::class)->import($organization, $client, $config);
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
            ['key' => 'base_url', 'label' => __('Instanz-URL'), 'type' => 'text', 'default' => 'https://gitlab.com', 'help' => __('GitLab-Instanz, z. B. https://gitlab.com oder die eigene On-Premise-Adresse.')],
            ['key' => 'project_id', 'label' => __('Projekt-ID'), 'type' => 'text', 'required' => true, 'help' => __('Numerische GitLab-Projekt-ID (Projektseite → Einstellungen → Allgemein).')],
            ['key' => 'api_token', 'label' => __('API-Token'), 'type' => 'password', 'required' => true, 'help' => __('Project Access Token (empfohlen) oder Personal Access Token mit read_api-Scope.')],
            ['key' => 'webhook_token', 'label' => __('Webhook-Token'), 'type' => 'password', 'help' => __('Optional: Secret Token des GitLab-Webhooks (Issue-Events, X-Gitlab-Token). Ohne Token bleibt der Webhook-Endpunkt deaktiviert; das Polling holt alles nach.')],
            ['key' => 'allow_private_network', 'label' => __('Private Adressen erlauben'), 'type' => 'boolean', 'default' => false, 'help' => __('Nur für On-Premise-Instanzen im eigenen Netz: erlaubt eine Instanz-URL mit privater/interner Adresse.')],
            ['key' => 'default_project', 'label' => __('Standard-Projekt (Sqid)'), 'type' => 'text', 'help' => __('Optional: Projekt-Kennung aus der Projekt-URL; importierte Aufgaben landen dort, sonst als globale Aufgabe.')],
            ['key' => 'writeback', 'label' => __('Erledigung zurückschreiben'), 'type' => 'boolean', 'default' => false, 'help' => __('Schließt das verknüpfte Issue (mit Notiz), wenn die Aufgabe in workDiary erledigt wird, und öffnet es beim Wiedereröffnen. Titel und Beschreibung bleiben quellsystem-geführt. Der Token braucht Schreibzugriff auf Issues (api-Scope).')],
        ];
    }

    /**
     * Health-Check je Organisation: billige Probe `GET /api/v4/user` mit dem
     * hinterlegten Token (Token gültig + API erreichbar).
     */
    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }

        if (! GitlabConfig::isConfigured((int) $organization->id)) {
            return PluginHealth::degraded(__('GitLab ist nicht konfiguriert (Projekt-ID oder API-Token fehlt).'), 'not_configured');
        }

        try {
            $user = app(GitlabClientFactory::class)->for((int) $organization->id)->user();

            return PluginHealth::ok(__('Verbunden mit GitLab als :login.', ['login' => (string) ($user['username'] ?? '?')]));
        } catch (GitlabApiException $e) {
            if ($e->isAuthError()) {
                return PluginHealth::failing(__('GitLab lehnt den API-Token ab (:status) — Token prüfen.', ['status' => $e->status]), 'auth');
            }

            return PluginHealth::degraded(__('GitLab-API antwortet mit Fehlerstatus :status.', ['status' => $e->status]), 'api_error');
        } catch (Throwable) {
            return PluginHealth::failing(__('GitLab-API nicht erreichbar.'), 'unreachable');
        }
    }
}
