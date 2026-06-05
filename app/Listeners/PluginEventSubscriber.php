<?php
/*
 * Created on   : Fri Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginEventSubscriber.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners;

use App\Events\{PluginAutoDisabled, PluginHealthChanged, PluginRecovered};
use App\Plugins\PluginHealth;
use Illuminate\Support\Facades\Log;

/**
 * Standard-Reaktion auf Plugin-Statuswechsel: protokolliert nur die ÜBERGÄNGE
 * (kein Dauer-Spam). Erweiterbar — wer Admin-Benachrichtigungen will, kann sich
 * an dieselben Events ({@see PluginHealthChanged}, {@see PluginAutoDisabled},
 * {@see PluginRecovered}) hängen.
 */
class PluginEventSubscriber {
    public function handleHealthChanged(PluginHealthChanged $e): void {
        $context = [
            'plugin_id' => $e->pluginId,
            'organization_id' => $e->organizationId,
            'from' => $e->from,
            'to' => $e->to,
            'message' => $e->message,
        ];
        if ($e->to === PluginHealth::STATUS_FAILING) {
            Log::warning('plugin.health.changed', $context);
        } else {
            Log::info('plugin.health.changed', $context);
        }
    }

    public function handleRecovered(PluginRecovered $e): void {
        Log::info('plugin.recovered', [
            'plugin_id' => $e->pluginId,
            'organization_id' => $e->organizationId,
        ]);
    }

    public function handleAutoDisabled(PluginAutoDisabled $e): void {
        Log::warning('plugin.auto_disabled', [
            'plugin_id' => $e->pluginId,
            'organization_id' => $e->organizationId,
            'reason' => $e->reason,
            'failure_count' => $e->failureCount,
        ]);
    }

    /** @return array<class-string, string> */
    public function subscribe(): array {
        return [
            PluginHealthChanged::class => 'handleHealthChanged',
            PluginRecovered::class => 'handleRecovered',
            PluginAutoDisabled::class => 'handleAutoDisabled',
        ];
    }
}
