<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebhookImportJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\TimeTracking;

use App\Jobs\Concerns\RetriesTransientFailures;
use App\Models\{Organization, TimeTrackingWebhookDelivery};
use App\Plugins\Contracts\TimeImporter;
use App\Plugins\PluginManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

/**
 * Vom Webhook angestoßener Zeit-Import (Feature 124, MVP-613).
 *
 * Ein Job für Toggl und Clockify: Beide Plugins tragen denselben
 * {@see TimeImporter}-Vertrag, und der Webhook macht nichts anderes, als
 * diesen Einstieg früher aufzurufen. Der Webhook WECKT nur — die Wahrheit
 * holt derselbe Lauf, der auch im Scheduler steht. Ein verlorener oder
 * doppelter Aufruf bleibt damit folgenlos.
 */
class WebhookImportJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use RetriesTransientFailures;
    use SerializesModels;

    public function __construct(
        private readonly string $pluginId,
        private readonly int $organizationId,
        private readonly ?int $deliveryId = null,
    ) {}

    public function handle(PluginManager $plugins): void {
        $organization = Organization::query()->withoutGlobalScopes()->find($this->organizationId);
        $plugin = $plugins->get($this->pluginId);

        if ($organization instanceof Organization && $plugin instanceof TimeImporter) {
            $plugin->importTimeEntries($organization);
        }

        if ($this->deliveryId !== null) {
            TimeTrackingWebhookDelivery::query()
                ->whereKey($this->deliveryId)
                ->update(['processed_at' => now()]);
        }
    }
}
