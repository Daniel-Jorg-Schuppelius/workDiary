<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillVoucherLinks.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Easybill\Services;

use App\Enums\Billing\DocumentKind;
use App\Models\Finance\AccountingVoucher;
use App\Plugins\Easybill\EasybillPlugin;
use App\Services\Billing\Feed\MirroredVoucherLinks;
use App\Support\Sqid;
use Illuminate\Support\Facades\Route;

/**
 * Belegfluss und Akte: Original-PDF gespiegelter easybill-Dokumente
 * (MVP-1499) über `GET /documents/{id}/pdf` (Swagger-Fixture). Serienvorlagen
 * sind kein Beleg; eine Web-Adresse des Originals ist nicht belegt.
 */
final class EasybillVoucherLinks extends MirroredVoucherLinks {
    protected function pluginId(): string {
        return EasybillPlugin::ID;
    }

    protected function voucherUrl(AccountingVoucher $voucher): ?string {
        return null;
    }

    protected function pdfUrl(AccountingVoucher $voucher): ?string {
        return self::documentId($voucher) !== null && Route::has('finance.easybill.vouchers.pdf')
            ? route('finance.easybill.vouchers.pdf', Sqid::encode(AccountingVoucher::class, (int) $voucher->id))
            : null;
    }

    /** easybill-Dokument-ID eines gespiegelten Belegs, sonst null. */
    public static function documentId(AccountingVoucher $voucher): ?string {
        $id = trim((string) $voucher->external_id);
        if ($voucher->plugin_id !== EasybillPlugin::ID || $id === '' || $voucher->document_kind === DocumentKind::RecurringTemplate->value) {
            return null;
        }

        return $id;
    }
}
