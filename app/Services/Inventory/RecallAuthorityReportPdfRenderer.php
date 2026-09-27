<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecallAuthorityReportPdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Enums\Inventory\RecallItemStatus;
use App\Models\Inventory\Recall;
use App\Models\Platform\Organization;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use CommonToolkit\Helper\FileSystem\File;

/**
 * Meldebogen eines Rückrufs für die Marktüberwachung (MVP-945): Produkt,
 * Gefahr, Risiko, Maßnahme, Länder, betroffene Mengen und Rücklauf. Ersetzt
 * nicht die Meldung im Portal der Behörde, sondern bündelt ihre Angaben.
 */
final class RecallAuthorityReportPdfRenderer {
    public function render(Recall $recall): string {
        $recall->loadMissing(['variant.article', 'items']);
        $organization = Organization::query()->withoutGlobalScopes()->find($recall->organization_id);

        return app(DocumentDesignRenderer::class)->renderPdf(
            RenderDocumentKind::Report,
            'pdf.recall-authority-report',
            [
                'recall' => $recall,
                'organization' => $organization,
                'stats' => [
                    'units' => (float) $recall->items->sum(static fn ($item): float => (float) $item->quantity),
                    'customers' => $recall->items->pluck('customer_id')->filter()->unique()->count(),
                    'returned' => $recall->items->whereIn('status', [RecallItemStatus::Returned, RecallItemStatus::Resolved])->count(),
                    'items' => $recall->items->count(),
                ],
            ],
            $organization,
        );
    }

    public function filename(Recall $recall): string {
        return File::sanitizeFilename('Rueckruf-Meldung_' . $recall->number . '.pdf', keepExtension: true);
    }
}
