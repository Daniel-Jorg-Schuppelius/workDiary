<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficePartyDocumentSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Plugins\Lexoffice\LexofficeVoucher;
use App\Models\Supplier\Supplier;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\PluginManager;
use App\Services\Billing\Contracts\PartyDocumentSource;
use App\Services\Billing\Dto\{PartyDocument, PartyDocumentList};
use App\Support\Ui\UiAction;
use Carbon\CarbonInterface;

/** Belegliste der Kunden- und Lieferantenakte aus dem Lexoffice-Belegspiegel. */
final class LexofficePartyDocumentSource implements PartyDocumentSource {
    public function key(): string {
        return LexofficePlugin::ID;
    }

    public function documentsFor(Customer|Supplier $party, CarbonInterface $from, CarbonInterface $to): ?PartyDocumentList {
        if (! app(PluginManager::class)->enabled()->has(LexofficePlugin::ID)) {
            return null;
        }

        $linked = ExternalReference::query()
            ->forPlugin((int) $party->organization_id, LexofficePlugin::ID, LexofficePlugin::EXT_TYPE_CONTACT)
            ->forReferenceable($party)
            ->exists();

        $vouchers = LexofficeVoucher::query()
            ->where($party instanceof Customer ? 'customer_id' : 'supplier_id', $party->getKey())
            ->where('archived', false)
            ->whereBetween('voucher_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderByDesc('voucher_date')
            ->limit(500)
            // Ohne den longtext-Payload je Beleg (Vollscan 2026-08-23, A9).
            ->get(['id', 'organization_id', 'voucher_type', 'voucher_status', 'voucher_number', 'voucher_date', 'total_amount', 'currency']);

        $documents = [];
        foreach ($vouchers as $voucher) {
            $documents[] = new PartyDocument(
                type: (string) $voucher->voucher_type,
                number: $voucher->voucher_number,
                date: $voucher->voucher_date,
                status: $voucher->voucher_status,
                amount: $voucher->total_amount?->toFloat() ?? 0.0,
                currency: $voucher->currency->value,
                actions: $this->actions($voucher),
            );
        }

        $syncRoute = $party instanceof Customer ? 'customers.lexoffice.sync-vouchers' : 'suppliers.lexoffice.sync-vouchers';

        return new PartyDocumentList(
            source: 'Lexoffice',
            linked: $linked,
            documents: $documents,
            refresh: $linked ? new UiAction('sync', __('Belege synchronisieren'), route($syncRoute, $party), post: true) : null,
            unlinkedHint: $linked ? null : (string) __('Kein Lexoffice-Kontakt verknüpft — verknüpfte Belege erscheinen erst nach Verknüpfung und Synchronisierung.'),
        );
    }

    /** @return list<UiAction> */
    private function actions(LexofficeVoucher $voucher): array {
        $actions = [];
        if (in_array($voucher->voucher_type, ['invoice', 'salesinvoice'], true) && $voucher->voucher_status === 'overdue') {
            $actions[] = new UiAction('notification_important', __('Mahnung erstellen'), route('lexoffice.vouchers.dunning', $voucher), post: true, tone: 'warning');
        }
        $actions[] = new UiAction('visibility', __('Belegbild anzeigen'), route('lexoffice.vouchers.preview', $voucher), modal: true);
        $actions[] = new UiAction('download', __('Belegbild herunterladen'), route('lexoffice.vouchers.file', [$voucher, 'download' => 1]));

        return $actions;
    }
}
