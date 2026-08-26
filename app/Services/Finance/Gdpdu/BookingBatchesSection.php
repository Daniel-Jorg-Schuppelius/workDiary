<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BookingBatchesSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Gdpdu;

use App\Enums\Finance\DatevBatchStatus;
use App\Models\Finance\DatevBookingBatch;
use App\Models\Organization;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Buchungsstapel-Nachweis (A16): NUR exportierte (festgeschriebene) Stapel,
 * deren Buchungszeitraum den Prüfungszeitraum berührt. Kopf + Positionen
 * kommen aus den persistierten Nachweisdaten (Batch/Source-Snapshots) —
 * bewusst KEINE Neuberechnung aus den Quellposten: exportiert ist, was im
 * Snapshot steht (inkl. Generalumkehr-/Storno-Kennzeichen, MVP-334).
 */
class BookingBatchesSection extends AbstractGdpduSection {
    public function key(): string {
        return 'booking_batches';
    }

    public function definition(): array {
        return [
            'file' => 'buchungsstapel.csv',
            'name' => 'Buchungsstapel',
            'description' => 'Exportierte (festgeschriebene) DATEV-Buchungsstapel, deren Buchungszeitraum den Prüfungszeitraum berührt — persistierter Nachweisstand des Exports inkl. Datei-Hash.',
            'columns' => [
                ['name' => 'Stapelnummer', 'type' => 'numeric', 'accuracy' => 0],
                ['name' => 'Zeitraum_von', 'type' => 'date'],
                ['name' => 'Zeitraum_bis', 'type' => 'date'],
                ['name' => 'Exportiert_am', 'type' => 'alpha'],
                ['name' => 'SKR', 'type' => 'alpha'],
                ['name' => 'Auswahl', 'type' => 'alpha'],
                ['name' => 'Buchungen', 'type' => 'numeric', 'accuracy' => 0],
                ['name' => 'Gesamtbetrag', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'Export_Hash', 'type' => 'alpha'],
            ],
        ];
    }

    /**
     * Gemeinsame Basisabfrage — auch Stapelnummern-Quelle der Positionen
     * ({@see BookingBatchItemsSection}), damit Kopf und Posten zusammenpassen.
     *
     * @return Builder<DatevBookingBatch>
     */
    public static function exportedBatches(Organization $organization, CarbonInterface $from, CarbonInterface $to): Builder {
        return DatevBookingBatch::query()
            ->where('organization_id', $organization->id)
            ->where('status', DatevBatchStatus::Exported->value)
            ->where('period_from', '<=', $to->toDateString())
            ->where('period_to', '>=', $from->toDateString())
            ->orderBy('batch_no')->orderBy('id');
    }

    public function rows(Organization $organization, CarbonInterface $from, CarbonInterface $to): iterable {
        foreach (self::exportedBatches($organization, $from, $to)->lazy() as $batch) {
            yield [
                $this->num($batch->batch_no, 0),
                $this->date($batch->period_from),
                $this->date($batch->period_to),
                $this->dateTime($batch->finalized_at),
                $this->str($batch->skr),
                $this->str($batch->selection_mode),
                $this->num($batch->booking_count, 0),
                $this->num($batch->total_amount, 2),
                $this->str($batch->file_hash),
            ];
        }
    }
}
