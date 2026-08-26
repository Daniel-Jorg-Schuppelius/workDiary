<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PickListPdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Organization;
use App\Services\DocumentDesign\DocumentDesignRenderer;

/**
 * Rendert eine Kommissionierliste als PDF (Feature 048, MVP-706) über die
 * Design-Pipeline (Dokumentart „report": interner Arbeitsbeleg, kein
 * Faktura-Dokument). Registriert in {@see \App\Services\DocumentDesign\PdfGeneratorInventory}.
 */
class PickListPdfRenderer {
    public function render(PickList $list, ?Organization $organization): string {
        return app(DocumentDesignRenderer::class)->renderPdf(
            RenderDocumentKind::Report,
            'pdf.pick-list',
            [
                'list' => $list,
                'organization' => $organization,
                'number' => $list->number(),
            ],
            $organization,
        );
    }
}
