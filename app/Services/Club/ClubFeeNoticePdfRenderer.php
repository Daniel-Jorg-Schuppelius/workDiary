<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeNoticePdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Club\{ClubFeeClaim, ClubFeeDunning};
use App\Services\DocumentDesign\DocumentDesignRenderer;
use App\Services\UI\BrandingService;

/** Beitragsmitteilung als PDF (MVP-850) über den Dokument-Renderer; Bankblock und Verwendungszweck wie beim Beleg. */
class ClubFeeNoticePdfRenderer {
    public function __construct(private readonly DocumentDesignRenderer $design) {}

    /** Mit Mahnstufe (MVP-851) wird dieselbe Mitteilung als Zahlungserinnerung/Mahnung ausgegeben. */
    public function output(ClubFeeClaim $claim, ?ClubFeeDunning $dunning = null): string {
        $claim->loadMissing(['items.member', 'account', 'customer', 'organization', 'correctedClaim']);
        $organization = $claim->organization;

        return \App\Support\DocumentLocale::within($claim->customer, $organization, fn(): string => $this->design->renderPdf(
            RenderDocumentKind::FeeNotice,
            'club.pdf.fee_notice',
            [
                'claim' => $claim,
                'dunning' => $dunning,
                'orgLegal' => app(BrandingService::class)->legalFor($organization),
                'footerText' => (string) data_get($organization?->settings, 'club.fees.notice_footer', ''),
            ],
            $organization,
        ));
    }

    public function filename(ClubFeeClaim $claim, ?ClubFeeDunning $dunning = null): string {
        $prefix = $dunning !== null ? 'mahnung-' . $dunning->level . '-' : 'beitragsmitteilung-';

        return $prefix . str_replace(['/', '\\', ' '], '-', $claim->number) . '.pdf';
    }
}
