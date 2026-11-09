<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillVoucherPdfController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Easybill\Http\Controllers;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Finance\AccountingVoucher;
use App\Plugins\Easybill\Api\EasybillClientFactory;
use App\Plugins\Easybill\Exceptions\EasybillApiException;
use App\Plugins\Easybill\Services\EasybillVoucherLinks;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/** Original-PDF eines gespiegelten easybill-Dokuments (MVP-1499); der Spiegel ist organisationsgescopt. */
final class EasybillVoucherPdfController extends Controller {
    public function __invoke(AccountingVoucher $voucher, EasybillClientFactory $clients): Response {
        Gate::authorize(Permission::VoucherViewAny->value);
        $documentId = EasybillVoucherLinks::documentId($voucher);
        $organizationId = (int) $voucher->organization_id;
        abort_if($documentId === null || ! $clients->isConfigured($organizationId), 404);

        try {
            $pdf = $clients->for($organizationId)->downloadPdf($documentId);
        } catch (EasybillApiException) {
            abort(502, (string) __('billing.feed.original_pdf_failed', ['program' => 'easybill']));
        }
        abort_if($pdf === null, 404, (string) __('billing.feed.original_pdf_missing', ['program' => 'easybill']));

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="easybill-' . Str::slug((string) ($voucher->voucher_number ?: $documentId)) . '.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
