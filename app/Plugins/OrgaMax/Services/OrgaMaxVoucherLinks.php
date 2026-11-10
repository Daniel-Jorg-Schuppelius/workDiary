<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxVoucherLinks.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Services;

use App\Models\Finance\AccountingVoucher;
use App\Models\Platform\User;
use App\Plugins\OrgaMax\Models\OrgaMaxInvoice;
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Services\Billing\Feed\MirroredVoucherLinks;
use App\Support\Sqid;
use Illuminate\Support\Facades\Route;

/**
 * Belegfluss und Akte: Original-PDF gespiegelter orgaMAX-Rechnungen
 * (MVP-670, MVP-1501) über die Admin-Route des Rechnungsspiegels
 * (`GET /invoice/document/{id}`). Ausgaben haben keinen belegten PDF-Abruf,
 * eine Web-Adresse des Originals ist nicht belegt.
 */
final class OrgaMaxVoucherLinks extends MirroredVoucherLinks {
    protected function pluginId(): string {
        return OrgaMaxPlugin::ID;
    }

    protected function voucherUrl(AccountingVoucher $voucher): ?string {
        return null;
    }

    protected function pdfUrl(AccountingVoucher $voucher): ?string {
        $externalId = (string) $voucher->external_id;
        if ($externalId === '' || str_starts_with($externalId, OrgaMaxVoucherPullService::EXPENSE_PREFIX) || ! Route::has('admin.orgamax.invoices.mirror-pdf')) {
            return null;
        }
        $invoiceId = OrgaMaxInvoice::query()->withoutGlobalScopes()
            ->where('organization_id', $voucher->organization_id)
            ->where('external_id', $externalId)
            ->value('id');

        return $invoiceId !== null ? route('admin.orgamax.invoices.mirror-pdf', Sqid::encode(OrgaMaxInvoice::class, (int) $invoiceId)) : null;
    }

    /** Die PDF-Route lässt dieselben Personen zu wie die Faktura-Aktionen (MVP-1527). */
    public function originalPdfUrl(AccountingVoucher $voucher, User $user): ?string {
        return OrgaMaxPlugin::mayUseFactura($user) ? parent::originalPdfUrl($voucher, $user) : null;
    }
}
