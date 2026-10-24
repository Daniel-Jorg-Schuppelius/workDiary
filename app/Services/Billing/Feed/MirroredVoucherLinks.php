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

use App\Models\Finance\AccountingVoucher;
use App\Models\Platform\User;
use App\Services\Billing\DocumentFeedFilters;
use App\Support\Ui\UiAction;
use CommonToolkit\Helper\Data\WebLinkHelper;
use Illuminate\Database\Query\Builder;

/**
 * Link vom gespiegelten Beleg (`accounting_vouchers`) zum Original im
 * Fremdsystem (Feature 176). Ein Plugin meldet die Unterklasse als Quelle an;
 * sie liefert keine eigenen Zeilen, sie stellt nur die Belegspiegel-Zeilen
 * ihrer Plugin-ID dar. Verlinkt wird nur http(s), in einem neuen Tab.
 */
abstract class MirroredVoucherLinks implements DocumentFeedSource, PresentsFeedRows {
    /** Plugin-ID, unter der die Belege gespiegelt sind (= Herkunft der Zeile). */
    abstract protected function pluginId(): string;

    abstract protected function voucherUrl(AccountingVoucher $voucher): ?string;

    public function key(): string {
        return $this->pluginId() . '_voucher_links';
    }

    public function builder(DocumentFeedFilters $filters): ?Builder {
        return null;
    }

    public function presents(\stdClass $row): bool {
        return $row->source_type === 'voucher' && $row->origin === $this->pluginId();
    }

    public function rowLink(\stdClass $row, User $user): ?UiAction {
        $voucher = AccountingVoucher::query()->where('plugin_id', $this->pluginId())->find((int) $row->source_id);
        $url = $voucher instanceof AccountingVoucher ? $this->voucherUrl($voucher) : null;

        return $url !== null && WebLinkHelper::isHttpUrl($url) ? new UiAction('open_in_new', (string) __('billing.feed.action.open_original'), $url) : null;
    }

    public function rowActions(\stdClass $row, User $user, bool $overdue): array {
        return [];
    }
}
