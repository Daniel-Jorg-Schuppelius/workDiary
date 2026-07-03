<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PriceApprovalService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\{Article, PriceChangeRequest, SupplierCatalogItem, User};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Vier-Augen-Freigabeflow für Verkaufspreisübernahmen (Feature 050, MVP-095):
 * Beantragen friert den serverseitig berechneten Vorschlag als Snapshot ein;
 * genehmigen darf nur eine andere Person, und nur wenn der zur Entscheidung
 * neu berechnete Vorschlag noch dem beantragten entspricht — sonst verfällt
 * der Antrag (expired) statt still einen anderen Preis zu übernehmen.
 */
class PriceApprovalService {
    public function __construct(private readonly PriceSuggestionService $pricing) {}

    /**
     * Beantragt die Übernahme des aktuellen Vorschlags. Je Katalogartikel ist
     * höchstens ein offener Antrag zulässig (idempotent: bestehender offener
     * Antrag wird zurückgegeben).
     *
     * @throws RuntimeException Wenn der Artikel nicht verknüpft ist oder kein Vorschlag entsteht.
     */
    public function request(SupplierCatalogItem $item, User $requester): PriceChangeRequest {
        if ($item->article_id === null) {
            throw new RuntimeException((string) __('procurement.margin.error.not_linked'));
        }

        $suggestion = $this->pricing->suggestForItem($item);
        if ($suggestion === null) {
            throw new RuntimeException((string) __('procurement.margin.error.no_suggestion'));
        }

        return DB::transaction(function () use ($item, $requester, $suggestion): PriceChangeRequest {
            $open = PriceChangeRequest::query()
                ->where('supplier_catalog_item_id', $item->id)
                ->where('status', PriceChangeRequest::STATUS_REQUESTED)
                ->lockForUpdate()
                ->first();
            if ($open !== null) {
                return $open;
            }

            return PriceChangeRequest::query()->create([
                'organization_id' => $item->organization_id,
                'supplier_catalog_item_id' => $item->id,
                'article_id' => $item->article_id,
                'pricing_margin_rule_id' => $suggestion['rule']->id,
                'purchase_price_snapshot' => (string) $item->purchase_price,
                'suggested_price' => $suggestion['price'],
                'margin_snapshot' => (string) $suggestion['margin'],
                'status' => PriceChangeRequest::STATUS_REQUESTED,
                'requested_by' => $requester->id,
            ]);
        });
    }

    /**
     * Genehmigt einen offenen Antrag (Vier-Augen: nie durch den Antragsteller)
     * und übernimmt den Preis in den Artikelstamm. Der Vorschlag wird
     * serverseitig neu berechnet; weicht er vom beantragten Preis ab, verfällt
     * der Antrag.
     *
     * @throws RuntimeException Bei Selbstfreigabe, verfallenem oder nicht mehr berechenbarem Vorschlag.
     */
    public function approve(PriceChangeRequest $request, User $approver): PriceChangeRequest {
        $this->assertOpen($request);

        if ((int) $request->requested_by === (int) $approver->id) {
            throw new RuntimeException((string) __('procurement.approval.error.self_approval'));
        }

        $item = $request->item;
        $suggestion = $item instanceof SupplierCatalogItem ? $this->pricing->suggestForItem($item) : null;
        if ($suggestion === null || bccomp($this->numeric($suggestion['price']), $this->numeric((string) $request->suggested_price), 4) !== 0) {
            $request->forceFill([
                'status' => PriceChangeRequest::STATUS_EXPIRED,
                'decided_by' => $approver->id,
                'decided_at' => Carbon::now(),
                'decision_note' => (string) __('procurement.approval.error.stale'),
            ])->save();

            throw new RuntimeException((string) __('procurement.approval.error.stale'));
        }

        return DB::transaction(function () use ($request, $approver, $suggestion): PriceChangeRequest {
            $article = Article::query()->findOrFail($request->article_id);
            $article->default_sale_price = $suggestion['price'];
            $article->save();

            $request->forceFill([
                'status' => PriceChangeRequest::STATUS_APPROVED,
                'decided_by' => $approver->id,
                'decided_at' => Carbon::now(),
            ])->save();

            return $request;
        });
    }

    /** Lehnt einen offenen Antrag mit optionaler Begründung ab. */
    public function reject(PriceChangeRequest $request, User $approver, ?string $note = null): PriceChangeRequest {
        $this->assertOpen($request);

        if ((int) $request->requested_by === (int) $approver->id) {
            throw new RuntimeException((string) __('procurement.approval.error.self_approval'));
        }

        $request->forceFill([
            'status' => PriceChangeRequest::STATUS_REJECTED,
            'decided_by' => $approver->id,
            'decided_at' => Carbon::now(),
            'decision_note' => $note,
        ])->save();

        return $request;
    }

    private function assertOpen(PriceChangeRequest $request): void {
        if ($request->status !== PriceChangeRequest::STATUS_REQUESTED) {
            throw new RuntimeException((string) __('procurement.approval.error.not_open'));
        }
    }

    /** @return numeric-string */
    private function numeric(string $value): string {
        return is_numeric($value) ? $value : '0';
    }
}
