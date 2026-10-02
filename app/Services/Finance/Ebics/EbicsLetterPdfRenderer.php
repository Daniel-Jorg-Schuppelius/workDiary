<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EbicsLetterPdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Ebics;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Finance\EbicsConnection;
use App\Models\Platform\{Organization, User};
use App\Services\DocumentDesign\DocumentDesignRenderer;

/** Initialisierungsbrief INI/HIA als PDF (MVP-124) — unterschrieben an die Bank. */
final class EbicsLetterPdfRenderer {
    public function __construct(private readonly EbicsConnectionService $connections) {}

    public function render(EbicsConnection $connection, User $actor): string {
        $connection->loadMissing('bankAccount');
        $organization = Organization::query()->withoutGlobalScopes()->findOrFail($connection->organization_id);

        return app(DocumentDesignRenderer::class)->renderPdf(
            RenderDocumentKind::Report,
            'pdf.ebics-letter',
            ['connection' => $connection, 'organization' => $organization, 'keys' => $this->connections->letterKeys($connection, $actor), 'printedAt' => now()],
            $organization,
        );
    }
}
