<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyIngestJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Calendly\Jobs;

use App\Jobs\Concerns\RetriesTransientFailures;
use App\Models\{CalendlyWebhookDelivery, Organization};
use App\Plugins\Calendly\CalendlyPlugin;
use App\Plugins\Calendly\Services\CalendlyIngestService;
use App\Support\OrganizationContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Throwable;

/**
 * Verarbeitet einen Calendly-Webhook-Payload nach dem Impuls (Feature 095):
 * bindet die Organisation explizit (Restore über {@see OrganizationContext})
 * und steuert den Zustandsautomaten der Terminwünsche. Fehler werden
 * geschluckt — der Polling-Backfill (calendly:backfill) ist die verlässliche
 * Quelle und heilt verpasste Impulse.
 */
class CalendlyIngestJob implements ShouldQueue {
    use RetriesTransientFailures;

    protected function pluginErrorId(): ?string {
        return CalendlyPlugin::ID;
    }
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $organizationId,
        public readonly string $rawPayload,
        public readonly ?int $deliveryId = null,
    ) {}

    public function handle(CalendlyIngestService $ingest): void {
        $org = Organization::query()->find($this->organizationId);
        if (! $org instanceof Organization) {
            return;
        }

        /** @var array<string, mixed> $payload */
        $payload = (array) json_decode($this->rawPayload, true);

        $processed = false;

        OrganizationContext::run($org, function () use ($ingest, $org, $payload, &$processed): void {
            try {
                $ingest->handlePayload($org, $payload);
                $processed = true;
            } catch (Throwable) {
                // bewusst: Polling heilt — Webhook ist nur Impuls
                $processed = false;
            }
        });

        if ($processed && $this->deliveryId !== null) {
            CalendlyWebhookDelivery::query()->whereKey($this->deliveryId)->update(['processed_at' => now()]);
        }
    }
}
