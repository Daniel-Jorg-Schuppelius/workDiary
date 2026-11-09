<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirroredVoucherLinks.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Feed;

use App\Enums\User\Permission;
use App\Models\Finance\AccountingVoucher;
use App\Models\Platform\User;
use App\Services\Billing\DocumentFeedFilters;
use App\Support\Ui\UiAction;
use CommonToolkit\Helper\Data\WebLinkHelper;
use Illuminate\Database\Query\Builder;

/**
 * Link vom gespiegelten Beleg (`accounting_vouchers`) zum Original im
 * Fremdsystem (Feature 176) und, wo die API es liefert, das Original-PDF
 * (Feature 105, MVP-1499). Ein Plugin meldet die Unterklasse als Quelle an;
 * sie liefert keine eigenen Zeilen, sie stellt nur die Belegspiegel-Zeilen
 * ihrer Plugin-ID dar. Belegliste der Akte und Einkaufsbelege nutzen dieselben
 * Aktionen über {@see actionsFor()}. Verlinkt wird nur http(s).
 */
abstract class MirroredVoucherLinks implements DocumentFeedSource, PresentsFeedRows {
    /** Plugin-ID, unter der die Belege gespiegelt sind (= Herkunft der Zeile). */
    abstract protected function pluginId(): string;

    abstract protected function voucherUrl(AccountingVoucher $voucher): ?string;

    /** Route, die das Original-PDF über den Client des Plugins ausliefert; null = keines. */
    protected function pdfUrl(AccountingVoucher $voucher): ?string {
        return null;
    }

    public function key(): string {
        return $this->pluginId() . '_voucher_links';
    }

    public function handles(string $pluginId): bool {
        return $pluginId === $this->pluginId();
    }

    public function builder(DocumentFeedFilters $filters): ?Builder {
        return null;
    }

    public function presents(\stdClass $row): bool {
        return $row->source_type === 'voucher' && $row->origin === $this->pluginId();
    }

    /** Original im Fremdsystem (neuer Tab), nur http(s). */
    public function originalUrl(AccountingVoucher $voucher): ?string {
        $url = $this->voucherUrl($voucher);

        return $url !== null && WebLinkHelper::isHttpUrl($url) ? $url : null;
    }

    /** Original-PDF für Nutzer mit Belegrecht. */
    public function originalPdfUrl(AccountingVoucher $voucher, User $user): ?string {
        return $user->can(Permission::VoucherViewAny->value) ? $this->pdfUrl($voucher) : null;
    }

    /** @return list<UiAction> Original öffnen und Original-PDF, soweit vorhanden */
    public function actionsFor(AccountingVoucher $voucher, User $user): array {
        $actions = [];
        $url = $this->originalUrl($voucher);
        if ($url !== null) {
            $actions[] = new UiAction('open_in_new', (string) __('billing.feed.action.open_original'), $url);
        }
        $pdf = $this->originalPdfUrl($voucher, $user);
        if ($pdf !== null) {
            $actions[] = new UiAction('picture_as_pdf', (string) __('billing.feed.action.original_pdf'), $pdf);
        }

        return $actions;
    }

    public function rowLink(\stdClass $row, User $user): ?UiAction {
        $voucher = $this->voucher($row);
        $url = $voucher instanceof AccountingVoucher ? $this->originalUrl($voucher) : null;

        return $url !== null ? new UiAction('open_in_new', (string) __('billing.feed.action.open_original'), $url) : null;
    }

    public function rowActions(\stdClass $row, User $user, bool $overdue): array {
        $voucher = $this->voucher($row);
        $pdf = $voucher instanceof AccountingVoucher ? $this->originalPdfUrl($voucher, $user) : null;

        return $pdf !== null ? [new UiAction('picture_as_pdf', (string) __('billing.feed.action.original_pdf'), $pdf)] : [];
    }

    private function voucher(\stdClass $row): ?AccountingVoucher {
        return AccountingVoucher::query()->where('plugin_id', $this->pluginId())->find((int) $row->source_id);
    }
}
