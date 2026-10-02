<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EfbPriceSheetPdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\Gaeb\BillOfQuantity;
use App\Models\Platform\Organization;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use CommonToolkit\Helper\FileSystem\File;
use InvalidArgumentException;

/** EFB-Preisblätter 221 und 223 eines LV als PDF (MVP-1056). */
final class EfbPriceSheetPdfRenderer {
    public const FORMS = ['221', '223'];

    public function __construct(private readonly EfbPriceSheetService $sheets) {}

    public function render(BillOfQuantity $bill, string $form): string {
        if (! in_array($form, self::FORMS, true)) {
            throw new InvalidArgumentException('Unknown EFB form ' . $form);
        }
        $organization = Organization::query()->withoutGlobalScopes()->findOrFail($bill->organization_id);
        $form221 = $this->sheets->form221($organization);
        $data = ['bill' => $bill, 'organization' => $organization, 'form221' => $form221];
        if ($form === '223') {
            $data['form223'] = $this->sheets->form223($bill, $form221['billingWage']);
        }

        return app(DocumentDesignRenderer::class)->renderPdf(RenderDocumentKind::Report, 'pdf.efb-' . $form, $data, $organization);
    }

    public function filename(BillOfQuantity $bill, string $form): string {
        return File::sanitizeFilename('EFB-' . $form . '_' . $bill->name . '.pdf', keepExtension: true);
    }
}
