<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeTrackingWebhookGate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\TimeTracking;

use App\Models\{Organization, PluginSetting};
use Illuminate\Support\Facades\Cache;

/**
 * Gemeinsame Teile der Zeiterfassungs-Webhooks (Feature 124, MVP-613).
 *
 * Zwei Dinge brauchen Toggl und Clockify gleichermaßen, und beide sind zu
 * heikel, um sie zweimal zu schreiben:
 *
 * 1. **Mandanten-Auflösung über den Workspace.** Die Plugin-Einstellungen
 *    liegen verschlüsselt (`settings` ist `encrypted:array`), also lässt sich
 *    der Workspace nicht per SQL suchen — die Zeilen werden gelesen und
 *    verglichen. Das passiert erst NACH der Signaturprüfung.
 * 2. **Entprellung.** Ein Webhook feuert pro Zeiteintrag; ein Import-Lauf je
 *    Aufruf würde genau die Quote sprengen, die der Webhook entlasten soll.
 */
class TimeTrackingWebhookGate {
    /** Mindestabstand zwischen zwei angestoßenen Läufen je Organisation. */
    public const DEBOUNCE_SECONDS = 120;

    /**
     * Organisation zu einem Workspace des Anbieters. Der Vergleich läuft über
     * die entschlüsselten Einstellungen — es gibt keinen Index darauf.
     */
    public function organizationFor(string $pluginId, string $workspaceId): ?Organization {
        $workspaceId = trim($workspaceId);
        if ($workspaceId === '') {
            return null;
        }

        $rows = PluginSetting::query()
            ->withoutGlobalScopes()
            ->where('plugin_id', $pluginId)
            ->where('enabled', true)
            ->get();

        foreach ($rows as $row) {
            $settings = is_array($row->settings) ? $row->settings : [];
            if (trim((string) ($settings['workspace_id'] ?? '')) === $workspaceId) {
                return Organization::query()->withoutGlobalScopes()->find($row->organization_id);
            }
        }

        return null;
    }

    /** Geteiltes Signaturgeheimnis der Anbindung (bei der Registrierung erhalten). */
    public function secretFor(string $pluginId, int $organizationId): ?string {
        $row = PluginSetting::query()
            ->withoutGlobalScopes()
            ->where('plugin_id', $pluginId)
            ->where('organization_id', $organizationId)
            ->first();

        $secret = is_array($row?->settings) ? trim((string) ($row->settings['webhook_secret'] ?? '')) : '';

        return $secret !== '' ? $secret : null;
    }

    /**
     * Darf jetzt ein Lauf starten? Beim ersten Aufruf ja, danach erst nach
     * der Entprellzeit wieder.
     */
    public function shouldRun(string $pluginId, int $organizationId): bool {
        return Cache::add($this->key($pluginId, $organizationId), true, self::DEBOUNCE_SECONDS);
    }

    private function key(string $pluginId, int $organizationId): string {
        return 'time-tracking:webhook-debounce:' . $pluginId . ':' . $organizationId;
    }
}
