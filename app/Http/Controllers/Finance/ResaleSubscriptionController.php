<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleSubscriptionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Enums\Reselling\{BillingFrequency, CompanyMappingMode};
use App\Enums\Reselling\{PeriodStatus, RenewalMode, SubscriptionKind, SubscriptionProvider, SubscriptionStatus};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\SaveResaleSubscriptionRequest;
use App\Models\{Article, Customer, ForeignCustomer, LexofficeArticle};
use App\Models\Reselling\{CompanyMapping, ResaleImport, ResaleSubscription};
use App\Services\Reselling\Register\{HolderResolver, MarketplaceImporter, PeriodPlanner};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Reselling-Register (Feature 152, MVP-758): Abos mit Halter, Laufzeit,
 * Preisen und den daraus geplanten Abrechnungsperioden.
 */
class ResaleSubscriptionController extends Controller {
    use ResolvesCurrentOrganization;

    private const PER_PAGE = 50;

    private const MAX_FILE_KB = 10240;

    public function index(Request $request): View {
        $today = CarbonImmutable::today();
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'kind' => (string) $request->query('kind', ''),
            'provider' => (string) $request->query('provider', ''),
            'status' => (string) $request->query('status', ''),
            'customer' => (string) $request->query('customer', ''),
            'open' => $request->boolean('open'),
        ];

        $query = ResaleSubscription::query()
            ->with(['customer:id,name', 'foreignCustomer:id,name,customer_id', 'foreignCustomer.customer:id,name', 'article:id,number,name', 'lexofficeArticle:id,article_number,name'])
            ->withCount(['periods as open_periods_count' => static fn(Builder $q) => $q->where('status', PeriodStatus::Open->value)->where('starts_on', '<', $today->addDay()->toDateString())]);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function (Builder $w) use ($q): void {
                $w->whereLikeEscaped('label', $q)
                    ->orWhereLikeEscaped('external_id', $q)
                    ->orWhereHas('customer', static fn(Builder $c) => $c->whereLikeEscaped('name', $q))
                    ->orWhereHas('foreignCustomer', static fn(Builder $f) => $f->whereLikeEscaped('name', $q));
            });
        }
        if (SubscriptionKind::tryFrom($filters['kind']) !== null) {
            $query->where('kind', $filters['kind']);
        }
        if (SubscriptionProvider::tryFrom($filters['provider']) !== null) {
            $query->where('provider', $filters['provider']);
        }
        if (SubscriptionStatus::tryFrom($filters['status']) !== null) {
            $query->where('status', $filters['status']);
        } elseif ($filters['status'] === '') {
            $query->planning();
        }
        $customerId = $filters['customer'] !== '' ? Sqid::decode(Customer::class, $filters['customer']) : null;
        $customer = $customerId !== null ? Customer::query()->find($customerId) : null;
        if ($customer !== null) {
            $query->forCustomer($customer);
        }
        if ($filters['open']) {
            $query->whereHas('periods', static fn(Builder $p) => $p->where('status', PeriodStatus::Open->value)->where('starts_on', '<', $today->addDay()->toDateString()));
        }

        $subscriptions = $query->orderBy('label')->orderBy('starts_on')->paginate(self::PER_PAGE)->withQueryString();

        $summary = [
            'active' => ResaleSubscription::query()->planning()->count(),
            'open_periods' => \App\Models\Reselling\ResalePeriod::query()->due($today)->count(),
            'unassigned' => ResaleSubscription::query()->planning()->unassigned()->count(),
        ];

        return view('finance.resale.index', [
            'subscriptions' => $subscriptions,
            'filters' => $filters,
            'filterCustomer' => $customer,
            'summary' => $summary,
            'kinds' => SubscriptionKind::cases(),
            'providers' => SubscriptionProvider::cases(),
            'statuses' => SubscriptionStatus::cases(),
        ]);
    }

    public function show(ResaleSubscription $subscription): View {
        $subscription->load(['customer', 'foreignCustomer.customer', 'article', 'lexofficeArticle', 'successor', 'predecessors', 'periods.decidedBy', 'periods.links', 'creator']);

        return view('finance.resale.show', [
            'subscription' => $subscription,
            'today' => CarbonImmutable::today(),
        ]);
    }

    public function create(Request $request): View {
        $customerId = Sqid::decode(Customer::class, (string) $request->query('customer', ''));
        $foreignId = Sqid::decode(ForeignCustomer::class, (string) $request->query('foreign', ''));

        return $this->dialog(null, [
            'holder' => $foreignId !== null ? 'foreign' : ($customerId !== null ? 'customer' : 'none'),
            'customer_id' => $customerId,
            'foreign_customer_id' => $foreignId,
        ]);
    }

    public function store(SaveResaleSubscriptionRequest $request, PeriodPlanner $planner): RedirectResponse {
        $subscription = ResaleSubscription::query()->create($request->subscriptionAttributes() + [
            'organization_id' => $this->currentOrganizationId(),
            'created_by_user_id' => $request->user()?->id,
        ]);
        $planner->sync($subscription);

        return redirect()->route('finance.resale.show', $subscription->sqid)->with('success', __('resale.flash.created'));
    }

    public function edit(ResaleSubscription $subscription): View {
        return $this->dialog($subscription, []);
    }

    public function update(SaveResaleSubscriptionRequest $request, ResaleSubscription $subscription, PeriodPlanner $planner): RedirectResponse {
        $subscription->fill($request->subscriptionAttributes())->save();
        $planner->sync($subscription);

        return redirect()->route('finance.resale.show', $subscription->sqid)->with('success', __('resale.flash.updated'));
    }

    public function destroy(ResaleSubscription $subscription): RedirectResponse {
        $decided = $subscription->periods()->where('status', '!=', PeriodStatus::Open->value)->exists();
        if ($decided) {
            return redirect()->route('finance.resale.show', $subscription->sqid)->with('error', __('resale.flash.has_decisions'));
        }
        $subscription->delete();

        return redirect()->route('finance.resale.index')->with('success', __('resale.flash.deleted'));
    }

    public function importCreate(): View {
        return view('finance.resale._import_dialog');
    }

    public function importStore(Request $request, MarketplaceImporter $importer): RedirectResponse {
        $request->validate([
            'telekom' => ['nullable', 'file', 'max:' . self::MAX_FILE_KB, 'extensions:csv,txt'],
            'qualityhosting' => ['nullable', 'file', 'max:' . self::MAX_FILE_KB, 'extensions:xlsx,xlsm'],
            'pricelist' => ['nullable', 'file', 'max:' . self::MAX_FILE_KB, 'extensions:xlsx,xlsm'],
        ]);
        $files = [];
        $directory = 'resale/' . $this->currentOrganizationId() . '/' . Str::uuid();
        foreach ([ResaleImport::KIND_PURCHASES => 'telekom', ResaleImport::KIND_CONTRACTS => 'qualityhosting', ResaleImport::KIND_PRICELIST => 'pricelist'] as $kind => $field) {
            $upload = $request->file($field);
            if ($upload === null) {
                continue;
            }
            $stored = (string) Storage::disk(ResaleImport::DISK)->putFileAs($directory, $upload, $kind . '.' . strtolower((string) $upload->getClientOriginalExtension()));
            $files[$kind] = ['name' => (string) $upload->getClientOriginalName(), 'path' => Storage::disk(ResaleImport::DISK)->path($stored), 'stored' => $stored];
        }
        if ($files === []) {
            return redirect()->route('finance.resale.index')->with('error', __('resale.import.flash.no_files'));
        }

        $organization = $this->currentOrganizationOrNull();
        if ($organization === null) {
            abort(404);
        }
        $records = $importer->import($organization, $request->user(), $files);
        $summary = [];
        $unassigned = 0;
        $failed = false;
        foreach ($records as $record) {
            $failed = $failed || $record->status === \App\Enums\Reselling\ImportStatus::Failed;
            $unassigned += $record->rows_unassigned;
            $summary[] = $record->status === \App\Enums\Reselling\ImportStatus::Failed
                ? $record->kindLabel() . ': ' . $record->error
                : __('resale.import.flash.line', ['kind' => $record->kindLabel(), 'created' => $record->rows_created, 'updated' => $record->rows_updated, 'unchanged' => $record->rows_unchanged, 'unassigned' => $record->rows_unassigned]);
        }
        $message = __('resale.import.flash.done') . ' ' . implode(' · ', $summary);

        return redirect()->route($unassigned > 0 ? 'finance.resale.inbox' : 'finance.resale.index')->with($failed ? 'error' : 'success', $message);
    }

    public function inbox(HolderResolver $resolver): View {
        $organization = $this->currentOrganizationOrNull();
        if ($organization === null) {
            abort(404);
        }
        $groups = [];
        $subscriptions = ResaleSubscription::query()->planning()->unassigned()->orderBy('company_name')->orderBy('label')->get();
        foreach ($subscriptions as $subscription) {
            $name = $subscription->company_name ?? '';
            $groups[$name] ??= ['company' => $name, 'subscriptions' => [], 'providers' => []];
            $groups[$name]['subscriptions'][] = $subscription;
            $groups[$name]['providers'][$subscription->provider->value] = $subscription->provider->label();
        }
        foreach ($groups as $name => $group) {
            $groups[$name]['suggestions'] = $name === '' ? ['customers' => collect(), 'foreign' => collect()] : $resolver->suggestions($organization, $name);
        }
        ksort($groups);

        return view('finance.resale.inbox', [
            'groups' => array_values($groups),
            'imports' => ResaleImport::query()->with('creator:id,name')->latest()->limit(20)->get(),
        ]);
    }

    public function assignCreate(Request $request): View {
        $company = trim((string) $request->query('company', ''));

        return view('finance.resale._assign_dialog', [
            'company' => $company,
            'count' => ResaleSubscription::query()->planning()->unassigned()->where('company_name', $company)->count(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'foreignCustomers' => ForeignCustomer::query()->with('customer:id,name')->whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'customer_id']),
        ]);
    }

    public function assignStore(Request $request, HolderResolver $resolver): RedirectResponse {
        $validated = $request->validate([
            'company' => ['required', 'string', 'max:190'],
            'mode' => ['required', 'in:customer,partner,foreign,own'],
            'customer_id' => ['nullable', 'string'],
            'foreign_customer_id' => ['nullable', 'string'],
        ]);
        $organization = $this->currentOrganizationOrNull();
        if ($organization === null) {
            abort(404);
        }
        $mode = (string) $validated['mode'];
        $customer = null;
        $foreign = null;
        if ($mode === 'customer' || $mode === 'partner') {
            $id = Sqid::decode(Customer::class, (string) ($validated['customer_id'] ?? ''));
            $customer = $id === null ? null : Customer::query()->find($id);
            if ($customer === null) {
                return back()->withErrors(['customer_id' => __('resale.error.customer_required')]);
            }
        }
        if ($mode === 'foreign') {
            $id = Sqid::decode(ForeignCustomer::class, (string) ($validated['foreign_customer_id'] ?? ''));
            $foreign = $id === null ? null : ForeignCustomer::query()->find($id);
            if ($foreign === null) {
                return back()->withErrors(['foreign_customer_id' => __('resale.error.foreign_required')]);
            }
        }
        if ($mode === 'partner' && $customer !== null) {
            $foreign = $resolver->foreignCustomerUnder($organization, $customer, (string) $validated['company']);
        }

        $attributes = [
            'customer_id' => $mode === 'customer' ? $customer?->id : null,
            'foreign_customer_id' => $foreign?->id,
            'is_own_holding' => $mode === 'own',
        ];
        $affected = ResaleSubscription::query()->unassigned()->where('company_name', (string) $validated['company'])->update($attributes);

        // Merken, damit der nächste Import dieselbe Entscheidung trifft.
        if ($mode === 'customer' || $mode === 'partner') {
            CompanyMapping::query()->updateOrCreate(
                ['organization_id' => $organization->id, 'normalized_name' => \App\Services\Reselling\Marketplace\MarketplaceCompany::normalizeName((string) $validated['company'])],
                ['company_name' => (string) $validated['company'], 'mode' => $mode === 'customer' ? CompanyMappingMode::Customer : CompanyMappingMode::Partner, 'customer_id' => $customer?->id, 'contact_external_id' => null, 'created_by_user_id' => $request->user()?->id],
            );
        } elseif ($mode === 'foreign' && $foreign !== null) {
            CompanyMapping::query()->updateOrCreate(
                ['organization_id' => $organization->id, 'normalized_name' => \App\Services\Reselling\Marketplace\MarketplaceCompany::normalizeName((string) $validated['company'])],
                ['company_name' => (string) $validated['company'], 'mode' => CompanyMappingMode::Partner, 'customer_id' => $foreign->customer_id, 'contact_external_id' => null, 'created_by_user_id' => $request->user()?->id],
            );
        }

        return redirect()->route('finance.resale.inbox')->with('success', __('resale.inbox.flash.assigned', ['count' => $affected]));
    }

    /**
     * @param  array<string, mixed>  $prefill
     */
    private function dialog(?ResaleSubscription $subscription, array $prefill): View {
        return view('finance.resale._form_dialog', [
            'subscription' => $subscription,
            'prefill' => $prefill,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'foreignCustomers' => ForeignCustomer::query()->with('customer:id,name')->whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'customer_id']),
            'articles' => Article::query()->where('sellable', true)->orderBy('name')->get(['id', 'number', 'name']),
            'lexofficeArticles' => LexofficeArticle::query()->active()->orderBy('name')->get(['id', 'article_number', 'name', 'unit_name', 'net_unit_price', 'currency']),
            'kinds' => SubscriptionKind::cases(),
            'providers' => SubscriptionProvider::cases(),
            'statuses' => SubscriptionStatus::cases(),
            'intervals' => BillingFrequency::cases(),
            'renewals' => RenewalMode::cases(),
        ]);
    }
}
