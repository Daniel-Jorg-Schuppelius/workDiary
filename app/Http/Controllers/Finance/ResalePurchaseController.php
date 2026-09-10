<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResalePurchaseController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\Reselling\SubscriptionProvider;
use App\Http\Controllers\Concerns\{ResolvesCurrentOrganization, ResolvesGlobalDateRange};
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\{ResalePurchaseImportRequest, ResalePurchaseStoreRequest};
use App\Models\LexofficeVoucher;
use App\Models\Reselling\{ResalePeriod, ResalePurchaseEntry};
use App\Services\Reselling\Register\{ProviderInvoiceImport, PurchaseAllocator};
use App\Support\Query\DateRange;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request};

/**
 * Einkaufsbelege (Feature 152, MVP-762): Anbieterrechnungen aus dem
 * Belegspiegel dem Anbieter zuweisen und pro rata auf die Perioden verteilen,
 * Anbieterrechnungen als PDF positionsgenau importieren; Domain-Buchungen
 * kommen automatisch.
 */
class ResalePurchaseController extends Controller {
    use ResolvesCurrentOrganization;
    use ResolvesGlobalDateRange;

    private const PER_PAGE = 50;

    /** Belegspiegel im Zuteilungsdialog: Eingangsbelege der letzten 36 Monate. */
    private const VOUCHER_MONTHS = 36;

    public const SOURCES = [ResalePurchaseEntry::SOURCE_PROVIDER_INVOICE, ResalePurchaseEntry::SOURCE_VOUCHER, ResalePurchaseEntry::SOURCE_DOMAIN, ResalePurchaseEntry::SOURCE_MANUAL];

    public function index(Request $request): View {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'provider' => (string) $request->query('provider', ''),
            'source' => (string) $request->query('source', ''),
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
        ];
        $query = ResalePurchaseEntry::query()
            ->with(['subscription:id,label,customer_id,foreign_customer_id,is_own_holding', 'subscription.customer:id,name', 'subscription.foreignCustomer:id,name', 'period:id,starts_on,ends_on', 'voucher:id,voucher_number,voucher_date,total_amount'])
            ->orderByDesc('entry_date')->orderByDesc('id');
        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(static function (Builder $w) use ($q): void {
                $w->whereLikeEscaped('document_number', $q)
                    ->orWhereLikeEscaped('description', $q)
                    ->orWhereHas('subscription', static fn(Builder $s) => $s->whereLikeEscaped('label', $q)->orWhereLikeEscaped('external_id', $q));
            });
        }
        if (SubscriptionProvider::tryFrom($filters['provider']) !== null) {
            $query->where('provider', $filters['provider']);
        }
        if (in_array($filters['source'], self::SOURCES, true)) {
            $query->where('source', $filters['source']);
        }
        // Zeitraum nur, wenn gesetzt — Vorgabe ist alles (halboffen: bis < Folgetag).
        if ($filters['from'] !== '' || $filters['to'] !== '') {
            [$from, $to] = $this->resolveRangeWithDefault($request, static fn(): array => [ResalePeriod::today()->subYears(10), ResalePeriod::today()]);
            $filters['from'] = $from->toDateString();
            $filters['to'] = $to->toDateString();
            $query->where('entry_date', '>=', DateRange::day($from))->where('entry_date', '<', DateRange::dayAfter($to));
        }
        $entries = $query->paginate(self::PER_PAGE)->withQueryString();
        $byDocument = ResalePurchaseEntry::query()
            ->selectRaw('provider, document_number, currency, MIN(entry_date) AS entry_date, SUM(net_amount) AS net, COUNT(*) AS n')
            ->whereNotNull('document_number')
            ->groupBy('provider', 'document_number', 'currency')
            ->orderByDesc('entry_date')
            ->limit(24)
            ->get();

        return view('finance.resale.purchases', [
            'entries' => $entries,
            'byDocument' => $byDocument,
            'filters' => $filters,
            'providers' => SubscriptionProvider::cases(),
            'sources' => self::SOURCES,
        ]);
    }

    /** Dialog „Eingangsbeleg zuteilen": Belege des Spiegels, per `q` vorgefiltert (Nummer, Lieferant). */
    public function create(Request $request): View {
        $q = trim((string) $request->query('q', ''));
        $since = ResalePeriod::today()->subMonths(self::VOUCHER_MONTHS);
        $vouchers = LexofficeVoucher::query()
            ->whereIn('voucher_type', ['purchaseinvoice', 'purchasecreditnote'])
            ->where('archived', false)
            ->where('voucher_date', '>=', DateRange::day($since))
            ->with('supplier:id,name')
            ->when($q !== '', static fn($query) => $query->where(static fn($w) => $w->whereLikeEscaped('voucher_number', $q)->orWhereHas('supplier', static fn($s) => $s->whereLikeEscaped('name', $q))))
            ->orderByDesc('voucher_date')
            ->limit(200)
            ->get();
        $allocated = ResalePurchaseEntry::query()->whereNotNull('lexoffice_voucher_id')->pluck('lexoffice_voucher_id')->unique()->flip()->all();

        return view('finance.resale._purchase_dialog', [
            'vouchers' => $vouchers,
            'allocated' => $allocated,
            'providers' => array_values(array_filter(SubscriptionProvider::cases(), static fn(SubscriptionProvider $p): bool => $p !== SubscriptionProvider::DomainReselling)),
            'q' => $q,
        ]);
    }

    public function store(ResalePurchaseStoreRequest $request, PurchaseAllocator $allocator): RedirectResponse {
        $organization = $this->currentOrganizationOrAbort(404);
        $voucher = $request->voucher();
        $net = Money::ofFloat($request->netAmount(), $voucher->currency);
        $result = $allocator->allocateVoucher($organization, $voucher, $request->provider(), $net, $request->month(), $request->user());
        if ($result['entries'] === 0) {
            return redirect()->route('finance.resale.purchases.index')->with('error', __('resale.purchase.flash.no_periods', ['month' => $request->month()->format('Y-m')]));
        }

        return redirect()->route('finance.resale.purchases.index')->with('success', __('resale.purchase_flash.allocated', ['entries' => $result['entries'], 'amount' => Money::ofFloat($result['allocated'], $voucher->currency, 2)->format(), 'voucher' => (string) $voucher->voucher_number]));
    }

    public function importCreate(): View {
        return view('finance.resale._purchase_import_dialog');
    }

    /**
     * PDF-Import: Zusammenfassung je Datei als Flash, Zeilenbefunde und
     * Summenabweichungen als Liste auf der Einkaufsseite — der Import läuft
     * trotzdem durch (Review 2026-09-10, B17).
     */
    public function importStore(ResalePurchaseImportRequest $request, ProviderInvoiceImport $import): RedirectResponse {
        $organization = $this->currentOrganizationOrAbort(404);
        $result = $import->run($organization, $request->uploads(), $request->user());
        $redirect = redirect()->route('finance.resale.purchases.index')->with($result['failed'] ? 'error' : 'success', implode(' · ', $result['summary']));
        if ($result['issues'] !== []) {
            $redirect->with('warning', trans_choice('resale.purchase_issues.count', count($result['issues']), ['count' => count($result['issues'])]))
                ->with('resale_purchase_issues', $result['issues']);
        }

        return $redirect;
    }

    public function destroy(ResalePurchaseEntry $entry): RedirectResponse {
        // Zuteilung eines Belegs immer als Ganzes lösen.
        if ($entry->document_number !== null && $entry->source !== ResalePurchaseEntry::SOURCE_DOMAIN) {
            ResalePurchaseEntry::query()->where('provider', $entry->provider->value)->where('source', $entry->source)->where('document_number', $entry->document_number)->delete();
        } else {
            $entry->delete();
        }

        return redirect()->route('finance.resale.purchases.index')->with('success', __('resale.purchase.flash.removed'));
    }
}
