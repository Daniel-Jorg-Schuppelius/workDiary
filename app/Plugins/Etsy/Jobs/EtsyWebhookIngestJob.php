<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyWebhookIngestJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Etsy\Jobs;

use App\Models\{EtsyWebhookDelivery, Organization};
use App\Plugins\Etsy\Services\EtsyReceiptImportService;
use App\Support\OrganizationContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Throwable;

/**
 * Verarbeitet einen Etsy-Webhook-Impuls (Feature 101, MVP-496): lädt das
 * Receipt selbst über die fixe Base-URL nach (nie die `resource_url` aus dem
 * Payload) und läuft in denselben Einzel-Ingest wie der Sweep — idempotent.
 * Fehler werden geschluckt: der Polling-Sweep (etsy:sync) ist die
 * verlässliche Quelle und heilt verpasste Impulse.
 */
class EtsyWebhookIngestJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $organizationId,
        public readonly int $receiptId,
        public readonly ?int $deliveryId = null,
    ) {}

    public function handle(EtsyReceiptImportService $import): void {
        $org = Organization::query()->find($this->organizationId);
        if (! $org instanceof Organization) {
            return;
        }

        $processed = false;

        OrganizationContext::run($org, function () use ($import, $org, &$processed): void {
            try {
                $import->importSingle($org, $this->receiptId);
                $processed = true;
            } catch (Throwable) {
                // bewusst: Polling heilt — Webhook ist nur Impuls
                $processed = false;
            }
        });

        if ($processed && $this->deliveryId !== null) {
            EtsyWebhookDelivery::query()->whereKey($this->deliveryId)->update(['processed_at' => now()]);
        }
    }
}
