<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommissionDocuments.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Commission;

use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Invoicing\Invoice;
use App\Modules\ModuleRegistry;
use App\Services\Invoicing\DunningService;
use App\Services\Sales\Contracts\{CommissionDocument, CommissionDocumentSource};

/**
 * Provisionsfähige Belege aller Quellen (MVP-1119): die lokale Rechnung und
 * die {@see CommissionDocumentSource}s der Buchhaltungs-Plugins.
 */
final class CommissionDocuments {
    public function __construct(
        private readonly DunningService $payments,
        private readonly ModuleRegistry $modules,
    ) {}

    public function of(Invoice|CommissionDocument $subject): CommissionDocument {
        return $subject instanceof Invoice ? new InvoiceCommissionDocument($subject, $this->payments) : $subject;
    }

    public function find(string $type, int $organizationId, int $id): ?CommissionDocument {
        if ($type === (new Invoice)->getMorphClass()) {
            $invoice = Invoice::query()->where('organization_id', $organizationId)->find($id);

            return $invoice instanceof Invoice ? $this->of($invoice) : null;
        }
        foreach ($this->sources() as $source) {
            if ($source->documentType() === $type) {
                return $source->find($organizationId, $id);
            }
        }

        return null;
    }

    /**
     * Bezahlte Rechnungen ohne Provisionszeile — der Arbeitsvorrat der
     * manuellen Zuordnung, zuletzt bezahlte zuerst.
     *
     * @return list<CommissionDocument>
     */
    public function unassignedPaid(int $organizationId, int $limit): array {
        $documents = Invoice::query()
            ->where('organization_id', $organizationId)
            ->where('status', InvoiceStatus::Paid)
            ->whereNotIn('type', [Invoice::TYPE_CREDIT_NOTE, Invoice::TYPE_CANCELLATION, Invoice::TYPE_PROFORMA])
            ->whereDoesntHave('commissions')
            ->orderByDesc('paid_on')
            ->limit($limit)
            ->get()
            ->map(fn (Invoice $invoice): CommissionDocument => $this->of($invoice))
            ->all();
        foreach ($this->sources() as $source) {
            array_push($documents, ...$source->unassignedPaid($organizationId, $limit));
        }
        usort($documents, static fn (CommissionDocument $a, CommissionDocument $b): int => $b->earnedOn() <=> $a->earnedOn());

        return array_slice($documents, 0, $limit);
    }

    /** @return list<CommissionDocumentSource> */
    private function sources(): array {
        return array_map(static fn (string $class): CommissionDocumentSource => app($class), $this->modules->extensions(CommissionDocumentSource::class));
    }
}
