<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningDossierPdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\{Organization, User};
use App\Services\DocumentDesign\DocumentDesignRenderer;
use Illuminate\Support\{Carbon, Collection};

/**
 * Nachweismappe als PDF (Feature 149, MVP-750).
 *
 * **Der Stichtag steht auf jedem Blatt**, und der Prüfhash entsteht aus
 * demselben Payload wie der JSON-Export — zwei Ausgaben zum selben Stichtag
 * tragen denselben Hash, sonst wäre „reproduzierbar" ein leeres Wort.
 */
class LearningDossierPdfRenderer {
    public function __construct(
        private readonly QualificationDossierService $dossier,
    ) {}

    /** @param  Collection<int, User>  $users */
    public function output(Organization $organization, Collection $users, Carbon $asOf, bool $named, string $reason = ''): string {
        $payload = $this->dossier->exportPayload($organization, $users, $asOf);

        return app(DocumentDesignRenderer::class)->renderPdf(
            RenderDocumentKind::Report,
            'learning.pdf.dossier',
            [
                'organization' => $organization,
                'asOf' => $asOf,
                'named' => $named,
                'reason' => $reason,
                'summary' => $this->dossier->coverageSummary($users, $asOf),
                // Namentlich nur, wenn ausdrücklich angefordert — sonst
                // stünde der Personenbezug ungefragt im Dokument.
                'rows' => $named ? $this->dossier->forUsers($users, $asOf) : [],
                'hash' => (string) $payload['hash'],
            ],
            $organization,
        );
    }
}
