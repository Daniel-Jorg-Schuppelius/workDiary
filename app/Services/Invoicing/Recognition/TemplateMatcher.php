<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TemplateMatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Recognition;

use App\Models\Invoicing\IncomingInvoiceTemplate;
use CommonToolkit\Helper\Data\VatNumberHelper;
use PDFToolkit\Entities\WordBox;

/**
 * Passende Belegvorlage (Phase 151, L3): zuerst über Lieferant oder USt-IdNr.
 * des Verkäufers, sonst über die Ähnlichkeit des Fingerabdrucks; unter der
 * Schwelle gewinnt niemand.
 */
final class TemplateMatcher {
    /**
     * @param  list<WordBox>  $boxes
     * @return array{template: IncomingInvoiceTemplate, similarity: float}|null
     */
    public function match(int $organizationId, array $boxes, ?string $sellerVat = null, ?int $supplierId = null): ?array {
        $templates = IncomingInvoiceTemplate::query()->withoutGlobalScopes()->with('supplier:id,vat_id')
            ->where('organization_id', $organizationId)->get();
        if ($templates->isEmpty()) {
            return null;
        }
        $fingerprint = TemplateFingerprint::fromBoxes($boxes);
        $vat = VatNumberHelper::normalize((string) $sellerVat);
        $best = null;
        foreach ($templates as $template) {
            $similarity = TemplateFingerprint::similarity($fingerprint, (array) $template->fingerprint);
            if ($supplierId !== null && (int) $template->supplier_id === $supplierId) {
                $similarity = max($similarity, 0.8);
            } elseif ($vat !== '' && $template->supplier !== null && VatNumberHelper::normalize((string) $template->supplier->vat_id) === $vat) {
                $similarity = max($similarity, 0.8);
            }
            if ($similarity >= TemplateFingerprint::MIN_SIMILARITY && ($best === null || $similarity > $best['similarity'])) {
                $best = ['template' => $template, 'similarity' => $similarity];
            }
        }

        return $best;
    }
}
