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
use App\Support\Query\DateRange;
use Carbon\CarbonInterface;
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};
use Illuminate\Support\{Carbon, Collection};
use Illuminate\Support\Facades\DB;

/**
 * Statusmaschine für Übergabenachweise (Feature 045, „Freigabe und Aggregation"):
 *
 *   createDraft() → confirm() → markTransferred() → cancel() (Storno)
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
 *  - cancel() ist der dokumentierte Rückweg NACH der Übergabe (Storno): gibt
 *    die Quellen frei und lässt den Nachweis als `cancelled` stehen.
 *  - Freigaben (void/cancel) überspringen Quellen, die ein ANDERER Nachweis
 *    mit Status confirmed|transferred noch hält (Korrektur-Ketten).
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
                'intro_text' => $this->renderText($customer, 'transfer_intro_text', $channel, $period),
                'closing_text' => $this->renderText($customer, 'transfer_closing_text', $channel, $period)
                    ?? ($customer->invoice_text !== null && trim((string) $customer->invoice_text) !== ''
                        ? trim((string) $customer->invoice_text) : null),
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

    /**
     * draft → confirmed (sowie failed → confirmed als Retry). Beim Bestätigen
     * werden die Positionen eingefroren (MVP-487): ab hier senden die Ziele
     * genau das, was die Vorschau zeigt — und der Text ist prüfbar.
     */
    public function confirm(BillingTransfer $transfer, ?User $actor = null): BillingTransfer {
        $this->assertSourcesFree($transfer);

        $confirmed = $this->transition($transfer, TransferStatus::Confirmed, $actor);
        $positions = app(BillingPositionBuilder::class)->freeze($confirmed);

        // Kopfzahlen folgen den eingefrorenen Positionen (MVP-491): sie sind
        // das, was tatsächlich fakturiert wird. Die ungetaktete Quellsumme
        // steht weiter unter den Einzelquellen — als Kopfzahl wäre sie
        // irreführend (Taktung, Standardleistung, nachbewertete Sätze).
        $confirmed->forceFill([
            'position_count' => $positions->count(),
            'total_quantity' => (string) round((float) $positions->sum(fn($p): float => $p->quantityFloat()), 2),
            'total_amount' => (string) round((float) $positions->sum(fn($p): float => $p->amountFloat()), 2),
        ])->save();

        return $confirmed->load('positions');
    }

    /**
     * Korrektur-Übergabe zu einem bereits übergebenen Nachweis (MVP-490).
     *
     * Der ursprüngliche Nachweis bleibt unverändert und unveränderlich — die
     * Korrektur ist ein EIGENER Nachweis, der über `corrects_transfer_id` auf
     * ihn zeigt. Damit bleibt sichtbar, was ursprünglich rausging und was es
     * abgelöst hat, statt einen ausgelieferten Beleg still zurückzudrehen.
     *
     * Sie erbt dieselben Quellen (die Zeiten sind bereits verbraucht und
     * bleiben es) und startet als Entwurf: Bestätigen friert die Positionen
     * neu ein — damit greifen zwischenzeitlich korrigierte Sätze und
     * Standardleistungen —, danach sind Texte prüfbar und das Übertragen legt
     * beim Ziel einen frischen Beleg an. Den alten Entwurf löscht man im
     * Zielsystem von Hand: Lexoffice kennt für Belege weder Update noch Delete.
     */
    public function createCorrection(BillingTransfer $original, ?string $reason = null, ?User $actor = null): BillingTransfer {
        // Nur zu einem AKTUELL übergebenen Nachweis — nach einem Storno sind
        // die Quellen frei und gehören in eine frische Übergabe, nicht in eine
        // Korrektur (die würde sie am Reservierungs-Schutz vorbei erneut binden).
        if ($original->status !== TransferStatus::Transferred) {
            throw new BillingTransferException(
                'correctionNotTransferred',
                (string) __('finance.error.correction_only_transferred'),
                ['status' => $original->status->value, 'transfer_id' => $original->id],
            );
        }

        $original->loadMissing('items');
        if ($original->items->isEmpty()) {
            throw new BillingTransferException(
                'noSources',
                (string) __('finance.error.no_sources'),
                ['transfer_id' => $original->id],
            );
        }

        $actorId = $this->resolveActorId($actor);

        return DB::transaction(function () use ($original, $reason, $actorId): BillingTransfer {
            /** @var list<array<string, mixed>> $positions */
            $positions = array_values($original->items->map(fn(BillingTransferItem $item): array => [
                'type' => $item->source_type,
                'id' => (int) $item->source_id,
                'date' => null,
                'quantity' => (float) $item->quantity,
                'amount' => (float) $item->amount,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price !== null ? (float) $item->unit_price : null,
                'tax_rate' => $item->tax_rate !== null ? (float) $item->tax_rate : null,
                'cost_position' => $item->cost_position,
            ])->all());

            /** @var BillingTransfer $correction */
            $correction = BillingTransfer::query()->create([
                'organization_id' => $original->organization_id,
                'customer_id' => $original->customer_id,
                'channel' => $original->channel,
                'target' => $original->target,
                'status' => TransferStatus::Draft,
                'corrects_transfer_id' => $original->id,
                'correction_reason' => $reason !== null && trim($reason) !== '' ? mb_substr(trim($reason), 0, 500) : null,
                'period_from' => $original->period_from?->toDateString(),
                'period_to' => $original->period_to?->toDateString(),
                'position_count' => $original->items->count(),
                'total_amount' => (string) round((float) $original->items->sum('amount'), 2),
                'total_quantity' => (string) round((float) $original->items->sum('quantity'), 2),
                'payload_hash' => self::hashPositions($positions),
                'created_by_user_id' => $actorId,
                'intro_text' => $original->intro_text,
                'closing_text' => $original->closing_text,
            ]);

            // Quellen 1:1 übernehmen — dieselben Zeiten, jetzt unter dem
            // korrigierenden Nachweis.
            foreach ($original->items as $item) {
                $correction->items()->create([
                    'source_type' => $item->source_type,
                    'source_id' => $item->source_id,
                    'amount' => $item->amount,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                    'cost_position' => $item->cost_position,
                ]);
            }

            $this->recordEvent($correction, 'created_as_correction', $actorId, [
                'corrects_transfer_id' => (int) $original->id,
                'reason' => $correction->correction_reason,
            ]);

            // Auch am Original vermerken: das Ereignis ist eine Kind-Zeile und
            // rührt den unveränderlichen Nachweis selbst nicht an.
            $this->recordEvent($original, 'correction_created', $actorId, [
                'correction_transfer_id' => (int) $correction->id,
                'reason' => $correction->correction_reason,
            ]);

            return $correction->refresh()->load('items');
        });
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
     * transferred → cancelled (Storno): der Rückweg, wenn der beim Ziel
     * entstandene Beleg-Entwurf verworfen wurde (z. B. orgaMAX-Auftrag oder
     * Lexoffice-Entwurf von Hand gelöscht, Datei-Paket nicht verwendet). Gibt
     * die Quellen wieder frei — außer solchen, die ein anderer Nachweis
     * (confirmed|transferred) hält, etwa eine bestätigte Korrektur-Übergabe.
     *
     * Der Nachweis selbst bleibt mit `transferred_at` als historischem
     * Übergabe-Beleg stehen; Grund/Akteur dokumentiert das `cancelled`-Ereignis
     * in der Hash-Kette. Den Beleg im Zielsystem entfernt der Storno NICHT —
     * das bestätigt der Nutzer im Dialog.
     */
    public function cancel(BillingTransfer $transfer, ?string $reason = null, ?User $actor = null): BillingTransfer {
        $this->assertTransition($transfer, TransferStatus::Cancelled);

        $actorId = $this->resolveActorId($actor);

        return DB::transaction(function () use ($transfer, $reason, $actorId): BillingTransfer {
            $released = $this->setSourceFlags($transfer, false);

            $transfer->fill(['status' => TransferStatus::Cancelled])->save();

            $this->recordEvent($transfer, 'cancelled', $actorId, [
                'status' => TransferStatus::Cancelled->value,
                'reason' => $reason !== null && trim($reason) !== '' ? mb_substr(trim($reason), 0, 500) : null,
                'released_sources' => $released,
                'external_reference_id' => $transfer->external_reference_id,
                'file_path' => $transfer->file_path,
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
     * Rechnungstext aus der Org-Vorlage (MVP-491), Platzhalter aufgelöst.
     * Der Text wird beim Anlegen materialisiert — was am Nachweis steht, geht
     * auch raus, unabhängig von späteren Vorlagenänderungen.
     *
     * @param  array{from?: string|CarbonInterface|null, to?: string|CarbonInterface|null}  $period
     */
    private function renderText(Customer $customer, string $key, TransferChannel $channel, array $period): ?string {
        $settings = $customer->organization?->invoicingSettings() ?? [];
        $template = trim((string) ($settings[$key] ?? ''));
        if ($template === '') {
            return null;
        }

        return strtr($template, [
            ':customer' => (string) $customer->name,
            ':channel' => $channel->label(),
            ':from' => ! empty($period['from']) ? Carbon::parse($period['from'])->format('d.m.Y') : '—',
            ':to' => ! empty($period['to']) ? Carbon::parse($period['to'])->format('d.m.Y') : '—',
        ]);
    }

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
            $query->where('date', '<', DateRange::dayAfter(Carbon::parse($period['to'])));
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
            'amount' => round(($entry->rate?->toFloat() ?? 0.0), 2),
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
                    $q->where('work_date', '<', DateRange::dayAfter(Carbon::parse($period['to'])));
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
            'quantity' => round(($usage->quantity?->getValue()->toFloat() ?? 0.0), 2),
            'amount' => round($usage->line_total_net?->toFloat() ?? 0.0, 2),
            // Materialpositions-Snapshot (Kriterium 6): Einheit, Einzelpreis,
            // Steuersatz und DATEV-Kostenposition (Material-SKU) zum Übergabezeitpunkt.
            'unit' => $usage->unit !== '' ? (string) $usage->unit : null,
            'unit_price' => $usage->unit_price !== null ? round($usage->unit_price->toFloat(), 4) : null,
            'tax_rate' => $usage->tax_rate !== null ? round((float) $usage->tax_rate->getNumericValue(), 2) : null,
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
     *
     * Beim Freigeben (void/cancel) bleiben Quellen verbraucht, die ein
     * ANDERER Nachweis mit Status confirmed|transferred hält — sonst würde
     * z. B. das Verwerfen einer Korrektur-Übergabe die Zeiten des
     * ursprünglichen, übergebenen Nachweises freigeben.
     *
     * @return int Anzahl der tatsächlich umgestellten Quellen.
     */
    private function setSourceFlags(BillingTransfer $transfer, bool $consumed): int {
        $items = $transfer->items()->get();
        $held = $consumed ? [] : $this->sourcesHeldElsewhere($transfer, $items);
        $changed = 0;

        foreach ($items as $item) {
            /** @var BillingTransferItem $item */
            if (in_array((int) $item->source_id, $held[$item->source_type] ?? [], true)) {
                continue;
            }

            if ($item->source_type === TimeEntry::class) {
                $entry = TimeEntry::query()->find($item->source_id);
                if ($entry !== null && (bool) $entry->exported !== $consumed) {
                    $entry->exported = $consumed;
                    $entry->saveQuietly();
                    $changed++;
                }
            } elseif ($item->source_type === MaterialUsage::class) {
                $usage = MaterialUsage::query()->find($item->source_id);
                if ($usage !== null && (bool) $usage->billed !== $consumed) {
                    $usage->billed = $consumed;
                    $usage->saveQuietly();
                    $changed++;
                }
            }
        }

        return $changed;
    }

    /**
     * Quellen dieses Transfers, die zusätzlich in einem ANDEREN Nachweis mit
     * Status confirmed|transferred hängen (Korrektur-Ketten, Doppel-Drafts).
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, BillingTransferItem>  $items
     * @return array<string, list<int>> source_type => fremdgehaltene source_ids
     */
    private function sourcesHeldElsewhere(BillingTransfer $transfer, \Illuminate\Database\Eloquent\Collection $items): array {
        if ($items->isEmpty()) {
            return [];
        }

        $held = [];
        foreach ($items->groupBy('source_type') as $sourceType => $group) {
            $ids = BillingTransferItem::query()
                ->join('billing_transfers', 'billing_transfers.id', '=', 'billing_transfer_items.billing_transfer_id')
                ->where('billing_transfers.id', '!=', $transfer->id)
                ->whereIn('billing_transfers.status', [TransferStatus::Confirmed->value, TransferStatus::Transferred->value])
                ->whereNull('billing_transfers.deleted_at')
                ->where('billing_transfer_items.source_type', $sourceType)
                ->whereIn('billing_transfer_items.source_id', $group->pluck('source_id')->all())
                ->pluck('billing_transfer_items.source_id');

            $held[(string) $sourceType] = array_values(array_map(static fn($id): int => (int) $id, $ids->all()));
        }

        return $held;
    }

    /**
     * Halten die Quellen dieses Nachweises schon andere?
     *
     * **Die Reservierungsprüfung lief nur beim Anlegen** (Sicherheitsscan
     * 2026-08-23, S-29). Zwei Entwürfe für denselben Kunden und Zeitraum —
     * zwei Tabs, zwei Personen, Toggl-Outbox plus manuell — enthielten
     * dieselben Zeiten; beide ließen sich bestätigen und übertragen. Der
     * Kunde bekam die Stunden doppelt berechnet, und die GoBD-Nachweise
     * widersprachen sich. Das Schwestermodul (DATEV-Stapel) hat den Guard
     * seit MVP-334; hier fehlte er.
     *
     * @throws BillingTransferException
     */
    private function assertSourcesFree(BillingTransfer $transfer): void {
        // Eine Korrektur-Übergabe (MVP-490) greift **absichtlich** auf die
        // Quellen des Nachweises zu, den sie korrigiert — sie ersetzt ihn ja.
        if ($transfer->corrects_transfer_id !== null) {
            return;
        }

        $items = $transfer->items()->get();
        $held = $this->sourcesHeldElsewhere($transfer, $items);

        $count = 0;
        foreach ($held as $ids) {
            $count += count($ids);
        }

        if ($count > 0) {
            throw new BillingTransferException(
                'sourcesAlreadyReserved',
                (string) __('finance.error.sources_already_reserved', ['count' => $count]),
                ['transfer_id' => $transfer->id, 'held' => $held],
            );
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
