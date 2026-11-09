<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskVoucherLinks.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk\Services;

use App\Models\Finance\AccountingVoucher;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Services\Billing\Feed\MirroredVoucherLinks;
use App\Support\Sqid;
use Illuminate\Support\Facades\Route;

/**
 * Belegfluss und Akte: Original-PDF gespiegelter sevDesk-Rechnungen und
 * -Gutschriften (MVP-1499, MVP-1504) über `GET /Invoice/{id}/getPdf` bzw.
 * `GET /CreditNote/{id}/getPdf`. Belege aus `/Voucher` haben keinen belegten
 * PDF-Abruf, eine Web-Adresse des Originals ist nicht belegt.
 */
final class SevDeskVoucherLinks extends MirroredVoucherLinks {
    protected function pluginId(): string {
        return SevDeskPlugin::ID;
    }

    protected function voucherUrl(AccountingVoucher $voucher): ?string {
        return null;
    }

    protected function pdfUrl(AccountingVoucher $voucher): ?string {
        return self::pdfSource($voucher) !== null && Route::has('finance.sevdesk.vouchers.pdf')
            ? route('finance.sevdesk.vouchers.pdf', Sqid::encode(AccountingVoucher::class, (int) $voucher->id))
            : null;
    }

    /**
     * sevDesk-Ressource und ID eines gespiegelten Belegs mit PDF-Abruf, sonst null.
     *
     * @return array{0: 'Invoice'|'CreditNote', 1: string}|null
     */
    public static function pdfSource(AccountingVoucher $voucher): ?array {
        if ($voucher->plugin_id !== SevDeskPlugin::ID) {
            return null;
        }
        $externalId = (string) $voucher->external_id;
        foreach (['Invoice' => SevDeskVoucherPullService::INVOICE_PREFIX, 'CreditNote' => SevDeskVoucherPullService::CREDIT_NOTE_PREFIX] as $resource => $prefix) {
            if (str_starts_with($externalId, $prefix) && strlen($externalId) > strlen($prefix)) {
                return [$resource, substr($externalId, strlen($prefix))];
            }
        }

        return null;
    }
}
