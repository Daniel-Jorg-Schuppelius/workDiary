<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecallService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Claims\ClaimSource;
use App\Enums\Inventory\{RecallItemStatus, RecallStatus, SerialStatus};
use App\Enums\Numbering\NumberScope;
use App\Mail\RecallNoticeMail;
use App\Models\Article\ArticleVariant;
use App\Models\Claims\ClaimCase;
use App\Models\Document\DocumentDispatch;
use App\Models\Inventory\{Recall, RecallItem, StockDelivery, StockSerial};
use App\Models\Platform\{Organization, User};
use App\Services\Claims\Contracts\ClaimIntake;
use App\Services\Concerns\AssertsStatusTransition;
use App\Services\Numbering\NumberSequenceService;
use App\Support\Tz;
use CommonToolkit\Helper\Data\EmailHelper;
use Illuminate\Database\Eloquent\{Builder, Collection};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Mail};
use RuntimeException;

/**
 * Rückrufaktionen (MVP-921): Eingrenzung, Ermittlung der betroffenen
 * Auslieferungen und Kunden, Sperre der noch im Lager liegenden
 * Seriennummern und Stand je Auslieferung. Einzige Schreibstelle.
 */
final class RecallService {
    use AssertsStatusTransition;

    /** Felder des Entwurfs. */
    public const EDITABLE = [
        'kind', 'title', 'reason', 'customer_message', 'manufacturing_order_ids', 'delivered_from', 'delivered_until',
        'serial_numbers', 'is_blocking_stock',
    ];

    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly SerialService $serials,
        private readonly ClaimIntake $claims,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function create(ArticleVariant $variant, array $data, User $actor): Recall {
        return Recall::query()->create(array_intersect_key($data, array_flip(self::EDITABLE)) + [
            'organization_id' => $variant->organization_id,
            'number' => $this->numbers->next((int) $variant->organization_id, NumberScope::Recall),
            'article_variant_id' => $variant->id,
            'status' => RecallStatus::Draft->value,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Recall $recall, array $data, User $actor): Recall {
        if ($recall->status !== RecallStatus::Draft) {
            throw new RuntimeException((string) __('recall.error.not_draft'));
        }
        $recall->fill(array_intersect_key($data, array_flip(self::EDITABLE)) + ['updated_by' => $actor->id])->save();

        return $recall;
    }

    /**
     * Betroffene Auslieferungen: Variante und alle gesetzten Eingrenzungen.
     *
     * @return Builder<StockDelivery>
     */
    public function affectedDeliveries(Recall $recall): Builder {
        $orders = array_values(array_filter((array) $recall->manufacturing_order_ids));
        $serials = array_values(array_filter((array) $recall->serial_numbers));

        return StockDelivery::query()
            ->where('organization_id', $recall->organization_id)
            ->where('article_variant_id', $recall->article_variant_id)
            ->when($orders !== [], fn (Builder $q) => $q->whereIn('manufacturing_order_id', $orders))
            // Lieferzeitraum als Ortstage der Organisation, gespeichert ist UTC.
            ->when($recall->delivered_from, fn (Builder $q, Carbon $from) => $q->where('delivered_at', '>=', Tz::parse($from->format('Y-m-d'))->startOfDay()->utc()))
            ->when($recall->delivered_until, fn (Builder $q, Carbon $until) => $q->where('delivered_at', '<', Tz::parse($until->format('Y-m-d'))->startOfDay()->addDay()->utc()))
            ->when($serials !== [], fn (Builder $q) => $q->whereIn('id', StockSerial::query()->whereIn('serial_no', $serials)->whereNotNull('stock_delivery_id')->select('stock_delivery_id')))
            ->orderBy('delivered_at');
    }

    /**
     * Seriennummern der Eingrenzung, die noch nicht beim Kunden sind.
     *
     * @return Collection<int, StockSerial>
     */
    public function stockSerials(Recall $recall): Collection {
        $orders = array_values(array_filter((array) $recall->manufacturing_order_ids));
        $serials = array_values(array_filter((array) $recall->serial_numbers));

        return StockSerial::query()
            ->where('organization_id', $recall->organization_id)
            ->where('article_variant_id', $recall->article_variant_id)
            ->whereIn('status', [SerialStatus::Created->value, SerialStatus::InStock->value, SerialStatus::Reserved->value, SerialStatus::Returned->value])
            ->when($orders !== [], fn (Builder $q) => $q->whereIn('manufacturing_order_id', $orders))
            ->when($serials !== [], fn (Builder $q) => $q->whereIn('serial_no', $serials))
            ->get();
    }

    /** Aktivieren: betroffene Auslieferungen festschreiben, Lagerbestand sperren. */
    public function activate(Recall $recall, User $actor): Recall {
        $this->assertStatusTransition($recall->status, RecallStatus::Active);

        return DB::transaction(function () use ($recall, $actor): Recall {
            $serialFilter = array_values(array_filter((array) $recall->serial_numbers));
            foreach ($this->affectedDeliveries($recall)->get() as $delivery) {
                $shipped = StockSerial::query()
                    ->where('stock_delivery_id', $delivery->id)
                    ->where('article_variant_id', $recall->article_variant_id)
                    ->when($serialFilter !== [], fn (Builder $q) => $q->whereIn('serial_no', $serialFilter))
                    ->get();
                if ($shipped->isEmpty()) {
                    $this->item($recall, $delivery, null, $delivery->quantity?->getNumericValue());
                }
                foreach ($shipped as $serial) {
                    $this->item($recall, $delivery, $serial, '1');
                }
            }
            if ($recall->is_blocking_stock) {
                foreach ($this->stockSerials($recall) as $serial) {
                    $this->serials->block($serial, $this->blockReason($recall));
                }
            }
            $recall->forceFill(['status' => RecallStatus::Active->value, 'activated_at' => now(), 'updated_by' => $actor->id])->save();

            return $recall;
        });
    }

    public function complete(Recall $recall, User $actor): Recall {
        $this->assertStatusTransition($recall->status, RecallStatus::Completed);
        $recall->forceFill(['status' => RecallStatus::Completed->value, 'completed_at' => now(), 'updated_by' => $actor->id])->save();

        return $recall;
    }

    /** Abbrechen hebt die Sperren dieses Rückrufs wieder auf; andere Sperren bleiben. */
    public function cancel(Recall $recall, User $actor): Recall {
        $this->assertStatusTransition($recall->status, RecallStatus::Cancelled);

        return DB::transaction(function () use ($recall, $actor): Recall {
            StockSerial::query()
                ->where('organization_id', $recall->organization_id)
                ->where('status', SerialStatus::Blocked->value)
                ->where('blocked_reason', $this->blockReason($recall))
                ->get()
                ->each(fn (StockSerial $serial) => $this->serials->unblock($serial));
            $recall->forceFill(['status' => RecallStatus::Cancelled->value, 'completed_at' => now(), 'updated_by' => $actor->id])->save();

            return $recall;
        });
    }

    public function setItemStatus(RecallItem $item, RecallItemStatus $to, User $actor, ?string $note = null): RecallItem {
        $this->assertStatusTransition($item->status, $to);
        $item->status = $to;
        $stamp = match ($to) {
            RecallItemStatus::Notified => 'notified_at',
            RecallItemStatus::Returned => 'returned_at',
            RecallItemStatus::Resolved => 'resolved_at',
            RecallItemStatus::Open => null,
        };
        if ($stamp !== null) {
            $item->setAttribute($stamp, now());
        }
        if ($note !== null && trim($note) !== '') {
            $item->note = trim($note);
        }
        $item->save();

        return $item;
    }

    /**
     * Anschreiben an alle Kunden mit offenen Positionen (MVP-922): je Kunde
     * ein Versandnachweis und eine Mail; die Positionen gelten dann als
     * informiert. Kunden ohne gültige E-Mail bleiben offen und werden gemeldet.
     *
     * @return array{sent: int, without_email: list<string>}
     */
    public function sendNotices(Recall $recall, User $actor): array {
        if ($recall->status !== RecallStatus::Active) {
            throw new RuntimeException((string) __('recall.error.not_active'));
        }
        $open = $recall->items()->with('customer')->where('status', RecallItemStatus::Open->value)->whereNotNull('customer_id')->get()->groupBy('customer_id');
        $sent = 0;
        $withoutEmail = [];
        foreach ($open as $items) {
            $customer = $items->first()?->customer;
            if ($customer === null) {
                continue;
            }
            $email = trim((string) $customer->email);
            if (! EmailHelper::isEmail($email)) {
                $withoutEmail[] = (string) $customer->name;

                continue;
            }
            DB::transaction(function () use ($recall, $customer, $email, $items, $actor): void {
                $dispatch = DocumentDispatch::query()->create([
                    'organization_id' => $recall->organization_id,
                    'document_kind' => Recall::DOCUMENT_KIND,
                    'document_id' => $recall->id,
                    'channel' => DocumentDispatch::CHANNEL_EMAIL,
                    'status' => 'queued',
                    'recipient' => $email,
                    'meta' => ['customer_id' => $customer->id, 'item_ids' => $items->pluck('id')->all()],
                    'created_by' => $actor->id,
                ]);
                Mail::to($email)->queue(new RecallNoticeMail((int) $recall->id, (int) $customer->id, (int) $dispatch->id));
                foreach ($items as $item) {
                    $this->setItemStatus($item, RecallItemStatus::Notified, $actor);
                }
            });
            $sent++;
        }

        return ['sent' => $sent, 'without_email' => $withoutEmail];
    }

    /** Rücklauf über die Reklamation (MVP-922): Fall eröffnen und an der Position vermerken; die RMA läuft in der Akte. */
    public function openClaim(RecallItem $item, User $actor): ClaimCase {
        if ($item->claim_case_id !== null && $item->claimCase !== null) {
            return $item->claimCase;
        }
        $recall = $item->recall()->firstOrFail();
        $organization = Organization::query()->withoutGlobalScopes()->findOrFail($item->organization_id);
        $claim = $this->claims->open($organization, $actor, array_filter([
            'title' => (string) __('recall.claim.title', ['number' => (string) $recall->number, 'title' => $recall->title]),
            'description' => $recall->reason,
            'source' => ClaimSource::Internal->value,
            'priority' => 'high',
            'severity' => 'major',
            'customer_id' => $item->customer_id,
            'stock_serial_id' => $item->stock_serial_id,
            'serial_no' => $item->serial?->serial_no,
        ], static fn (mixed $v): bool => $v !== null));
        $item->forceFill(['claim_case_id' => $claim->id])->save();

        return $claim;
    }

    private function item(Recall $recall, StockDelivery $delivery, ?StockSerial $serial, ?string $quantity): RecallItem {
        return RecallItem::query()->firstOrCreate(
            ['recall_id' => $recall->id, 'stock_delivery_id' => $delivery->id, 'stock_serial_id' => $serial?->id],
            [
                'organization_id' => $recall->organization_id,
                'customer_id' => $serial->customer_id ?? $delivery->customer_id,
                'quantity' => $quantity,
                'status' => RecallItemStatus::Open->value,
            ],
        );
    }

    /** Sprachunabhängig, damit der Abbruch genau diese Sperren wiederfindet. */
    private function blockReason(Recall $recall): string {
        return (string) $recall->number;
    }
}
