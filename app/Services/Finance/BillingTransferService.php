<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingTransferService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Finance;

use App\Enums\Finance\{TransferChannel, TransferStatus, TransferTarget};
use App\Models\{Customer, ExternalReference, MaterialUsage, TimeEntry, User};
use App\Models\Finance\{BillingTransfer, BillingTransferEvent, BillingTransferItem};
use App\Services\Concerns\ResolvesActorId;
use Carbon\CarbonInterface;
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};
use Illuminate\Support\{Carbon, Collection};
use Illuminate\Support\Facades\DB;

/**
 * Statusmaschine für Übergabenachweise (Feature 045, „Freigabe und Aggregation"):
 *
 *   createDraft() → confirm() → markTransferred()
 *                             ↘ markFailed() → confirm() (Retry)
 *   draft|confirmed → void()
 *
 * Quellen-Schutz (Doppelverbrauch):
 *  - createDraft() sammelt nur abrechenbare, noch nicht lokal fakturierte
 *    (`exported`/`billed`) Quellen und schließt zusätzlich Quellen aus, die
 *    bereits in einem anderen Transfer mit Status confirmed|transferred hängen.
 *  - Erst markTransferred() verbraucht die Quellen (exported/billed = true,
 *    atomar in DB::transaction).
 *  - void() gibt Quellen nur frei, wenn der Transfer NIE transferred war.
 *
 * Jede Statusänderung schreibt ein {@see BillingTransferEvent} in die
 * revisionssichere Hash-Kette (config('audit.chains'), `audit:verify`).
 */
class BillingTransferService {
    use ResolvesActorId;

    /**
     * Erzeugt einen Draft-Übergabenachweis und sammelt die übergabefähigen
     * Quellen des Kunden im Zeitraum (Muster aus InvoiceGenerator).
     *
     * @param  array{from?: string|CarbonInterface|null, to?: string|CarbonInterface|null}  $period
     * @param  list<int>|null  $sourceIds  Optionale Auswahl: nur diese Quell-IDs
     *                                     (Schnittmenge mit den übergabefähigen Quellen).
     *
     * @throws BillingTransferException wenn keine übergabefähigen Quellen vorliegen.
     */
    public function createDraft(
        Customer $customer,
        TransferChannel $channel,
        TransferTarget $target,
        array $period = [],
        ?array $sourceIds = null,
        ?User $actor = null,
    ): BillingTransfer {
        $sources = $channel === TransferChannel::Time
            ? $this->collectTimeSources($customer, $period, $sourceIds)
            : $this->collectMaterialSources($customer, $period, $sourceIds);

        if ($sources->isEmpty()) {
            throw new BillingTransferException(
                'noSources',
                (string) __('finance.error.no_sources'),
                ['customer_id' => $customer->id, 'channel' => $channel->value],
            );
        }

        $positions = array_values($sources->map(fn(array $s): array => [
            'type' => $s['type'],
            'id' => $s['id'],
            'date' => $s['date'],
            'quantity' => $s['quantity'],
            'amount' => $s['amount'],
            'unit' => $s['unit'],
            'unit_price' => $s['unit_price'],
            'tax_rate' => $s['tax_rate'],
            'cost_position' => $s['cost_position'],
        ])->all());

        $actorId = $this->resolveActorId($actor);

        return DB::transaction(function () use ($customer, $channel, $target, $period, $sources, $positions, $actorId): BillingTransfer {
            /** @var BillingTransfer $transfer */
            $transfer = BillingTransfer::query()->create([
                'organization_id' => $customer->organization_id,
                'customer_id' => $customer->id,
                'channel' => $channel,
                'target' => $target,
                'status' => TransferStatus::Draft,
                'period_from' => ! empty($period['from']) ? Carbon::parse($period['from'])->toDateString() : null,
                'period_to' => ! empty($period['to']) ? Carbon::parse($period['to'])->toDateString() : null,
                'position_count' => $sources->count(),
                'total_amount' => (string) round((float) $sources->sum('amount'), 2),
                'total_quantity' => (string) round((float) $sources->sum('quantity'), 2),
                'payload_hash' => self::hashPositions($positions),
                'created_by_user_id' => $actorId,
            ]);

            foreach ($sources as $source) {
                $transfer->items()->create([
                    'source_type' => $source['type'],
                    'source_id' => $source['id'],
                    'amount' => (string) $source['amount'],
                    'quantity' => (string) $source['quantity'],
                    'unit' => $source['unit'],
                    'unit_price' => $source['unit_price'] !== null ? (string) $source['unit_price'] : null,
                    'tax_rate' => $source['tax_rate'] !== null ? (string) $source['tax_rate'] : null,
                    'cost_position' => $source['cost_position'],
                ]);
            }

            $this->recordEvent($transfer, 'created', $actorId, [
                'status' => TransferStatus::Draft->value,
                'position_count' => $sources->count(),
                'payload_hash' => $transfer->payload_hash,
            ]);

            return $transfer->refresh()->load('items');
        });
    }

    /** draft → confirmed (sowie failed → confirmed als Retry). */
    public function confirm(BillingTransfer $transfer, ?User $actor = null): BillingTransfer {
        return $this->transition($transfer, TransferStatus::Confirmed, $actor);
    }

    /**
     * confirmed → transferred: verbraucht die Quellen (TimeEntry.exported bzw.
     * MaterialUsage.billed) atomar und hinterlegt den Zielnachweis
     * (ExternalReference bei API-Zielen, file_path bei Datei-Übergabe).
     */
    public function markTransferred(
        BillingTransfer $transfer,
        ?ExternalReference $externalReference = null,
        ?string $filePath = null,
        ?User $actor = null,
    ): BillingTransfer {
        $this->assertTransition($transfer, TransferStatus::Transferred);

        $actorId = $this->resolveActorId($actor);

        return DB::transaction(function () use ($transfer, $externalReference, $filePath, $actorId): BillingTransfer {
            $this->setSourceFlags($transfer, true);

            $transfer->fill([
                'status' => TransferStatus::Transferred,
                'transferred_at' => now(),
                'external_reference_id' => $externalReference?->id,
                'file_path' => $filePath,
                'failure_reason' => null,
            ])->save();

            $this->recordEvent($transfer, 'transferred', $actorId, [
                'status' => TransferStatus::Transferred->value,
                'external_reference_id' => $externalReference?->id,
                'file_path' => $filePath,
                'payload_hash' => $transfer->payload_hash,
            ]);

            // Feature-Nutzungszähler (036; Vollaudit 2026-07, N14).
            app(\App\Services\Metrics\OperationsMetricsService::class)->increment('finance.transfer', (int) $transfer->organization_id);

            return $transfer->refresh();
        });
    }

    /** confirmed → failed (Quellen bleiben unberührt — Retry via confirm()). */
    public function markFailed(BillingTransfer $transfer, string $reason, ?User $actor = null): BillingTransfer {
        $this->assertTransition($transfer, TransferStatus::Failed);

        $actorId = $this->resolveActorId($actor);

        $transfer = DB::transaction(function () use ($transfer, $reason, $actorId): BillingTransfer {
            $transfer->fill([
                'status' => TransferStatus::Failed,
                'failure_reason' => $reason,
            ])->save();

            $this->recordEvent($transfer, 'failed', $actorId, [
                'status' => TransferStatus::Failed->value,
                'failure_reason' => $reason,
            ]);

            return $transfer->refresh();
        });

        // Vollaudit 2026-07 (M16): Fehlschlag an die Buchhaltung melden —
        // die Finanzschnittstelle lief bisher komplett an der Registry vorbei.
        app(\App\Services\Notification\NotificationDispatcher::class)->notify(
            \App\Enums\Notification\NotificationEvent::FinanceTransferFailed,
            $transfer,
            null,
            [
                'title' => (string) __('notification.message.finance_transfer_failed_title', ['id' => $transfer->id]),
                'title_key' => 'notification.message.finance_transfer_failed_title',
                'title_params' => ['id' => $transfer->id],
                'message' => $reason,
                'url' => route('finance.transfers.show', $transfer),
            ],
        );

        return $transfer;
    }

    /**
     * draft|confirmed → voided: gibt die Quellen wieder frei. Nach einer
     * erfolgreichen Übergabe (transferred) ist void() nicht mehr möglich —
     * Korrekturen laufen dann über Storno-/Differenzübergaben (Teil B).
     */
    public function void(BillingTransfer $transfer, ?User $actor = null): BillingTransfer {
        $this->assertTransition($transfer, TransferStatus::Voided);

        if ($transfer->wasTransferred()) {
            // Defensive: Statusmaschine verhindert das bereits, aber die
            // Freigabe der Quellen darf unter keinen Umständen nach einer
            // erfolgten Übergabe passieren.
            throw new BillingTransferException(
                'alreadyTransferred',
                (string) __('finance.error.void_after_transfer'),
                ['transfer_id' => $transfer->id],
            );
        }

        $actorId = $this->resolveActorId($actor);

        return DB::transaction(function () use ($transfer, $actorId): BillingTransfer {
            // Quellen freigeben (exported/billed zurücksetzen) — NUR weil der
            // Transfer nie transferred war (oben geprüft).
            $this->setSourceFlags($transfer, false);

            $transfer->fill(['status' => TransferStatus::Voided])->save();

            $this->recordEvent($transfer, 'voided', $actorId, [
                'status' => TransferStatus::Voided->value,
            ]);

            return $transfer->refresh();
        });
    }

    /**
     * Kanonischer SHA-256-Hash über die Positionsliste (deterministische
     * Reihenfolge und Serialisierung).
     *
     * @param  list<array<string, mixed>>  $positions
     */
    public static function hashPositions(array $positions): string {
        usort($positions, static function (array $a, array $b): int {
            return [$a['type'], (int) $a['id']] <=> [$b['type'], (int) $b['id']];
        });

        $hash = CryptoHelper::hash(JsonHelper::encode($positions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $hash;
    }

    // ── intern ─────────────────────────────────────────────────────────

    /**
     * Übergabefähige Zeiteinträge: abrechenbar, noch nicht exportiert, dem
     * Kunden zugeordnet (über das Projekt), im Zeitraum und nicht bereits in
     * einem anderen Transfer (confirmed|transferred) reserviert.
     *
     * @param  array{from?: string|CarbonInterface|null, to?: string|CarbonInterface|null}  $period
     * @param  list<int>|null  $sourceIds
     * @return Collection<int, array{type: 'App\Models\TimeEntry', id: int, date: string|null, quantity: float, amount: float, unit: null, unit_price: null, tax_rate: null, cost_position: null}>
     */
    private function collectTimeSources(Customer $customer, array $period, ?array $sourceIds): Collection {
        $query = TimeEntry::query()
            ->where('billable', true)
            ->where('exported', false)
            ->whereHas('project', fn($q) => $q->where('customer_id', $customer->id));

        if (! empty($period['from'])) {
            $query->where('date', '>=', Carbon::parse($period['from'])->toDateString());
        }
        if (! empty($period['to'])) {
            $query->where('date', '<=', Carbon::parse($period['to'])->toDateString());
        }
        if ($sourceIds !== null) {
            $query->whereIn('id', $sourceIds);
        }

        $this->excludeReserved($query, TimeEntry::class);

        return $query->orderBy('date')->get()->map(fn(TimeEntry $entry): array => [
            'type' => TimeEntry::class,
            'id' => (int) $entry->id,
            'date' => $entry->date?->toDateString(),
            'quantity' => round(((int) $entry->minutes) / 60, 2),
            'amount' => round((float) $entry->rate, 2),
            // Zeit-Positionen tragen keine Material-Felder (einheitliche Snapshot-Shape).
            'unit' => null,
            'unit_price' => null,
            'tax_rate' => null,
            'cost_position' => null,
        ])->values();
    }

    /**
     * Übergabefähige Materialverwendungen: noch nicht abgerechnet, dem Kunden
     * zugeordnet (über Timesheet → Projekt), im Zeitraum (work_date) und nicht
     * bereits in einem anderen Transfer (confirmed|transferred) reserviert.
     *
     * @param  array{from?: string|CarbonInterface|null, to?: string|CarbonInterface|null}  $period
     * @param  list<int>|null  $sourceIds
     * @return Collection<int, array{type: 'App\Models\MaterialUsage', id: int, date: string|null, quantity: float, amount: float, unit: non-empty-string|null, unit_price: float|null, tax_rate: float|null, cost_position: non-empty-string|null}>
     */
    private function collectMaterialSources(Customer $customer, array $period, ?array $sourceIds): Collection {
        $query = MaterialUsage::query()
            ->where('billed', false)
            ->whereHas('timesheet', function ($q) use ($customer, $period): void {
                $q->whereHas('project', fn($p) => $p->where('customer_id', $customer->id));
                if (! empty($period['from'])) {
                    $q->where('work_date', '>=', Carbon::parse($period['from'])->toDateString());
                }
                if (! empty($period['to'])) {
                    $q->where('work_date', '<=', Carbon::parse($period['to'])->toDateString());
                }
            });

        if ($sourceIds !== null) {
            $query->whereIn('id', $sourceIds);
        }

        $this->excludeReserved($query, MaterialUsage::class);

        return $query->with(['timesheet:id,work_date', 'material:id,sku'])->get()->map(fn(MaterialUsage $usage): array => [
            'type' => MaterialUsage::class,
            'id' => (int) $usage->id,
            'date' => $usage->timesheet?->work_date?->toDateString(),
            'quantity' => round((float) $usage->quantity, 2),
            'amount' => round((float) $usage->line_total_net, 2),
            // Materialpositions-Snapshot (Kriterium 6): Einheit, Einzelpreis,
            // Steuersatz und DATEV-Kostenposition (Material-SKU) zum Übergabezeitpunkt.
            'unit' => $usage->unit !== '' ? (string) $usage->unit : null,
            'unit_price' => $usage->unit_price !== null ? round((float) $usage->unit_price, 4) : null,
            'tax_rate' => $usage->tax_rate !== null ? round((float) $usage->tax_rate, 2) : null,
            'cost_position' => ($sku = trim((string) data_get($usage->material, 'sku', ''))) !== '' ? $sku : null,
        ])->values();
    }

    /**
     * Schließt Quellen aus, die bereits in einem anderen Transfer mit Status
     * confirmed|transferred reserviert/verbraucht sind (Schutz gegen
     * Doppelverbrauch — zusätzlich zu exported/billed).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TimeEntry>|\Illuminate\Database\Eloquent\Builder<MaterialUsage>  $query
     * @param  class-string  $sourceType
     */
    private function excludeReserved($query, string $sourceType): void {
        $query->whereNotExists(function ($sub) use ($sourceType, $query): void {
            $sub->from('billing_transfer_items')
                ->join('billing_transfers', 'billing_transfers.id', '=', 'billing_transfer_items.billing_transfer_id')
                ->whereColumn('billing_transfer_items.source_id', $query->getModel()->getTable() . '.id')
                ->where('billing_transfer_items.source_type', $sourceType)
                ->whereIn('billing_transfers.status', [TransferStatus::Confirmed->value, TransferStatus::Transferred->value])
                ->whereNull('billing_transfers.deleted_at');
        });
    }

    /**
     * Setzt bzw. löscht die Verbrauchs-Flags der Quellen dieses Transfers
     * (TimeEntry.exported / MaterialUsage.billed). saveQuietly analog
     * InvoiceGenerator — kein Observer-/Recalc-Rauschen.
     */
    private function setSourceFlags(BillingTransfer $transfer, bool $consumed): void {
        foreach ($transfer->items()->get() as $item) {
            /** @var BillingTransferItem $item */
            if ($item->source_type === TimeEntry::class) {
                $entry = TimeEntry::query()->find($item->source_id);
                if ($entry !== null && (bool) $entry->exported !== $consumed) {
                    $entry->exported = $consumed;
                    $entry->saveQuietly();
                }
            } elseif ($item->source_type === MaterialUsage::class) {
                $usage = MaterialUsage::query()->find($item->source_id);
                if ($usage !== null && (bool) $usage->billed !== $consumed) {
                    $usage->billed = $consumed;
                    $usage->saveQuietly();
                }
            }
        }
    }

    /** Validierter Statuswechsel + Persistenz + Hash-Ketten-Event. */
    private function transition(BillingTransfer $transfer, TransferStatus $to, ?User $actor): BillingTransfer {
        $this->assertTransition($transfer, $to);

        $actorId = $this->resolveActorId($actor);
        $from = $transfer->status;

        return DB::transaction(function () use ($transfer, $from, $to, $actorId): BillingTransfer {
            $transfer->fill(['status' => $to])->save();

            $this->recordEvent($transfer, $to->value, $actorId, [
                'status' => $to->value,
                'from' => $from->value,
            ]);

            return $transfer->refresh();
        });
    }

    private function assertTransition(BillingTransfer $transfer, TransferStatus $to): void {
        if (! $transfer->status->canTransitionTo($to)) {
            throw new BillingTransferException(
                'illegalTransition',
                (string) __('finance.error.illegal_transition', [
                    'from' => $transfer->status->label(),
                    'to' => $to->label(),
                ]),
                ['from' => $transfer->status->value, 'to' => $to->value, 'transfer_id' => $transfer->id],
            );
        }
    }

    /** @param  array<string, mixed>  $payload */
    private function recordEvent(BillingTransfer $transfer, string $event, ?int $actorId, array $payload): BillingTransferEvent {
        return BillingTransferEvent::create([
            'organization_id' => $transfer->organization_id,
            'billing_transfer_id' => $transfer->id,
            'event' => $event,
            'actor_user_id' => $actorId,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
