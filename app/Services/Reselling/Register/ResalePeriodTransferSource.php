<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResalePeriodTransferSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Finance\{TransferChannel, TransferStatus};
use App\Models\Customer\Customer;
use App\Models\Finance\{BillingTransfer, BillingTransferItem, BillingTransferPosition};
use App\Models\Integration\ExternalReference;
use App\Models\Reselling\ResalePeriod;
use App\Services\Finance\Contracts\TransferSource;
use App\Support\MorphMap;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Abo-Perioden als Übergabequelle (Feature 185, MVP-1422): die offenen
 * Perioden eines Rechnungsempfängers werden Positionen eines
 * Übergabenachweises — eine je Abo und Zeitraum mit Endkunde und Artikel wie
 * im Rechnungsvorschlag. Übertragen stempelt die Perioden mit der
 * Fremdreferenz des Belegs (`draft_reference`), damit der Spiegel den
 * fertigen Beleg zuordnet; Storno oder Verwerfen nimmt den Stempel zurück.
 *
 * @phpstan-import-type SourceRow from TransferSource
 */
final class ResalePeriodTransferSource implements TransferSource {
    public const SOURCE_KIND = 'resale';

    public function __construct(private readonly ResaleInvoiceDraftService $drafts) {}

    public function channel(): TransferChannel {
        return TransferChannel::Resale;
    }

    /** @return Collection<int, SourceRow> */
    public function collect(Customer $customer, array $period, ?array $sourceIds): Collection {
        try {
            $entries = $this->drafts->openEntries($customer);
        } catch (RuntimeException) {
            return collect(); // nichts offen oder schon im Entwurf — der Dienst meldet „keine Quellen“
        }
        $from = ! empty($period['from']) ? CarbonImmutable::parse($period['from'])->startOfDay() : null;
        $to = ! empty($period['to']) ? CarbonImmutable::parse($period['to'])->startOfDay() : null;
        $reserved = $this->reservedPeriodIds(array_map(static fn(array $e): int => (int) $e['period']->id, $entries));
        $type = MorphMap::alias(ResalePeriod::class);
        /** @var list<SourceRow> $rows */
        $rows = [];
        foreach ($entries as $entry) {
            $periodModel = $entry['period'];
            if ($sourceIds !== null && ! in_array((int) $periodModel->id, $sourceIds, true)) {
                continue;
            }
            if (($from !== null && $periodModel->starts_on->lessThan($from)) || ($to !== null && $periodModel->starts_on->greaterThan($to)) || isset($reserved[$periodModel->id])) {
                continue;
            }
            $line = $entry['line'];
            $rows[] = [
                'type' => $type,
                'id' => (int) $periodModel->id,
                'date' => $periodModel->starts_on->toDateString(),
                'quantity' => (float) $line['quantity'],
                'amount' => round($line['quantity'] * $line['unit_net'], 2),
                'unit' => $line['unit_name'],
                'unit_price' => (float) $line['unit_net'],
                'tax_rate' => null,
                'cost_position' => null,
            ];
        }

        return collect($rows);
    }

    public function positions(BillingTransfer $transfer): Collection {
        $ids = $transfer->items->where('source_type', MorphMap::alias(ResalePeriod::class))->pluck('source_id')->map(static fn($id): int => (int) $id)->all();
        $periods = ResalePeriod::query()->withoutGlobalScopes()
            ->whereIn('id', $ids)
            ->with(['subscription.foreignCustomer', 'links'])
            ->orderBy('starts_on')->orderBy('id')
            ->get();
        $positions = new Collection;
        $index = 0;
        foreach ($periods as $period) {
            $line = $this->drafts->lineFor($period);
            if ($line === null) {
                continue;
            }
            $positions->push(new BillingTransferPosition([
                'organization_id' => $transfer->organization_id,
                'billing_transfer_id' => $transfer->id,
                'position' => ++$index,
                'source_kind' => self::SOURCE_KIND,
                'source_ids' => [(int) $period->id],
                'primary_source_id' => (int) $period->id,
                'name' => $line['name'],
                'description' => $line['description'],
                'quantity' => round($line['quantity'], 3),
                'unit_name' => $line['unit_name'],
                'unit_price' => round($line['unit_net'], 4),
                'vat_rate' => null,
                'amount' => round($line['quantity'] * $line['unit_net'], 2),
                'article_ref' => $line['article_ref'],
                'service_source' => null,
                'price_source' => self::SOURCE_KIND,
                'service_from' => $period->starts_on->toDateString(),
                'service_to' => $period->ends_on->toDateString(),
            ]));
        }

        return $positions;
    }

    public function setConsumed(BillingTransfer $transfer, bool $consumed, array $heldElsewhere): int {
        $type = MorphMap::alias(ResalePeriod::class);
        $held = $heldElsewhere[$type] ?? [];
        $ids = $transfer->items()->where('source_type', $type)->pluck('source_id')->map(static fn($id): int => (int) $id)->all();
        $reference = self::referenceOf($transfer);
        $label = (string) __('resale.draft.transfer_note', ['number' => $reference]);
        $changed = 0;
        foreach (ResalePeriod::query()->withoutGlobalScopes()->whereIn('id', $ids)->get() as $period) {
            if (in_array((int) $period->id, $held, true)) {
                continue;
            }
            if ($consumed) {
                $period->appendNote($label);
                $period->forceFill(['draft_reference' => $reference, 'draft_created_at' => now()])->save();
                $changed++;
            } elseif ($period->draft_reference === $reference) {
                $period->forceFill(['draft_reference' => null, 'draft_created_at' => null])->save();
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * Stempel der Perioden: die Belegkennung im Buchhaltungsprogramm, sonst
     * die Übergabe selbst. Über die Kennung erkennt der Spiegel den fertigen
     * Beleg ({@see LinkProposer::matchesDraft()}).
     */
    public static function referenceOf(BillingTransfer $transfer): string {
        $external = $transfer->external_reference_id !== null ? ExternalReference::query()->find($transfer->external_reference_id) : null;
        $externalId = $external?->external_id;

        return is_string($externalId) && $externalId !== '' ? $externalId : 'transfer:' . $transfer->id;
    }

    /**
     * Perioden, die eine bestätigte oder übergebene Übergabe hält (Pendant zu `excludeReserved`).
     *
     * @param  list<int>  $ids
     * @return array<int, true>
     */
    private function reservedPeriodIds(array $ids): array {
        if ($ids === []) {
            return [];
        }
        $reserved = BillingTransferItem::query()
            ->join('billing_transfers', 'billing_transfers.id', '=', 'billing_transfer_items.billing_transfer_id')
            ->where('billing_transfer_items.source_type', MorphMap::alias(ResalePeriod::class))
            ->whereIn('billing_transfer_items.source_id', $ids)
            ->whereIn('billing_transfers.status', [TransferStatus::Confirmed->value, TransferStatus::Transferred->value])
            ->whereNull('billing_transfers.deleted_at')
            ->pluck('billing_transfer_items.source_id');

        return array_fill_keys(array_map('intval', $reserved->all()), true);
    }

}
