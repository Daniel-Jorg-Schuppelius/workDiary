<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistWebhookSyncJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Todoist\Jobs;

use App\Jobs\Concerns\RetriesTransientFailures;
use App\Models\{Organization, TodoistConnection, TodoistProjectLink, TodoistWebhookDelivery};
use App\Plugins\Todoist\Services\TodoistImportService;
use App\Plugins\Todoist\TodoistPlugin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Throwable;

/**
 * Gezielter Abgleich nach Webhook-Impuls (Feature 055, MVP-115): bindet die
 * Organisation explizit (Muster {@see \App\Jobs\Location\ProcessLocationBatch})
 * und gleicht nur die betroffene Projektzuordnung ab — der Webhook schreibt
 * nie direkt Felder. Fehler werden geschluckt: das stündliche Polling
 * (todoist:sync) ist die verlässliche Quelle und heilt verpasste Impulse.
 */
class TodoistWebhookSyncJob implements ShouldQueue {
    use RetriesTransientFailures;

    protected function pluginErrorId(): ?string {
        return TodoistPlugin::ID;
    }
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $organizationId,
        public readonly ?string $todoistProjectId = null,
        public readonly ?int $deliveryId = null,
    ) {}

    public function handle(TodoistImportService $imports): void {
        $org = Organization::query()->find($this->organizationId);
        if (! $org instanceof Organization) {
            return;
        }
        // Org-Kontext für nachgelagerte (scoped) Operationen binden — mit
        // Restore über OrganizationContext (Vollaudit 2026-07, M42).
        \App\Support\OrganizationContext::run($org, function () use ($imports, $org): void {
            $this->syncWithinContext($imports, $org);
        });

        if ($this->deliveryId !== null) {
            TodoistWebhookDelivery::query()->whereKey($this->deliveryId)->update(['processed_at' => now()]);
        }
    }

    private function syncWithinContext(TodoistImportService $imports, Organization $org): void {
        $connection = TodoistConnection::query()
            ->where('organization_id', $org->id)
            ->first();
        if ($connection instanceof TodoistConnection && $connection->isActive()) {
            $links = TodoistProjectLink::query()
                ->where('organization_id', $org->id)
                ->where('status', TodoistProjectLink::STATUS_ACTIVE)
                ->when($this->todoistProjectId !== null && $this->todoistProjectId !== '',
                    fn ($q) => $q->where('todoist_project_id', $this->todoistProjectId))
                ->get();

            foreach ($links as $link) {
                try {
                    $imports->syncLink($link, $connection);
                } catch (Throwable) {
                    // bewusst: Polling heilt — Webhook ist nur Impuls
                }
            }
        }

    }
}
