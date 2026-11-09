<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskVoucherPdfController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk\Http\Controllers;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Finance\AccountingVoucher;
use App\Plugins\SevDesk\Api\SevDeskClientFactory;
use App\Plugins\SevDesk\Exceptions\SevDeskApiException;
use App\Plugins\SevDesk\Services\SevDeskVoucherLinks;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/** Original-PDF einer gespiegelten sevDesk-Rechnung oder -Gutschrift (MVP-1499, MVP-1504); der Spiegel ist organisationsgescopt. */
final class SevDeskVoucherPdfController extends Controller {
    public function __invoke(AccountingVoucher $voucher, SevDeskClientFactory $clients): Response {
        Gate::authorize(Permission::VoucherViewAny->value);
        $source = SevDeskVoucherLinks::pdfSource($voucher);
        $organizationId = (int) $voucher->organization_id;
        abort_if($source === null || ! $clients->isConfigured($organizationId), 404);
        [$resource, $documentId] = $source;

        try {
            $client = $clients->for($organizationId);
            $pdf = $resource === 'CreditNote' ? $client->creditNotePdf($documentId) : $client->invoicePdf($documentId);
        } catch (SevDeskApiException) {
            abort(502, (string) __('billing.feed.original_pdf_failed', ['program' => 'sevDesk']));
        }
        abort_if($pdf === null, 404, (string) __('billing.feed.original_pdf_missing', ['program' => 'sevDesk']));

        return response($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="sevdesk-' . Str::slug((string) ($voucher->voucher_number ?: $documentId)) . '.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
