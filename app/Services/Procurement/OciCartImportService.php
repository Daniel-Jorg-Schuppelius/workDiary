<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OciCartImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\{Article, ArticleSupply, Organization, PurchaseOrder, Supplier, Warehouse};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Support\Facades\DB;

/**
 * Übernimmt einen OCI-/IDS-Shop-Warenkorb als Bestellentwurf (Feature 050,
 * MVP-096). Jede Warenkorbzeile wird über die Lieferanten-Artikelnummer
 * (`NEW_ITEM-VENDORMAT`) gegen die Bezugsquellen ({@see ArticleSupply}) des
 * Lieferanten aufgelöst; nur eindeutig zugeordnete Zeilen werden zu
 * Bestellpositionen. Preise und Artikel werden nicht ungeprüft überschrieben —
 * unzuordenbare Zeilen werden gezählt und gemeldet, nie still verworfen.
 */
class OciCartImportService {
    public function __construct(private readonly PurchaseOrderService $orders) {}

    /**
     * @param  list<array{vendormat: ?string, description: ?string, quantity: ?string, price: ?string}>  $cartLines
     * @return array{order: PurchaseOrder, matched: int, unmatched: int, unmatched_items: list<string>}
     */
    public function import(Organization $organization, Supplier $supplier, Warehouse $warehouse, array $cartLines, ?int $createdBy = null): array {
        return DB::transaction(function () use ($organization, $supplier, $warehouse, $cartLines, $createdBy): array {
            $order = $this->orders->createDraft($organization, $supplier, $warehouse, [
                'note' => __('procurement.oci.note'),
                'created_by' => $createdBy,
            ]);

            $matched = 0;
            $unmatched = [];

            foreach ($cartLines as $line) {
                $vendormat = trim((string) ($line['vendormat'] ?? ''));
                $qty = $this->positive((string) ($line['quantity'] ?? '1'));
                $article = $vendormat !== '' ? $this->resolveArticle($organization, $supplier, $vendormat) : null;

                if ($article === null) {
                    $unmatched[] = trim((string) ($line['description'] ?? $vendormat)) ?: $vendormat;

                    continue;
                }

                $price = trim((string) ($line['price'] ?? ''));
                $this->orders->addLine($order, $article, $qty, [
                    'unit_price' => $price !== '' && is_numeric($price) ? $price : null,
                ]);
                $matched++;
            }

            return [
                'order' => $order,
                'matched' => $matched,
                'unmatched' => count($unmatched),
                'unmatched_items' => $unmatched,
            ];
        });
    }

    /** Löst die Lieferanten-Artikelnummer über die Bezugsquelle zu einem internen Artikel auf. */
    private function resolveArticle(Organization $organization, Supplier $supplier, string $vendormat): ?Article {
        $supply = ArticleSupply::query()
            ->where('organization_id', $organization->id)
            ->where('supplier_id', $supplier->id)
            ->where('supplier_sku', $vendormat)
            ->first();

        if (! $supply instanceof ArticleSupply) {
            return null;
        }

        $article = Article::query()->find($supply->article_id);

        return $article instanceof Article ? $article : null;
    }

    /** @return numeric-string */
    private function positive(string $value): string {
        // Leer-/is_numeric-Prüfung entfällt: normalizeDecimalString() setzt
        // numeric-string durch ('0' als Fallback). Die Positiv-Prüfung bleibt
        // fachlich — Mengen <= 0 werden auf 1 gehoben.
        $value = NumberHelper::normalizeDecimalString($value);
        if ((float) $value <= 0) {
            return '1';
        }

        return $value;
    }
}
