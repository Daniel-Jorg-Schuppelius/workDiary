<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReconcileRetainersJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Jobs;

use App\Models\Organization;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficeInvoiceService};
use App\Services\Billing\RetainerVoucherReconciler;
use App\Support\OrganizationContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\{ShouldBeUnique, ShouldQueue};
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

/**
 * Retainer-Abgleich (Feature 098) im Anschluss an einen Beleg-Sync.
 *
 * Wird von {@see SyncVouchersJob} NACH dem Fan-out eingereiht, damit der
 * Zahlstatus frisch gesyncter Belege sofort im Leistungssaldo landet — sonst
 * hinge der Abgleich bis zum nächsten stündlichen `lexoffice:sync-vouchers`.
 */
class ReconcileRetainersJob implements ShouldBeUnique, ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 60;

    public int $tries = 1;

    public function __construct(public readonly int $organizationId) {}

    public function uniqueId(): string {
        return (string) $this->organizationId;
    }

    public function handle(): void {
        $config = LexofficeConfig::resolve($this->organizationId);
        if (! is_string($config['api_key']) || $config['api_key'] === '') {
            return;
        }

        $organization = Organization::query()->find($this->organizationId);
        if ($organization === null) {
            return;
        }

        OrganizationContext::run($organization, function () use ($organization): void {
            // Singleton mit org-spezifischem Key neu auflösen — der Netto-
            // Nachschlag ruft sonst mit dem Key einer fremden Organisation an.
            app()->forgetInstance(LexofficeInvoiceService::class);
            app(RetainerVoucherReconciler::class)->reconcile($organization);
        });
    }
}
