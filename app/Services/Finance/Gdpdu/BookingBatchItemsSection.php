<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BookingBatchItemsSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Gdpdu;

use App\Models\Finance\DatevBookingSource;
use App\Models\Organization;
use Carbon\CarbonInterface;

/**
 * Quellposten-Snapshots der exportierten Stapel — wie übergeben, keine
 * Neuberechnung (Stapelquelle: {@see BookingBatchesSection::exportedBatches()}).
 */
class BookingBatchItemsSection extends AbstractGdpduSection {
    public function key(): string {
        return 'booking_batch_items';
    }

    public function definition(): array {
        return [
            'file' => 'buchungsstapelpositionen.csv',
            'name' => 'Buchungsstapel-Positionen',
            'description' => 'Quellposten (Buchungssatz-Snapshots) der exportierten DATEV-Buchungsstapel inkl. Generalumkehr-/Storno-Kennzeichen — wie übergeben, keine Neuberechnung.',
            'columns' => [
                ['name' => 'Stapelnummer', 'type' => 'numeric', 'accuracy' => 0],
                ['name' => 'Belegfeld', 'type' => 'alpha'],
                ['name' => 'Quelltyp', 'type' => 'alpha'],
                ['name' => 'Konto', 'type' => 'alpha'],
                ['name' => 'Gegenkonto', 'type' => 'alpha'],
                ['name' => 'SH', 'type' => 'alpha'],
                ['name' => 'Betrag', 'type' => 'numeric', 'accuracy' => 2],
                ['name' => 'BU_Schluessel', 'type' => 'alpha'],
                ['name' => 'Generalumkehr', 'type' => 'alpha'],
            ],
        ];
    }

    public function rows(Organization $organization, CarbonInterface $from, CarbonInterface $to): array {
        $batches = BookingBatchesSection::exportedBatches($organization, $from, $to)->get();
        $numberById = $batches->pluck('batch_no', 'id');

        $rows = [];
        DatevBookingSource::query()
            ->whereIn('datev_booking_batch_id', $batches->modelKeys())
            ->orderBy('datev_booking_batch_id')->orderBy('id')
            ->get()
            ->each(function (DatevBookingSource $source) use (&$rows, $numberById): void {
                $rows[] = [
                    $this->num($numberById[$source->datev_booking_batch_id] ?? null, 0),
                    $this->str($source->document_ref),
                    class_basename($source->source_type),
                    $this->str($source->debtor_account),
                    $this->str($source->revenue_account),
                    $this->str($source->soll_haben),
                    $this->num($source->amount, 2),
                    $this->str($source->tax_key),
                    $source->is_reversal ? 'Ja' : 'Nein',
                ];
            });

        return $rows;
    }
}
