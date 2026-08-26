<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevBookingFieldResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Datev;

use App\Models\{Expense, IncomingEInvoice, Invoice};
use App\Services\TimeExport\CostCenterResolver;
use CommonToolkit\Helper\Data\NumberHelper;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Kanzlei-Felder je Quellbeleg für den EXTF-Stapel (Feature 135, MVP-700) —
 * eine Ableitung für beide Exportpfade (Faktura-Stapel und Journal-Übergabe),
 * damit dieselbe Rechnung nicht zwei Fälligkeiten bekommt.
 *
 *  - KOST1: über {@see CostCenterResolver::codeForSource()} — dieselbe Regel,
 *    mit der das Journal seine Zeilen bekostet (Feature 142). Trägt die
 *    Buchungszeile bereits eine Kostenstelle, gilt die Zeile
 *    ({@see \App\Services\Accounting\LedgerDatevExportService}).
 *  - Fälligkeit: `invoices.due_on` bzw. `incoming_einvoices.due_date`.
 *  - Skonto: Skontobetrag aus den Konditionen der Rechnung, Typ 2 = Verkauf
 *    (Ausgangsrechnung), Typ 1 = Einkauf (Eingangsrechnung).
 *  - Beleglink: bleibt leer (siehe {@see DatevBookingAdapter}).
 *
 * Zustandsbehaftet je Export-Lauf (Regel-Cache) — pro Lauf neu instanziieren.
 *
 * @phpstan-type SourceFields array{cost_center1: ?string, due_on: ?DateTimeImmutable, discount_amount: ?numeric-string, discount_type: ?int}
 */
final class DatevBookingFieldResolver {
    private readonly CostCenterResolver $costCenters;

    public function __construct(int $organizationId) {
        $this->costCenters = new CostCenterResolver($organizationId);
    }

    /** @return SourceFields */
    public function forSource(?Model $source): array {
        if ($source instanceof Invoice) {
            $discount = $source->hasSkonto() ? $source->skontoAmount()->getAmount() : null;

            return [
                'cost_center1' => $this->costCenters->codeForSource($source),
                'due_on' => $this->date($source->due_on?->toDateString()),
                'discount_amount' => $discount !== null && NumberHelper::comparePrecise($discount, '0', 2) > 0 ? $discount : null,
                'discount_type' => $discount !== null ? DatevBookingAdapter::DISCOUNT_TYPE_SALES : null,
            ];
        }

        if ($source instanceof IncomingEInvoice) {
            $discount = null;
            $percent = (float) ($source->discount_percent ?? 0);
            $gross = $source->amount_gross;
            if ($percent > 0 && (int) ($source->discount_days ?? 0) > 0 && $gross !== null) {
                $discount = $gross->percentage((string) $percent)->getAmount();
            }

            return [
                'cost_center1' => $this->costCenters->codeForSource($source),
                'due_on' => $this->date($source->due_date?->toDateString()),
                'discount_amount' => $discount !== null && NumberHelper::comparePrecise($discount, '0', 2) > 0 ? $discount : null,
                'discount_type' => $discount !== null ? DatevBookingAdapter::DISCOUNT_TYPE_PURCHASE : null,
            ];
        }

        if ($source instanceof Expense) {
            return [
                'cost_center1' => $this->costCenters->codeForSource($source),
                'due_on' => null,
                'discount_amount' => null,
                'discount_type' => null,
            ];
        }

        return [
            'cost_center1' => $this->costCenters->codeForSource($source),
            'due_on' => null,
            'discount_amount' => null,
            'discount_type' => null,
        ];
    }

    private function date(?string $value): ?DateTimeImmutable {
        return $value !== null && $value !== '' ? new DateTimeImmutable($value) : null;
    }
}
