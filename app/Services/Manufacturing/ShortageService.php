<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShortageService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\Manufacturing\{ProcurementStatus, SubstituteStatus};
use App\Models\{Article, ArticleVariant, ManufacturingOrderMaterial, MaterialSubstitute, ProcurementRequest, Warehouse};
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Fehlmaterialprozess (Feature 048): strukturierte, auditierbare Reaktionen auf
 * Unterdeckung – Ersatzmaterial beantragen/genehmigen (verändert NICHT die
 * Stückliste) und Beschaffungsbedarf als offenen Punkt erzeugen.
 */
class ShortageService {
    /** Beantragt Ersatzmaterial für eine Materialposition. */
    public function requestSubstitute(
        ManufacturingOrderMaterial $material,
        Article $substituteArticle,
        ?ArticleVariant $substituteVariant,
        string $qty,
        string $reason,
        ?int $requestedBy = null,
    ): MaterialSubstitute {
        $order = $material->order;
        if ($order === null) {
            throw new RuntimeException('Materialposition ohne Fertigungsauftrag.');
        }

        return MaterialSubstitute::query()->create([
            'organization_id' => $order->organization_id,
            'manufacturing_order_id' => $order->id,
            'manufacturing_order_material_id' => $material->id,
            'planned_article_id' => $material->article_id,
            'planned_variant_id' => $material->article_variant_id,
            'substitute_article_id' => $substituteArticle->id,
            'substitute_variant_id' => $substituteVariant?->id,
            'quantity' => $this->positive($qty),
            'status' => SubstituteStatus::Requested->value,
            'reason' => $reason,
            'requested_by' => $requestedBy,
        ]);
    }

    public function approveSubstitute(MaterialSubstitute $substitute, ?int $approvedBy = null): MaterialSubstitute {
        return $this->decide($substitute, SubstituteStatus::Approved, $approvedBy);
    }

    public function rejectSubstitute(MaterialSubstitute $substitute, ?int $approvedBy = null): MaterialSubstitute {
        return $this->decide($substitute, SubstituteStatus::Rejected, $approvedBy);
    }

    /** Erzeugt einen offenen Beschaffungsbedarf aus einer Fehlmenge. */
    public function createProcurementRequest(
        Article $article,
        ?ArticleVariant $variant,
        string $qty,
        ?Warehouse $warehouse = null,
        ?Model $source = null,
        ?int $createdBy = null,
        ?string $note = null,
    ): ProcurementRequest {
        return ProcurementRequest::query()->create([
            'organization_id' => $article->organization_id,
            'article_id' => $article->id,
            'article_variant_id' => $variant?->id,
            'warehouse_id' => $warehouse?->id,
            'quantity' => $this->positive($qty),
            'status' => ProcurementStatus::Open->value,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'note' => $note,
            'created_by' => $createdBy,
        ]);
    }

    private function decide(MaterialSubstitute $substitute, SubstituteStatus $status, ?int $approvedBy): MaterialSubstitute {
        if ($substitute->status !== SubstituteStatus::Requested) {
            throw new RuntimeException('Ersatzmaterial ist bereits entschieden.');
        }

        $substitute->forceFill([
            'status' => $status,
            'approved_by' => $approvedBy,
            'decided_at' => Carbon::now(),
        ])->save();

        return $substitute;
    }

    /** @return numeric-string */
    private function positive(string $value): string {
        $value = NumberHelper::normalizeDecimalString($value);
        if ($value === '' || ! is_numeric($value)) {
            return '0';
        }

        return bccomp($value, '0', 4) < 0 ? bcmul($value, '-1', 4) : $value;
    }
}
