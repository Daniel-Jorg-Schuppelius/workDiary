<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceSettlement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Enums\Finance\AllocationKind;
use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Finance\PaymentAllocation;
use App\Models\Invoicing\Invoice;
use App\Support\MorphMap;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Die eine Stelle, die den Zahlstatus einer Rechnung aus ihrer Deckung
 * fortschreibt. Kasse, Bankabgleich und Online-Zahlung rufen sie nach jedem
 * Geldeingang und nach jedem Wegfall einer Zahlung; vorher rechnete jede
 * Quelle mit eigener Summe und eigenem Soll (Sicherheitsaudit 2026-10-04,
 * li-1/pub-4; Konsolidierungs-Audit k3-1).
 *
 * Gezahlt ist die Summe aller Quellen ({@see DunningService::paidAmount()}).
 * Gedeckt ist die Rechnung ab dem fälligen Betrag (Summe abzüglich offenem
 * Einbehalt) abzüglich eines im Bankabgleich bereits anerkannten Skontos.
 * Der Bankabgleich übergibt seine beleggenaue Skonto-Untergrenze selbst.
 */
final class InvoiceSettlement {
    private const TOLERANCE = 0.005;

    public function __construct(private readonly RetentionService $retentions) {}

    /** Kann eine Zahlung den Beleg überhaupt ausgleichen? Entwurf, Storno, Gutschrift und Pro-forma nicht. */
    public function isSettleable(Invoice $invoice): bool {
        return $invoice->status->acceptsPayments()
            && ! $invoice->isCreditNote()
            && ! $invoice->isProforma()
            && $invoice->type !== Invoice::TYPE_CANCELLATION;
    }

    /**
     * @param  CarbonInterface|null  $paidOn  Tag der auslösenden Zahlung; wird beim Wechsel auf „bezahlt“ festgehalten.
     * @param  float|null  $acceptAt  Betrag, ab dem die Rechnung als gedeckt gilt; null = fälliger Betrag abzüglich anerkanntem Skonto.
     */
    public function sync(Invoice $invoice, ?CarbonInterface $paidOn = null, ?float $acceptAt = null): InvoiceSettlementResult {
        $from = $invoice->status;
        if (! $this->isSettleable($invoice)) {
            return new InvoiceSettlementResult($from, $from, false);
        }

        // DunningService hängt am Bankabgleich, der diesen Dienst ruft — erst hier auflösen.
        $paid = app(DunningService::class)->paidAmount($invoice)->toFloat();
        $threshold = $acceptAt ?? round($this->retentions->payableAmountOf($invoice) - $this->acceptedSkonto($invoice), 2);

        $to = match (true) {
            $paid + self::TOLERANCE >= $threshold => InvoiceStatus::Paid,
            $paid > self::TOLERANCE => InvoiceStatus::PartiallyPaid,
            default => InvoiceStatus::Issued,
        };

        if ($to !== $from && $from->canTransitionTo($to)) {
            $invoice->status = $to;
            $invoice->paid_on = $to === InvoiceStatus::Paid ? Carbon::instance($paidOn ?? now()) : null;
            // Mit Modellereignissen: der Statuswechsel ist die Naht für invoice.paid, Provision und Audit.
            $invoice->save();

            return new InvoiceSettlementResult($from, $to, true);
        }

        return new InvoiceSettlementResult($from, $from, true);
    }

    /** Fälliger Betrag, den die Zahlungen aller Quellen noch nicht decken (nie negativ). */
    public function shortfall(Invoice $invoice): float {
        return max(0.0, round($this->retentions->payableAmountOf($invoice) - app(DunningService::class)->paidAmount($invoice)->toFloat(), 2));
    }

    /** Im Bankabgleich anerkannter Skontoabzug (eigener Zuordnungssatz) — zählt als Deckung, nicht als Zahlung. */
    private function acceptedSkonto(Invoice $invoice): float {
        return (float) PaymentAllocation::query()
            ->where('allocatable_type', MorphMap::alias(Invoice::class))
            ->where('allocatable_id', $invoice->id)
            ->where('kind', AllocationKind::Skonto->value)
            ->sum('amount');
    }
}
