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
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\LexofficeVoucher;
use App\Models\Reselling\ResalePurchaseEntry;
use App\Services\Reselling\Marketplace\QualityHostingInvoiceReader;
use App\Services\Reselling\Register\PurchaseAllocator;
use App\Support\Query\DateRange;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};

/**
 * Einkaufsbelege (Feature 152, MVP-762): Anbieterrechnungen aus dem
 * Belegspiegel dem Anbieter zuweisen und pro rata auf die Perioden verteilen;
 * Domain-Buchungen kommen automatisch.
 */
class ResalePurchaseController extends Controller {
    use ResolvesCurrentOrganization;

    private const PER_PAGE = 50;

    public function index(Request $request): View {
        $entries = ResalePurchaseEntry::query()
            ->with(['subscription:id,label,customer_id,foreign_customer_id,is_own_holding', 'subscription.customer:id,name', 'subscription.foreignCustomer:id,name', 'period:id,starts_on,ends_on', 'voucher:id,voucher_number,voucher_date,total_amount'])
            ->orderByDesc('entry_date')->orderByDesc('id')
            ->paginate(self::PER_PAGE)->withQueryString();
        $byDocument = ResalePurchaseEntry::query()
            ->selectRaw('provider, document_number, MIN(entry_date) AS entry_date, SUM(net_amount) AS net, COUNT(*) AS n')
            ->whereNotNull('document_number')
            ->groupBy('provider', 'document_number')
            ->orderByDesc('entry_date')
            ->limit(24)
            ->get();

        return view('finance.resale.purchases', [
            'entries' => $entries,
            'byDocument' => $byDocument,
            'providers' => SubscriptionProvider::cases(),
        ]);
    }

    public function create(Request $request): View {
        $q = trim((string) $request->query('q', ''));
        $since = CarbonImmutable::today()->subMonths(36);
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

    public function store(Request $request, PurchaseAllocator $allocator): RedirectResponse {
        $validated = $request->validate([
            'voucher_id' => ['required', 'string'],
            'provider' => ['required', 'string'],
            'net_amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'month' => ['required', 'date_format:Y-m'],
        ]);
        $organization = $this->currentOrganizationOrNull();
        $voucherId = Sqid::decode(LexofficeVoucher::class, (string) $validated['voucher_id']);
        $voucher = $voucherId === null ? null : LexofficeVoucher::query()->find($voucherId);
        $provider = SubscriptionProvider::tryFrom((string) $validated['provider']);
        if ($organization === null || $voucher === null || $provider === null) {
            return back()->withErrors(['voucher_id' => __('resale.purchase.error.voucher')]);
        }
        $currency = $voucher->currency;
        $result = $allocator->allocateVoucher($organization, $voucher, $provider, Money::ofFloat((float) $validated['net_amount'], $currency), CarbonImmutable::createFromFormat('Y-m-d', $validated['month'] . '-01') ?: CarbonImmutable::today(), $request->user());
        if ($result['entries'] === 0) {
            return redirect()->route('finance.resale.purchases.index')->with('error', __('resale.purchase.flash.no_periods', ['month' => $validated['month']]));
        }

        return redirect()->route('finance.resale.purchases.index')->with('success', __('resale.purchase.flash.allocated', ['entries' => $result['entries'], 'amount' => number_format($result['allocated'], 2, ',', '.'), 'voucher' => (string) $voucher->voucher_number]));
    }

    public function importCreate(): View {
        return view('finance.resale._purchase_import_dialog');
    }

    public function importStore(Request $request, PurchaseAllocator $allocator, QualityHostingInvoiceReader $reader): RedirectResponse {
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:50'],
            'files.*' => ['file', 'max:10240', 'extensions:pdf'],
        ]);
        $organization = $this->currentOrganizationOrNull();
        if ($organization === null) {
            abort(404);
        }
        $summary = [];
        $failed = false;
        foreach ((array) $request->file('files') as $upload) {
            if (! $upload instanceof \Illuminate\Http\UploadedFile) {
                continue;
            }
            $name = (string) $upload->getClientOriginalName();
            try {
                $invoice = $reader->read($upload->getRealPath() ?: $upload->getPathname());
                if ($invoice->number === '' || $invoice->lines === []) {
                    $failed = true;
                    $summary[] = __('resale.purchase.import.unreadable', ['file' => $name]);

                    continue;
                }
                $result = $allocator->importProviderInvoice($organization, $invoice, SubscriptionProvider::QualityHosting, $request->user(), $name);
                $summary[] = __('resale.purchase.import.line', ['number' => $invoice->number, 'matched' => $result['matched'], 'lines' => $result['lines'], 'duplicates' => $result['duplicates'], 'net' => number_format($result['net'], 2, ',', '.')])
                    . ($result['unmatched'] !== [] ? ' ' . __('resale.purchase.import.unmatched', ['list' => implode('; ', array_slice($result['unmatched'], 0, 5))]) : '');
            } catch (\Throwable $e) {
                $failed = true;
                $summary[] = $name . ': ' . $e->getMessage();
            }
        }

        return redirect()->route('finance.resale.purchases.index')->with($failed ? 'error' : 'success', implode(' · ', $summary));
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
