<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BomResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\Manufacturing\{BomOverrideAction, QuantityKind};
use App\Models\{ArticleVariant, ArticleVariantBomOverride, ProcedureMaterialRequirement, ProcedureTemplateVersion};
use Illuminate\Support\Collection;

/**
 * Löst die effektive Stückliste auf (Feature 047, MVP-061): Basis-Positionen
 * der Arbeitsplan-Version plus die Overrides der konkreten Variante
 * (deaktivieren / Menge überschreiben / hinzufügen), referenziert über den
 * stabilen `position_code`. Ergebnis ist die einzufrierende Vollstückliste.
 *
 * @return Collection<int, ProcedureMaterialRequirement>
 */
class BomResolver {
    /** @return Collection<int, ProcedureMaterialRequirement> */
    public function resolve(ProcedureTemplateVersion $version, ?ArticleVariant $variant): Collection {
        /** @var Collection<int, ProcedureMaterialRequirement> $base */
        $base = ProcedureMaterialRequirement::query()
            ->where('procedure_template_version_id', $version->id)
            ->where('active', true)
            ->orderBy('position')
            ->get();

        if ($variant === null) {
            return $base;
        }

        /** @var Collection<int, ArticleVariantBomOverride> $overrides */
        $overrides = ArticleVariantBomOverride::query()
            ->where('article_variant_id', $variant->id)
            ->get();

        $disabled = $overrides->where('action', BomOverrideAction::Disable)
            ->pluck('position_code')->flip();
        $qtyByPos = $overrides->where('action', BomOverrideAction::OverrideQty)
            ->keyBy('position_code');

        /** @var Collection<int, ProcedureMaterialRequirement> $resolved */
        $resolved = new Collection();

        foreach ($base as $req) {
            if ($disabled->has($req->position_code)) {
                continue;
            }

            $override = $qtyByPos->get($req->position_code);
            if ($override instanceof ArticleVariantBomOverride) {
                $clone = $req->replicate();
                if ($override->quantity !== null) {
                    $clone->quantity = $override->quantity;
                }
                if ($override->quantity_kind !== null) {
                    $clone->quantity_kind = $override->quantity_kind;
                }
                if ($override->ratio_part !== null) {
                    $clone->ratio_part = $override->ratio_part;
                }
                $resolved->push($clone);

                continue;
            }

            $resolved->push($req);
        }

        foreach ($overrides->where('action', BomOverrideAction::Add) as $add) {
            $resolved->push(new ProcedureMaterialRequirement([
                'procedure_template_version_id' => $version->id,
                'position_code' => $add->position_code,
                'article_id' => $add->article_id,
                'quantity_kind' => ($add->quantity_kind ?? QuantityKind::PerUnit)->value,
                'quantity' => $add->quantity ?? '0',
                'ratio_part' => $add->ratio_part,
                'unit' => $add->unit ?? 'Stk',
                'rounding' => 'none',
                'waste_surcharge' => $add->waste_surcharge,
                'is_tool' => $add->is_tool,
                'active' => true,
            ]));
        }

        return $resolved;
    }
}
