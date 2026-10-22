<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevBatchReportPdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Finance\{DatevBookingBatch, DatevBookingSource};
use App\Services\DocumentDesign\DocumentDesignRenderer;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;

/**
 * Begleitbericht zum DATEV-Buchungsstapel (Feature 045, MVP-1278): Zeitraum,
 * Berater/Mandant, Buchungszahl, Summen je Erlöskonto und Steuerschlüssel,
 * Prüfsumme der Datei und Übermittlungsverlauf — das Blatt, das mit dem
 * Stapel an die Kanzlei geht.
 */
class DatevBatchReportPdfRenderer {
    public function __construct(private readonly DocumentDesignRenderer $design) {}

    public function output(DatevBookingBatch $batch): string {
        $batch->loadMissing(['sources', 'creator:id,name', 'journal']);

        return $this->design->renderPdf(
            RenderDocumentKind::Report,
            'finance.datev.report-pdf',
            [
                'batch' => $batch,
                'byAccount' => $this->sums($batch, static fn (DatevBookingSource $s): string => (string) $s->revenue_account),
                'byTaxKey' => $this->sums($batch, static fn (DatevBookingSource $s): string => (string) ($s->tax_key ?? '—')),
                'reversals' => $batch->sources->where('is_reversal', true)->count(),
                'transmission' => app(DatevBookingService::class)->transmissionStatus($batch),
            ],
            (int) $batch->organization_id,
        );
    }

    public function filename(DatevBookingBatch $batch): string {
        return 'datev-begleitbericht-' . $batch->batch_no . '.pdf';
    }

    /**
     * Soll und Haben je Schlüssel, in Cent genau über Money.
     *
     * @param  callable(DatevBookingSource): string  $key
     * @return array<string, array{count: int, soll: Money, haben: Money}>
     */
    private function sums(DatevBookingBatch $batch, callable $key): array {
        $rows = [];
        foreach ($batch->sources as $source) {
            $group = $key($source);
            $rows[$group] ??= ['count' => 0, 'soll' => Money::zero(CurrencyCode::Euro, 2), 'haben' => Money::zero(CurrencyCode::Euro, 2)];
            $amount = Money::of((string) $source->amount, CurrencyCode::Euro, 2);
            $side = $source->soll_haben === 'H' ? 'haben' : 'soll';
            $rows[$group]['count']++;
            $rows[$group][$side] = $rows[$group][$side]->plus($amount);
        }
        ksort($rows);

        return $rows;
    }
}
