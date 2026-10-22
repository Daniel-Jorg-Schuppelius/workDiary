<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderConfirmationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\Billing\DocumentLineKind;
use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Enums\Numbering\NumberScope;
use App\Enums\Sales\OrderConfirmationStatus;
use App\Models\Platform\User;
use App\Models\Sales\{OrderConfirmation, Quote};
use App\Services\DocumentDesign\DocumentDesignRenderer;
use App\Services\Numbering\{DocumentIssuers, NumberSequenceService};
use Illuminate\Support\Facades\{Cache, DB};
use RuntimeException;

/**
 * Auftragsbestätigung (Phase 145, MVP-1162): aus den angenommenen Positionen
 * eines Angebots anlegen, bestätigen und stornieren. Beim Bestätigen steht die
 * Nummer fest — vergibt ein Buchhaltungsprogramm den Kreis, überträgt es die
 * AB dabei (MVP-1164).
 */
final class OrderConfirmationService {
    public function __construct(
        private readonly NumberSequenceService $numbers,
        private readonly DocumentIssuers $issuers,
    ) {}

    /** Legt die AB zum Angebot an; eine nicht stornierte AB wird wiederverwendet. */
    public function fromQuote(Quote $quote, User $actor): OrderConfirmation {
        if (! $quote->status->isWon()) {
            throw new RuntimeException((string) __('quotes.order_confirmation.error.not_won'));
        }
        $existing = OrderConfirmation::query()->where('quote_id', $quote->id)
            ->where('status', '!=', OrderConfirmationStatus::Cancelled)->latest('id')->first();
        if ($existing instanceof OrderConfirmation) {
            return $existing;
        }

        return DB::transaction(function () use ($quote, $actor): OrderConfirmation {
            $confirmation = OrderConfirmation::query()->create([
                'organization_id' => $quote->organization_id,
                'customer_id' => $quote->customer_id,
                'project_id' => $quote->project_id,
                'quote_id' => $quote->id,
                'number' => $this->numbers->next((int) $quote->organization_id, NumberScope::OrderConfirmation, now()),
                'status' => OrderConfirmationStatus::Draft,
                'currency' => $quote->documentCurrency(),
                'created_by' => $actor->id,
            ]);

            $position = 0;
            foreach ((array) ($quote->decision_snapshot['items'] ?? []) as $item) {
                if (! ($item['accepted'] ?? false)) {
                    continue;
                }
                $kind = DocumentLineKind::tryFrom((string) ($item['line_kind'] ?? '')) ?? DocumentLineKind::Item;
                $confirmation->items()->create([
                    'organization_id' => $quote->organization_id,
                    'article_id' => isset($item['article_id']) ? (int) $item['article_id'] : null,
                    'position' => ++$position,
                    // Eine gewählte Alternative ist in der AB eine gewöhnliche Position.
                    'line_kind' => $kind === DocumentLineKind::Alternative ? DocumentLineKind::Item : $kind,
                    'description' => (string) $item['description'],
                    'quantity' => (string) $item['quantity'],
                    'unit' => $item['unit'] ?? null,
                    'unit_price' => (string) $item['unit_price'],
                    'discount_percent' => $item['discount_percent'] ?? null,
                    'discount_amount' => $item['discount_amount'] ?? null,
                    'tax_rate' => $item['tax_rate'] ?? null,
                ]);
            }

            $confirmation->load('items');
            $confirmation->recalculate();
            $confirmation->save();
            $confirmation->audit('order_confirmation.created', ['quote_id' => $quote->id, 'number' => $confirmation->number]);

            return $confirmation->refresh();
        });
    }

    /**
     * Bestätigen: Nummer festlegen (lokal oder durch das Programm), Datum und
     * Layoutstand einfrieren.
     *
     * @throws RuntimeException wenn die AB nicht im Entwurf ist oder das Programm ablehnt
     */
    public function confirm(OrderConfirmation $confirmation, User $actor): OrderConfirmation {
        // Sperre nehmen, unter der Sperre neu prüfen (Sicherheitsaudit 2026-10-21, li-1): ein zweiter Aufruf
        // wartet und sieht den geschriebenen Stand. Bewusst keine DB-Transaktion — vergibt ein Programm die
        // Nummer, muss die Verknüpfung zum dort angelegten Beleg einen Fehlschlag überleben.
        return Cache::lock('document-issue:' . $confirmation->getMorphClass() . ':' . $confirmation->getKey(), 120)
            ->block(30, function () use ($confirmation, $actor): OrderConfirmation {
                $confirmation->refresh();

                return $this->confirmLocked($confirmation, $actor);
            });
    }

    private function confirmLocked(OrderConfirmation $confirmation, User $actor): OrderConfirmation {
        if (! $confirmation->status->canTransitionTo(OrderConfirmationStatus::Confirmed)) {
            throw new RuntimeException((string) __('quotes.order_confirmation.error.not_draft'));
        }
        $confirmation->loadMissing('items');
        if (! $confirmation->items->contains(fn ($item): bool => $item->lineKind()->isPriced())) {
            throw new RuntimeException((string) __('quotes.order_confirmation.error.empty'));
        }

        $number = $this->issuers->numberOnIssue($confirmation, NumberScope::OrderConfirmation);
        $confirmation->update(['status' => OrderConfirmationStatus::Confirmed, 'confirmed_on' => now()->toDateString()]
            + ($number !== null ? ['number' => $number] : []));
        $confirmation->audit('order_confirmation.confirmed', ['by' => $actor->id, 'number' => $confirmation->number]);

        $organization = $confirmation->organization;
        if ($organization !== null) {
            $renderer = app(DocumentDesignRenderer::class);
            $renderer->snapshot($confirmation, RenderDocumentKind::OrderConfirmation, $organization, $renderer->payloadFor($organization, RenderDocumentKind::OrderConfirmation, (int) $confirmation->customer_id), $actor);
        }

        return $confirmation->refresh();
    }

    public function cancel(OrderConfirmation $confirmation, User $actor): OrderConfirmation {
        if (! $confirmation->status->canTransitionTo(OrderConfirmationStatus::Cancelled)) {
            throw new RuntimeException((string) __('quotes.order_confirmation.error.not_cancellable'));
        }
        $confirmation->update(['status' => OrderConfirmationStatus::Cancelled]);
        $confirmation->audit('order_confirmation.cancelled', ['by' => $actor->id]);

        return $confirmation->refresh();
    }
}
