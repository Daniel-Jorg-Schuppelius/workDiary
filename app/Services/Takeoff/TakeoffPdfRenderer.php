<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TakeoffPdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Takeoff;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Platform\Organization;
use App\Models\Takeoff\Takeoff;
use App\Services\DocumentDesign\{DocumentDesignRenderer, IssuerIdentity};
use CommonToolkit\Helper\FileSystem\File;

/** Aufmaßblatt als PDF (MVP-1058) — Nachweis zur Rechnung. */
final class TakeoffPdfRenderer {
    public function __construct(private readonly TakeoffService $takeoffs) {}

    public function render(Takeoff $takeoff): string {
        $takeoff->loadMissing(['lines.boqItem', 'lines.article', 'diaryEntry', 'project', 'billOfQuantity']);
        $organization = Organization::query()->withoutGlobalScopes()->findOrFail($takeoff->organization_id);

        $design = app(DocumentDesignRenderer::class);
        $payload = $design->payloadFor($organization, RenderDocumentKind::Protocol);

        return $design->renderPdf(
            RenderDocumentKind::Protocol,
            'pdf.takeoff',
            [
                'takeoff' => $takeoff,
                'organization' => $organization,
                'totals' => $this->takeoffs->totals($takeoff),
                // Firmenangaben nach Blockzustand, wie der Preflight sie prüft (MVP-1624).
                'design' => $design->context($payload),
                'issuer' => IssuerIdentity::for($organization),
            ],
            $organization,
            payload: $payload,
        );
    }

    public function filename(Takeoff $takeoff): string {
        return File::sanitizeFilename('Aufmass_' . $takeoff->title . '.pdf', keepExtension: true);
    }
}
