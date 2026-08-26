<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConstructionNoticePdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Construction;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Construction\ConstructionNotice;
use App\Models\Organization;
use App\Services\DocumentDesign\DocumentDesignRenderer;

/**
 * PDF der VOB/B-Schreiben (H23, MVP-728) auf dem Firmenbogen. Belegart ist die
 * Art des Schreibens selbst — Behinderungsanzeige oder Bedenkenanmeldung —,
 * damit Organisationen beiden ein eigenes Design geben koennen; ohne eigenes
 * Profil greift der Fallback auf `report`.
 *
 * Ein versendetes Schreiben rendert mit dem eingefrorenen Designstand
 * (Snapshot), damit spaetere Designwechsel den Zugangsnachweis nicht
 * nachtraeglich veraendern.
 */
class ConstructionNoticePdfRenderer {
    public function render(ConstructionNotice $notice): string {
        $notice->loadMissing(['customer', 'project', 'site', 'diaryEntry', 'weatherSnapshot', 'creator']);

        $organization = Organization::query()->withoutGlobalScopes()->find($notice->organization_id);
        $design = app(DocumentDesignRenderer::class);

        return $design->renderPdf(
            $notice->kind,
            'pdf.construction-notice',
            [
                'notice' => $notice,
                'organization' => $organization,
                'generatedAt' => now(),
            ],
            $organization,
            payload: $design->payloadFromSnapshot($notice, $notice->kind),
        );
    }

    public function filename(ConstructionNotice $notice): string {
        $label = match ($notice->kind) {
            RenderDocumentKind::ConstructionConcernNotice => 'Bedenkenanmeldung',
            RenderDocumentKind::ConstructionObstructionNotice => 'Behinderungsanzeige',
            default => 'Schreiben',
        };

        return $label . '_' . (string) preg_replace('/[^A-Za-z0-9._-]/', '_', $notice->displayNo());
    }
}
