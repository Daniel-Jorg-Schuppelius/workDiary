<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Todoist;

use App\Models\{Organization, PluginSetting, TodoistConnection};
use App\Plugins\Contracts\{Plugin, PluginCapability, TaskSyncer};
use App\Plugins\{PluginDefaults, PluginHealth};
use App\Plugins\Support\PluginOrgContext;
use App\Plugins\Todoist\Api\TodoistApiClient;
use Throwable;

/**
 * Todoist-Aufgabensynchronisation (Feature 055, MVP-111–116).
 *
 * - Genau EINE OAuth-Verbindung je Organisation ({@see TodoistConnection},
 *   Tokens verschlüsselt); Client-ID/-Secret installationsweit (ENV).
 * - Nur ausdrücklich zugeordnete Todoist-Projekte werden synchronisiert;
 *   Zuordnung/Konflikte laufen über ExternalReference + Integrations-Inbox
 *   (MVP-103) — kein paralleles Mapping- oder Konfliktsystem.
 * - Voraussetzung je Org: Plugin aktiviert UND `module.kanban` lizenziert.
 *
 * Plugin-Id ist "todoist", per Organisation aktivierbar.
 */
class TodoistPlugin implements Plugin, TaskSyncer {
    use PluginDefaults;

    public const ID = 'todoist';

    public const SERVICE_PROVIDER = TodoistServiceProvider::class;

    /** ExternalReference-Typen dieses Plugins. */
    public const EXT_TYPE_TASK = 'task';

    public const EXT_TYPE_COLLABORATOR = 'collaborator';

    public function id(): string {
        return self::ID;
    }

    public function name(): string {
        return 'Todoist';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('Synchronisiert Aufgaben mit Todoist (OAuth, API v1): explizit zugeordnete Projekte, Integrations-Inbox für Konflikte, keine Löschweitergabe.');
    }

    public function isEnabled(): bool {
        $org = PluginOrgContext::currentOrNull();
        if ($org instanceof Organization) {
            $row = PluginSetting::forOrganization($org->id, self::ID);
            if ($row->exists) {
                return $row->enabled;
            }
        }

        return (bool) config('plugins.todoist.enabled', false);
    }

    public function capabilities(): array {
        return [
            PluginCapability::TaskSync,
        ];
    }

    /** Einheitlicher Sync-Einstieg (TaskSyncer): Abgleich aller aktiven Projektzuordnungen. */
    public function syncTasks(Organization $organization): array {
        return app(Services\TodoistSyncService::class)->syncOrganization($organization);
    }

    /**
     * Deep-Link zur Todoist-Aufgabe eines verknüpften Tasks (MVP-116) —
     * nur bei gültiger, URL-sicherer Fremd-ID (DoD), sonst null.
     */
    public static function taskUrl(\App\Models\Task $task): ?string {
        $externalId = \App\Models\ExternalReference::query()
            ->forPlugin($task->organization_id, self::ID, self::EXT_TYPE_TASK)
            ->forReferenceable($task)
            ->value('external_id');

        if (! is_string($externalId) || preg_match('/^[A-Za-z0-9_-]+$/', $externalId) !== 1) {
            return null;
        }

        return 'https://app.todoist.com/app/task/' . $externalId;
    }

    public function adminPanel(): ?array {
        return [
            'route' => 'admin.todoist.index',
            'label' => __('Todoist'),
            'icon' => 'checklist',
        ];
    }

    public function serviceProvider(): ?string {
        return TodoistServiceProvider::class;
    }

    /** Keine per-Org-Secrets: Client-ID/-Secret sind installationsweit (ENV). */
    public function settingsSchema(): array {
        return [];
    }

    public function isPerOrganization(): bool {
        return true;
    }

    public function healthCheck(): PluginHealth {
        if (! TodoistConfig::isConfigured()) {
            return PluginHealth::degraded(__('Todoist ist nicht konfiguriert (TODOIST_CLIENT_ID/SECRET fehlen).'));
        }

        $org = PluginOrgContext::currentOrNull();
        if (! $org instanceof Organization) {
            return PluginHealth::ok(__('Konfiguriert (keine Organisation im Kontext).'));
        }

        $connection = TodoistConnection::query()->where('organization_id', $org->id)->first();
        if (! $connection instanceof TodoistConnection || $connection->status === TodoistConnection::STATUS_DISCONNECTED) {
            return PluginHealth::degraded(__('Keine Todoist-Verbindung hergestellt.'));
        }
        if ($connection->status === TodoistConnection::STATUS_PAUSED) {
            return PluginHealth::failing(__('Verbindung pausiert — bitte neu verbinden.'));
        }

        try {
            $user = (new TodoistApiClient($connection))->getUser();

            return PluginHealth::ok(__('Verbunden als :email.', ['email' => (string) ($user['email'] ?? $connection->todoist_user_email ?? '—')]));
        } catch (Throwable $e) {
            return PluginHealth::failing(__('Todoist-API nicht erreichbar (:class).', ['class' => class_basename($e)]));
        }
    }
}
