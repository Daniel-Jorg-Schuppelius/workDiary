<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliveryNoteService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\{DeliveryNoteStatus, DocumentLineKind};
use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Enums\Numbering\NumberScope;
use App\Enums\Sales\OrderConfirmationStatus;
use App\Models\Billing\{DeliveryNote, DeliveryNoteItem};
use App\Models\Inventory\StockDelivery;
use App\Models\Platform\User;
use App\Models\Sales\{OrderConfirmation, OrderConfirmationItem};
use App\Services\DocumentDesign\DocumentDesignRenderer;
use App\Services\Numbering\{DocumentIssuers, NumberSequenceService};
use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Lieferschein (Phase 145, MVP-1163): aus der bestätigten AB als Entwurf
 * (Restmengen, Teillieferung über die Mengen), aus einer Lagerauslieferung der
 * Fertigung; Ausliefern legt die Nummer fest — vergibt ein Programm den Kreis,
 * überträgt es den Lieferschein dabei (MVP-1164).
 */
final class DeliveryNoteService {
    private const SCALE = 4;

    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly DocumentIssuers $issuers,
    ) {}

    /** Entwurf mit den noch nicht gelieferten Mengen der AB; ein offener Entwurf wird wiederverwendet. */
    public function fromOrderConfirmation(OrderConfirmation $confirmation, User $actor): DeliveryNote {
        if ($confirmation->status !== OrderConfirmationStatus::Confirmed) {
            throw new RuntimeException((string) __('billing.delivery_note.error.not_confirmed'));
        }
        $draft = DeliveryNote::query()->where('order_confirmation_id', $confirmation->id)
            ->where('status', DeliveryNoteStatus::Draft)->latest('id')->first();
        if ($draft instanceof DeliveryNote) {
            return $draft;
        }

        $confirmation->loadMissing('items');
        $delivered = $this->deliveredQuantities($confirmation);
        $lines = [];
        foreach ($confirmation->items as $item) {
            $kind = $item->lineKind();
            if ($kind === DocumentLineKind::Item) {
                $open = Decimal::of((string) $item->quantity, self::SCALE)->minus($delivered[$item->id] ?? Decimal::zero(self::SCALE));
                if (! $open->isPositive()) {
                    continue;
                }
                $lines[] = ['item' => $item, 'kind' => $kind, 'quantity' => $open->getValue()];
            } elseif ($kind === DocumentLineKind::Title || $kind === DocumentLineKind::Text) {
                $lines[] = ['item' => $item, 'kind' => $kind, 'quantity' => '0'];
            }
        }
        if (! array_any($lines, static fn (array $line): bool => $line['kind'] === DocumentLineKind::Item)) {
            throw new RuntimeException((string) __('billing.delivery_note.error.fully_delivered'));
        }

        return DB::transaction(function () use ($confirmation, $actor, $lines): DeliveryNote {
            $note = DeliveryNote::query()->create([
                'organization_id' => $confirmation->organization_id,
                'customer_id' => $confirmation->customer_id,
                'project_id' => $confirmation->project_id,
                'order_confirmation_id' => $confirmation->id,
                'number' => $this->numbers->next((int) $confirmation->organization_id, NumberScope::DeliveryNote, now()),
                'status' => DeliveryNoteStatus::Draft,
                'delivered_on' => now()->toDateString(),
                'created_by' => $actor->id,
            ]);
            $position = 0;
            foreach ($lines as $line) {
                /** @var OrderConfirmationItem $item */
                $item = $line['item'];
                $note->items()->create([
                    'organization_id' => $confirmation->organization_id,
                    'order_confirmation_item_id' => $item->id,
                    'article_id' => $item->article_id,
                    'position' => ++$position,
                    'line_kind' => $line['kind'],
                    'description' => (string) $item->description,
                    'quantity' => $line['quantity'],
                    'unit' => $item->unit,
                ]);
            }
            $note->audit('delivery_note.created', ['order_confirmation_id' => $confirmation->id, 'number' => $note->number]);

            return $note->refresh();
        });
    }

    /**
     * Lieferschein der Lagerauslieferung. Neue Auslieferungen bekommen einen
     * Entwurf, den {@see deliver()} festschreibt; Auslieferungen ohne Lieferschein
     * (vor Phase 145) erhalten ihn mit der bisherigen Nummer `LS-<id>`.
     */
    public function forStockDelivery(StockDelivery $delivery, ?int $createdBy = null, bool $issued = true): DeliveryNote {
        $existing = $delivery->deliveryNoteItem()->first()?->deliveryNote()->first();
        if ($existing instanceof DeliveryNote) {
            return $existing;
        }
        $delivery->loadMissing('order');

        return DB::transaction(function () use ($delivery, $createdBy, $issued): DeliveryNote {
            $organizationId = (int) $delivery->organization_id;
            $note = DeliveryNote::query()->create([
                'organization_id' => $organizationId,
                'customer_id' => $delivery->customer_id,
                'project_id' => $delivery->order?->project_id,
                'manufacturing_order_id' => $delivery->manufacturing_order_id,
                'number' => $issued
                    ? 'LS-' . str_pad((string) $delivery->id, 6, '0', STR_PAD_LEFT)
                    : $this->numbers->next($organizationId, NumberScope::DeliveryNote, now()),
                'status' => $issued ? DeliveryNoteStatus::Delivered : DeliveryNoteStatus::Draft,
                'delivered_on' => ($delivery->delivered_at ?? now())->toDateString(),
                'created_by' => $createdBy ?? $delivery->getAttribute('created_by'),
            ]);
            $note->items()->create([
                'organization_id' => $organizationId,
                'stock_delivery_id' => $delivery->id,
                'article_id' => $delivery->variant?->article_id,
                'position' => 1,
                'line_kind' => DocumentLineKind::Item,
                'sku' => $delivery->sku_snapshot,
                'description' => (string) ($delivery->name_snapshot ?? ''),
                'quantity' => $delivery->quantity?->getNumericValue() ?? '0',
                'unit' => $delivery->unit,
            ]);
            $note->audit('delivery_note.created', ['stock_delivery_id' => $delivery->id, 'number' => $note->number]);

            return $note->refresh();
        });
    }

    /**
     * Mengen, Lieferdatum und Hinweis des Entwurfs; Menge 0 streicht die Position.
     *
     * @param  array<int, string>  $quantities  Positions-ID => Menge
     */
    public function updateDraft(DeliveryNote $note, array $quantities, ?string $deliveredOn, ?string $remark): DeliveryNote {
        if ($note->status !== DeliveryNoteStatus::Draft || $note->hasStockDeliveries()) {
            throw new RuntimeException((string) __('billing.delivery_note.error.not_editable'));
        }

        return DB::transaction(function () use ($note, $quantities, $deliveredOn, $remark): DeliveryNote {
            foreach ($note->items as $item) {
                if ($item->line_kind !== DocumentLineKind::Item || ! array_key_exists($item->id, $quantities)) {
                    continue;
                }
                $quantity = Decimal::of($quantities[$item->id], self::SCALE);
                if ($quantity->isZero()) {
                    $item->delete();
                } else {
                    $item->update(['quantity' => $quantity->getValue()]);
                }
            }
            $note->update(['delivered_on' => $deliveredOn, 'note' => $remark]);

            return $note->refresh();
        });
    }

    /**
     * Ausliefern: Nummer festlegen (lokal oder durch das Programm), Layoutstand einfrieren.
     *
     * @throws RuntimeException wenn der Lieferschein kein Entwurf ist, leer ist oder das Programm ablehnt
     */
    public function deliver(DeliveryNote $note, ?User $actor): DeliveryNote {
        if (! $note->status->canTransitionTo(DeliveryNoteStatus::Delivered)) {
            throw new RuntimeException((string) __('billing.delivery_note.error.not_draft'));
        }
        $note->loadMissing('items');
        if (! $note->items->contains(fn (DeliveryNoteItem $item): bool => $item->line_kind === DocumentLineKind::Item)) {
            throw new RuntimeException((string) __('billing.delivery_note.error.empty'));
        }

        $number = $this->issuers->numberOnIssue($note, NumberScope::DeliveryNote);
        $note->update(['status' => DeliveryNoteStatus::Delivered, 'delivered_on' => $note->delivered_on ?? now()->toDateString()]
            + ($number !== null ? ['number' => $number] : []));
        $note->audit('delivery_note.delivered', ['by' => $actor?->id, 'number' => $note->number]);

        $organization = $note->organization;
        if ($organization !== null) {
            $renderer = app(DocumentDesignRenderer::class);
            $renderer->snapshot($note, RenderDocumentKind::DeliveryNote, $organization, $renderer->payloadFor($organization, RenderDocumentKind::DeliveryNote, $note->customer_id), $actor);
        }

        return $note->refresh();
    }

    /** Storno; ein Lieferschein mit Lagerabgang wird über die Auslieferung korrigiert, nicht hier. */
    public function cancel(DeliveryNote $note, User $actor): DeliveryNote {
        if (! $note->status->canTransitionTo(DeliveryNoteStatus::Cancelled) || $note->hasStockDeliveries()) {
            throw new RuntimeException((string) __('billing.delivery_note.error.not_cancellable'));
        }
        $note->update(['status' => DeliveryNoteStatus::Cancelled]);
        $note->audit('delivery_note.cancelled', ['by' => $actor->id]);

        return $note->refresh();
    }

    /** @return array<int, Decimal> AB-Position => gelieferte Menge (ohne Stornos und Entwürfe) */
    private function deliveredQuantities(OrderConfirmation $confirmation): array {
        $rows = DeliveryNoteItem::query()
            ->whereIn('order_confirmation_item_id', $confirmation->items->pluck('id'))
            ->whereHas('deliveryNote', fn ($query) => $query->where('status', DeliveryNoteStatus::Delivered))
            ->get(['order_confirmation_item_id', 'quantity']);

        $delivered = [];
        foreach ($rows as $row) {
            $id = (int) $row->order_confirmation_item_id;
            $delivered[$id] = ($delivered[$id] ?? Decimal::zero(self::SCALE))->plus(Decimal::of((string) $row->quantity, self::SCALE));
        }

        return $delivered;
    }
}
