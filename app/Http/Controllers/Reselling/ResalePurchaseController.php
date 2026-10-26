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

namespace App\Http\Controllers\Reselling;

use App\Enums\Reselling\SubscriptionProvider;
use App\Http\Controllers\Concerns\{ResolvesCurrentOrganization, ResolvesGlobalDateRange};
use App\Http\Controllers\Controller;
use App\Http\Requests\Reselling\{ResalePurchaseImportRequest, ResalePurchaseStoreRequest};
use App\Models\Reselling\{ResalePeriod, ResaleProviderSupplier, ResalePurchaseEntry, ResaleSubscription};
use App\Models\Supplier\Supplier;
use App\Services\Billing\Purchase\{PurchaseDocument, PurchaseDocuments};
use App\Services\Reselling\Register\{ProviderInvoiceImport, ProviderSuppliers, PurchaseAllocator};
use App\Support\Query\DateRange;
use App\Support\Sqid;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Validation\{Rule, ValidationException};

/**
 * Einkaufsbelege (Feature 152, MVP-762; Review 2026-09-11, Einkauf):
 * Eingangsbelege aller Quellen ({@see PurchaseDocuments} — Lexoffice-Spiegel,
 * lokale Ausgaben, Eingangs-E-Rechnungen) dem Anbieter zuweisen und pro rata
 * auf die Perioden verteilen, Anbieterrechnungen als PDF positionsgenau
 * importieren; Domain-Buchungen kommen automatisch.
 */
class ResalePurchaseController extends Controller {
    use ResolvesCurrentOrganization;
    use ResolvesGlobalDateRange;

    private const PER_PAGE = 50;

    /** Zuteilungsdialog: Eingangsbelege der letzten 36 Monate, höchstens 200. */
    private const DOCUMENT_MONTHS = 36;

    private const DOCUMENT_LIMIT = 200;

    public const SOURCES = [ResalePurchaseEntry::SOURCE_PROVIDER_INVOICE, ResalePurchaseEntry::SOURCE_VOUCHER, ResalePurchaseEntry::SOURCE_DOMAIN, ResalePurchaseEntry::SOURCE_MANUAL];

    public function index(Request $request, PurchaseDocuments $documents): View {
        $organization = $this->currentOrganizationOrAbort(404);
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'provider' => (string) $request->query('provider', ''),
            'source' => (string) $request->query('source', ''),
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
            'unmatched' => $request->boolean('unmatched'),
        ];
        $query = ResalePurchaseEntry::query()
            ->with(['subscription:id,label,customer_id,foreign_customer_id,is_own_holding', 'subscription.customer:id,name', 'subscription.foreignCustomer:id,name', 'period:id,starts_on,ends_on'])
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
        if ($filters['unmatched']) {
            $query->whereNull('subscription_id');
        }
        // Zeitraum nur, wenn gesetzt — Vorgabe ist alles (halboffen: bis < Folgetag).
        if ($filters['from'] !== '' || $filters['to'] !== '') {
            [$from, $to] = $this->resolveRangeWithDefault($request, static fn(): array => [ResalePeriod::today()->subYears(10), ResalePeriod::today()]);
            $filters['from'] = $from->toDateString();
            $filters['to'] = $to->toDateString();
            $query->where('entry_date', '>=', DateRange::day($from))->where('entry_date', '<', DateRange::dayAfter($to));
        }
        $entries = $query->paginate(self::PER_PAGE)->withQueryString();
        // Belege je Quelle in einer Abfrage an die Zeilen hängen (Kennung, Quelle, Permalink, Vorschau).
        $documents->preload($organization, $entries->getCollection());
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
            'unmatchedCount' => ResalePurchaseEntry::query()->whereNull('subscription_id')->count(),
        ]);
    }

    /** Dialog Lieferant → Anbieter (Feature 185, MVP-1419): Bestand mit Lösen, neue Zuordnung mit Namensvorschlag. */
    public function suppliersCreate(ProviderSuppliers $suppliers): View {
        $organization = $this->currentOrganizationOrAbort(404);
        $mappings = $suppliers->all($organization);
        $mapped = $mappings->pluck('supplier_id')->all();
        $candidates = Supplier::query()->orderBy('name')->get(['id', 'name'])
            ->reject(static fn(Supplier $s): bool => in_array($s->id, $mapped, true))
            ->map(static fn(Supplier $s): array => ['supplier' => $s, 'suggested' => $suppliers->suggest((string) $s->name)])
            ->values();

        return view('finance.resale._supplier_dialog', [
            'mappings' => $mappings,
            'candidates' => $candidates,
            'providers' => array_values(array_filter(SubscriptionProvider::cases(), static fn(SubscriptionProvider $p): bool => $p !== SubscriptionProvider::Manual)),
        ]);
    }

    public function suppliersStore(Request $request, ProviderSuppliers $suppliers): RedirectResponse {
        $organization = $this->currentOrganizationOrAbort(404);
        $data = $request->validate([
            'supplier_id' => ['required', 'string', 'max:32'],
            'provider' => ['required', Rule::enum(SubscriptionProvider::class), Rule::notIn([SubscriptionProvider::Manual->value])],
        ]);
        $supplierId = Sqid::decode(Supplier::class, (string) $data['supplier_id']);
        $supplier = $supplierId !== null ? Supplier::query()->find($supplierId) : null;
        if ($supplier === null) {
            throw ValidationException::withMessages(['supplier_id' => (string) __('resale.purchase.supplier.error.unknown')]);
        }
        $provider = SubscriptionProvider::from((string) $data['provider']);
        ResaleProviderSupplier::query()->updateOrCreate(
            ['organization_id' => $organization->id, 'supplier_id' => $supplier->id],
            ['provider' => $provider],
        );
        $suppliers->forget();

        return redirect()->toList('finance.resale.purchases.index')->with('success', __('resale.purchase.supplier.flash.saved', ['supplier' => $supplier->name, 'provider' => $provider->label()]));
    }

    public function suppliersDestroy(ResaleProviderSupplier $mapping, ProviderSuppliers $suppliers): RedirectResponse {
        $mapping->delete();
        $suppliers->forget();

        return redirect()->toList('finance.resale.purchases.index')->with('success', __('resale.purchase.supplier.flash.deleted'));
    }

    /** Einkauf ohne Abo (Feature 185, MVP-1420): dem passenden Abo zuordnen, Periode am Belegdatum. */
    public function assignCreate(ResalePurchaseEntry $entry): View {
        abort_if($entry->subscription_id !== null, 404);
        $subscriptions = ResaleSubscription::query()->where('provider', $entry->provider->value)->planning()
            ->with(['customer:id,name', 'foreignCustomer:id,name,customer_id'])
            ->orderBy('label')->get();

        return view('finance.resale._entry_assign_dialog', ['entry' => $entry, 'subscriptions' => $subscriptions]);
    }

    public function assignStore(Request $request, ResalePurchaseEntry $entry): RedirectResponse {
        abort_if($entry->subscription_id !== null, 404);
        $data = $request->validate(['subscription_id' => ['required', 'string', 'max:32']]);
        $subscriptionId = Sqid::decode(ResaleSubscription::class, (string) $data['subscription_id']);
        $subscription = $subscriptionId !== null ? ResaleSubscription::query()->find($subscriptionId) : null;
        if ($subscription === null) {
            throw ValidationException::withMessages(['subscription_id' => (string) __('resale.purchase.unmatched.error.unknown')]);
        }
        $period = $subscription->periods()
            ->where('starts_on', '<', DateRange::dayAfter($entry->entry_date))
            ->where('ends_on', '>=', DateRange::day($entry->entry_date))
            ->first() ?? $subscription->periods()->orderByDesc('starts_on')->first();
        $entry->forceFill(['subscription_id' => $subscription->id, 'period_id' => $period?->id])->save();

        return redirect()->toList('finance.resale.purchases.index')->with('success', __('resale.purchase.unmatched.flash', ['number' => $entry->document_number ?? '#' . $entry->id, 'label' => $subscription->label]));
    }

    /** Dialog „Eingangsbeleg zuteilen": Belege aller Quellen, per `q` vorgefiltert (Nummer, Lieferant). */
    public function create(Request $request, PurchaseDocuments $documents): View {
        $organization = $this->currentOrganizationOrAbort(404);
        $q = trim((string) $request->query('q', ''));
        $since = ResalePeriod::today()->subMonths(self::DOCUMENT_MONTHS);
        $allocated = [];
        foreach (ResalePurchaseEntry::query()->whereNotNull('document_type')->whereNotNull('document_id')->distinct()->get(['document_type', 'document_id']) as $entry) {
            $allocated[PurchaseDocument::identityOf((string) $entry->document_type, (int) $entry->document_id)] = true;
        }

        return view('finance.resale._purchase_dialog', [
            'documents' => $documents->search($organization, $q !== '' ? $q : null, $since, self::DOCUMENT_LIMIT),
            'allocated' => $allocated,
            'sources' => $documents->sources(),
            'providers' => array_values(array_filter(SubscriptionProvider::cases(), static fn(SubscriptionProvider $p): bool => $p !== SubscriptionProvider::DomainReselling)),
            'q' => $q,
        ]);
    }

    public function store(ResalePurchaseStoreRequest $request, PurchaseAllocator $allocator): RedirectResponse {
        $organization = $this->currentOrganizationOrAbort(404);
        $document = $request->document();
        $net = Money::ofFloat($request->netAmount(), $document->currency);
        $result = $allocator->allocateVoucher($organization, $document, $request->provider(), $net, $request->month(), $request->user());
        if ($result['entries'] === 0) {
            return redirect()->toList('finance.resale.purchases.index')->with('error', __('resale.purchase.flash.no_periods', ['month' => $request->month()->format('Y-m')]));
        }

        return redirect()->toList('finance.resale.purchases.index')->with('success', __('resale.purchase_flash.allocated', ['entries' => $result['entries'], 'amount' => Money::ofFloat($result['allocated'], $document->currency, 2)->format(), 'voucher' => $document->reference()]));
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
        $redirect = redirect()->toList('finance.resale.purchases.index')->with($result['failed'] ? 'error' : 'success', implode(' · ', $result['summary']));
        if ($result['issues'] !== []) {
            $redirect->with('warning', trans_choice('resale.purchase_issues.count', count($result['issues']), ['count' => count($result['issues'])]))
                ->with('resale_purchase_issues', $result['issues']);
        }

        return $redirect;
    }

    public function destroy(ResalePurchaseEntry $entry): RedirectResponse {
        // Zuteilung eines Belegs immer als Ganzes lösen: über den Belegbezug, ohne Beleg (Anbieterrechnung, Handeingabe) über die Belegnummer.
        $group = ResalePurchaseEntry::query()->where('provider', $entry->provider->value)->where('source', $entry->source);
        if ($entry->source !== ResalePurchaseEntry::SOURCE_DOMAIN && $entry->document_type !== null && $entry->document_id !== null) {
            $group->where('document_type', $entry->document_type)->where('document_id', $entry->document_id)->delete();
        } elseif ($entry->source !== ResalePurchaseEntry::SOURCE_DOMAIN && $entry->document_number !== null) {
            $group->where('document_number', $entry->document_number)->delete();
        } else {
            $entry->delete();
        }

        return redirect()->toList('finance.resale.purchases.index')->with('success', __('resale.purchase.flash.removed'));
    }
}
