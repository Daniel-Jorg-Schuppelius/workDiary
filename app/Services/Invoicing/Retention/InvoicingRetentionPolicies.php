<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicingRetentionPolicies.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Retention;

use App\Enums\Invoicing\IncomingEInvoiceStatus;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\User;
use App\Services\Invoicing\EInvoice\IncomingEInvoiceService;
use App\Services\Retention\Contracts\RetentionPolicyProvider;
use App\Services\Retention\RetentionPolicy;

/**
 * Abgelehnte Rechnungseingänge (Phase 150, MVP-1213): „Keine Rechnung“ ist
 * eine Entscheidung eines Menschen; nach der Frist (Tage, `invoicing.rejected_retention_days`)
 * schlägt der Scan die Vernichtung vor — Vollzug nur nach Freigabe, über
 * denselben Weg wie das Verwerfen per Regel.
 */
final class InvoicingRetentionPolicies implements RetentionPolicyProvider {
    /** @return list<RetentionPolicy> */
    public function policies(): array {
        return [
            new RetentionPolicy(
                area: 'incoming_invoices_rejected',
                modelClass: IncomingEInvoice::class,
                overdueQuery: fn ($organization, $cutoff) => IncomingEInvoice::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('status', IncomingEInvoiceStatus::Rejected->value)
                    ->whereNull('supplier_id')->whereNull('customer_id')
                    ->whereNull('transferred_at')
                    ->whereNotNull('decided_at')
                    ->where('decided_at', '<', $cutoff),
                purge: function (IncomingEInvoice $subject, User $actor): void {
                    app(IncomingEInvoiceService::class)->discard($subject, (string) __('Aufbewahrungsfrist abgelaufen'), $actor, 'retention');
                },
            ),
        ];
    }
}
