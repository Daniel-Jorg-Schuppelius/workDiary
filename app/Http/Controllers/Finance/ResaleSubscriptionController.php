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

use App\Enums\Contract\ContractPartnerType;
use App\Enums\Reselling\{BillingFrequency, CompanyMappingMode, ImportStatus};
use App\Enums\Reselling\{PeriodStatus, RenewalMode, SubscriptionKind, SubscriptionProvider, SubscriptionStatus};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\Resale\{AssignResaleHolderRequest, ImportResaleFilesRequest, TransferResaleSubscriptionRequest};
use App\Http\Requests\Finance\SaveResaleSubscriptionRequest;
use App\Models\{Article, Customer, ForeignCustomer, LexofficeArticle, Organization};
use App\Models\Contract\Contract;
use App\Models\Reselling\{CompanyMapping, ResaleImport, ResalePeriod, ResaleSubscription};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Reselling\Marketplace\MarketplaceCompany;
use App\Services\Reselling\Mirror\{InvoiceMirror, MirrorLine, MirrorVoucher};
use App\Services\Reselling\Register\{HolderResolver, LicenseMonths, LinkProposer, MarketplaceImporter, PeriodLinker, PeriodPlanner};
use App\Support\{CsvExport, Sqid};
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\{Builder, Collection};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{DB, Gate, Log, Storage};
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reselling-Register (Feature 152, MVP-758): Abos mit Halter, Laufzeit,
 * Preisen und den daraus geplanten Abrechnungsperioden.
 */
class ResaleSubscriptionController extends Controller {
    use ResolvesCurrentOrganization;

    private const PER_PAGE = 50;

    public function __construct(private readonly InvoiceMirror $mirror, private readonly PeriodLinker $linker) {}

    public function index(Request $request): View {
        $today = ResalePeriod::today();
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'kind' => (string) $request->query('kind', ''),
            'provider' => (string) $request->query('provider', ''),
            'status' => (string) $request->query('status', ''),
            'customer' => (string) $request->query('customer', ''),
            'open' => $request->boolean('open'),
        ];

        // Fällig = offen, Beginn erreicht, fremder Halter — eigener Bestand wird nie berechnet (B14).
        $query = ResaleSubscription::query()
            ->with(['customer:id,name', 'foreignCustomer:id,name,customer_id', 'foreignCustomer.customer:id,name', 'article:id,number,name', 'lexofficeArticle:id,article_number,name'])
            ->withCount(['periods as open_periods_count' => static fn($q) => $q->due($today)]);

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
            $query->whereIn('id', ResalePeriod::query()->due($today)->select('subscription_id'));
        }

        $subscriptions = $query->orderBy('label')->orderBy('starts_on')->paginate(self::PER_PAGE)->withQueryString();

        $summary = [
            'active' => ResaleSubscription::query()->planning()->count(),
            'open_periods' => ResalePeriod::query()->due($today)->count(),
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
        $subscription->load(['customer', 'foreignCustomer.customer', 'article', 'lexofficeArticle', 'contract', 'successor', 'predecessors', 'parent.customer', 'parent.foreignCustomer', 'assignments.customer', 'assignments.foreignCustomer', 'periods.decidedBy', 'periods.links', 'creator']);
        $organization = $this->currentOrganizationOrAbort(404);
        $this->mirror->preload($organization, $subscription->periods->flatMap(static fn(ResalePeriod $p) => $p->links));
        $today = ResalePeriod::today();
        // Ziele der Schnellzuordnung: noch nicht entschieden oder teilweise gedeckt, Beginn erreicht.
        $openPeriods = $subscription->periods
            ->filter(static fn(ResalePeriod $p): bool => (! $p->status->isDecided() || $p->status === PeriodStatus::Partial) && ! $p->starts_on->greaterThan($today))
            ->values();

        return view('finance.resale.show', [
            'subscription' => $subscription,
            'today' => $today,
            'assignedNow' => $subscription->assignments->isNotEmpty() ? $subscription->assignedQuantityOn($today) : 0,
            'openPeriods' => $openPeriods,
            'invoices' => $this->recipientInvoices($organization, $subscription, $openPeriods->first()),
            // Vertragsakte (079) nur verlinken, wenn Modul und Recht sie öffnen können.
            'contractLink' => $subscription->contract !== null && $this->contractsEnabled() && Gate::allows('view', $subscription->contract) ? route('contracts.show', $subscription->contract) : null,
        ]);
    }

    /**
     * Rechnungen des Rechnungsempfängers aus dem Belegspiegel (alle Quellen)
     * im Fenster des Abos (ab 90 Tage vor Beginn): je Beleg die Lizenz-
     * positionen mit Lizenzmonaten, Verbrauch, Rest und Vorbelegung der
     * Schnellzuordnung; die übrigen Positionen einklappbar; dazu die Zahl
     * noch nicht gespiegelter Rechnungen.
     *
     * @return array{has_source: bool, vouchers: list<array{voucher: MirrorVoucher, permalink: string|null, rows: int, licence: list<array{line: MirrorLine, months: float, per_licence: float, linked: array{months: float, periods: list<string>}|null, remaining: float, default_licences: float}>, other: list<MirrorLine>}>, pending: int, hidden: int}
     */
    private function recipientInvoices(Organization $organization, ResaleSubscription $subscription, ?ResalePeriod $firstOpen): array {
        $billedTo = $subscription->is_own_holding ? null : $subscription->billedTo();
        if ($billedTo === null) {
            return ['has_source' => false, 'vouchers' => [], 'pending' => 0, 'hidden' => 0];
        }
        $from = $subscription->starts_on->subDays(LinkProposer::WINDOW_BEFORE);
        $vouchers = $this->mirror->vouchersFor($organization, [$billedTo->id], $from);
        $licenceLines = [];
        foreach ($vouchers as $voucher) {
            foreach ($voucher->lines as $line) {
                if ($line->articleIsLicence) {
                    $licenceLines[] = $line;
                }
            }
        }
        $linked = $this->linker->consumed($licenceLines);

        $rows = [];
        $hidden = 0;
        foreach ($vouchers as $voucher) {
            $licence = [];
            $other = [];
            foreach ($voucher->lines as $line) {
                if (! $line->articleIsLicence) {
                    $other[] = $line;
                    $hidden++;

                    continue;
                }
                $split = LicenseMonths::split($line);
                $months = $split['licences'] * $split['months'];
                $info = $linked[$line->identity()] ?? null;
                $remaining = max(0.0, $months - ($info['months'] ?? 0.0));
                $perLicence = max(0.01, $split['months']);
                $needLicences = max(1.0, ($firstOpen?->requiredMonths() ?? 1.0) / $perLicence);
                $licence[] = [
                    'line' => $line,
                    'months' => $months,
                    'per_licence' => $split['months'],
                    'linked' => $info,
                    'remaining' => $remaining,
                    'default_licences' => round(min($remaining / $perLicence, $needLicences), 2),
                ];
            }
            if ($licence === []) {
                continue; // nur Nicht-Lizenzpositionen: Rechnung gehört nicht in die Liste
            }
            $rows[] = [
                'voucher' => $voucher,
                'permalink' => $voucher->permalink,
                'rows' => count($licence) + ($other !== [] ? 1 : 0),
                'licence' => $licence,
                'other' => $other,
            ];
        }

        return ['has_source' => $this->mirror->coversRecipient($organization, $billedTo), 'vouchers' => $rows, 'pending' => $this->mirror->pendingCount($organization, [$billedTo->id], $from), 'hidden' => $hidden];
    }

    public function create(Request $request): View {
        $customerId = Sqid::decode(Customer::class, (string) $request->query('customer', ''));
        $foreignId = Sqid::decode(ForeignCustomer::class, (string) $request->query('foreign', ''));
        $prefill = [
            'holder' => $foreignId !== null ? 'foreign' : ($customerId !== null ? 'customer' : 'none'),
            'customer_id' => $customerId,
            'foreign_customer_id' => $foreignId,
        ];
        // „Abo aus Rechnungsposition anlegen" (Abgleich): Position liefert Produkt, Menge, Beginn und Preis.
        $lineKey = trim((string) $request->query('line', ''));
        $organization = $this->currentOrganizationOrNull();
        $line = $lineKey !== '' && $organization !== null ? $this->mirror->lineByKey($organization, $lineKey) : null;
        if ($line !== null) {
            $split = LicenseMonths::split($line);
            $start = LicenseMonths::referenceDate($line);
            $perLicenceYear = LicenseMonths::isMonthly($line) ? $line->unitNet->times(12) : $line->unitNet;
            $provider = SubscriptionProvider::tryFrom((string) $request->query('provider', '')) ?? SubscriptionProvider::Manual;
            $prefill += [
                'label' => $line->label(),
                'lexoffice_article_id' => $line->lexofficeArticleId(),
                'article_id' => $line->localArticleId(),
                'quantity' => (int) round($split['licences']),
                'starts_on' => $start?->toDateString(),
                'provider' => $provider === SubscriptionProvider::DomainReselling ? SubscriptionProvider::Manual->value : $provider->value,
                'sale_unit_price' => $perLicenceYear->withScale(2)->getAmount(),
                // Laufzeit nur aus der Position, wenn die Lizenzzahl sicher ist („24 Monat" allein kann 2 × 12 sein).
                'term_months' => LicenseMonths::isLicenceCountCertain($line) && (int) round($split['months']) > 0 ? (int) round($split['months']) : 12,
            ];
        }

        return $this->dialog(null, $prefill);
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
        if ($subscription->isAssignment() && $subscription->parent !== null) {
            // Menge oder Laufzeit der Abtretung geändert: der Vertrag plant mit dem neuen Rest.
            $subscription->parent->unsetRelation('assignments');
            $planner->sync($subscription->parent);
        }

        return redirect()->route('finance.resale.show', $subscription->sqid)->with('success', __('resale.flash.updated'));
    }

    public function destroy(ResaleSubscription $subscription, PeriodPlanner $planner): RedirectResponse {
        $blocked = match (true) {
            $subscription->isDomain() => 'resale.delete_error.is_domain',
            $subscription->periods()->where('status', '!=', PeriodStatus::Open->value)->exists() => 'resale.flash.has_decisions',
            $subscription->assignments()->exists() => 'resale.transfer.flash.has_assignments',
            $subscription->purchases()->exists() => 'resale.delete_error.has_purchases',
            default => null,
        };
        if ($blocked !== null) {
            return redirect()->route('finance.resale.show', $subscription->sqid)->with('error', __($blocked));
        }
        $parent = $subscription->parent;
        $subscription->delete();
        if ($parent !== null) {
            // Abtretung weg: der Vertrag bekommt seine Lizenzen zurück.
            $parent->unsetRelation('assignments');
            $planner->sync($parent);
        }

        return redirect()->route('finance.resale.index')->with('success', __('resale.flash.deleted'));
    }

    /**
     * Dialog: Lizenzen dieses Vertrags an einen anderen Halter abtreten. Aus
     * dem Abgleich kommt die Vorbelegung (Kunde, Menge, Zeitraum der Periode):
     * ein Halterwechsel im Zeitverlauf — etwa eine Firma, die aufgespalten
     * wurde — ist eine Abtretung aller Lizenzen für den alten Zeitraum.
     */
    public function transferCreate(Request $request, ResaleSubscription $subscription): View {
        $customerId = Sqid::decode(Customer::class, (string) $request->query('customer', ''));
        $subscription->load(['assignments', 'customer', 'foreignCustomer']);
        $startsOn = $this->queryDate($request, 'starts_on') ?? $subscription->starts_on;
        $endsOn = $this->queryDate($request, 'ends_on') ?? $subscription->ends_on;
        $available = max(0, $subscription->quantity - $subscription->assignedQuantityBetween($startsOn, $endsOn));
        $wanted = max(0, (int) $request->query('quantity', '0'));

        return view('finance.resale._transfer_dialog', [
            'subscription' => $subscription,
            'available' => $available,
            'quantityDefault' => $wanted > 0 ? min($wanted, max(1, $available)) : 1,
            'prefill' => [
                'customer_id' => $customerId !== null ? Sqid::encode(Customer::class, $customerId) : '',
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $endsOn?->toDateString() ?? '',
            ],
        ] + $this->holderPicker());
    }

    /**
     * Abtretung anlegen: Kind-Abo mit Halter, Menge und Laufzeit; Produkt,
     * Preise und Rhythmus vom Vertrag. Der Vertrag plant danach mit dem Rest.
     */
    public function transferStore(TransferResaleSubscriptionRequest $request, ResaleSubscription $subscription, PeriodPlanner $planner): RedirectResponse {
        $holder = $request->holder();
        $quantity = (int) $request->validated('quantity');
        $salePrice = $request->validated('sale_unit_price');
        $assignment = DB::transaction(function () use ($request, $subscription, $planner, $holder, $quantity, $salePrice): ResaleSubscription {
            $subscription->load('assignments');
            $sequence = $subscription->nextAssignmentSuffix();
            $assignment = ResaleSubscription::query()->create([
                'organization_id' => $subscription->organization_id,
                'parent_id' => $subscription->id,
                'kind' => $subscription->kind,
                'provider' => $subscription->provider,
                'external_id' => $subscription->external_id !== null ? $subscription->external_id . '#' . $sequence : null,
                'label' => $subscription->label,
                'company_name' => $holder['foreign'] !== null ? $holder['foreign']->name : $holder['customer']->name,
                'customer_id' => $holder['foreign'] === null ? $holder['customer']->id : null,
                'foreign_customer_id' => $holder['foreign']?->id,
                'is_own_holding' => false,
                'article_id' => $subscription->article_id,
                'lexoffice_article_id' => $subscription->lexoffice_article_id,
                'quantity' => $quantity,
                'starts_on' => (string) $request->validated('starts_on'),
                'ends_on' => $request->validated('ends_on') ?: $subscription->ends_on?->toDateString(),
                'term_months' => $subscription->term_months,
                'interval' => $subscription->interval,
                'renewal' => $subscription->renewal,
                'purchase_unit_price' => $subscription->purchase_unit_price?->getAmount(),
                'sale_unit_price' => is_numeric($salePrice) ? (string) $salePrice : $subscription->sale_unit_price?->getAmount(),
                'currency' => $subscription->currency,
                'status' => $subscription->status,
                'notes' => $request->validated('note') ?: null,
                'created_by_user_id' => $request->user()?->id,
            ]);
            $planner->sync($assignment);
            $subscription->unsetRelation('assignments');
            $planner->sync($subscription);

            return $assignment;
        });

        return redirect()->route('finance.resale.show', $subscription->sqid)->with('success', __('resale.transfer.flash.created', ['quantity' => $quantity, 'holder' => $assignment->holderLabel()]));
    }

    public function importCreate(): View {
        return view('finance.resale._import_dialog');
    }

    /** CSV-Vorlage für die generische Liste: Spaltennamen, die der Reader erkennt, plus eine Beispielzeile. */
    public function importTemplate(): StreamedResponse {
        return CsvExport::streamFromRows(
            (string) __('resale.import_review.template_filename'),
            ['Kennung', 'Firma', 'Produkt', 'Menge', 'Beginn', 'Ende', 'Intervall', 'Laufzeit (Monate)', 'Einkaufspreis', 'Verkaufspreis', 'Anbieter', 'Bestellnummer'],
            [['V-2026-001', 'Beispiel GmbH', 'Microsoft 365 Business Standard', '3', '01.03.2026', '', 'jährlich', '12', '128,38', '145,56', 'manual', '']],
        );
    }

    public function importStore(ImportResaleFilesRequest $request, MarketplaceImporter $importer): RedirectResponse {
        $uploads = $request->uploads(); // mindestens eine Datei — sonst 422 aus dem FormRequest
        $organization = $this->currentOrganizationOrAbort(404);
        $files = [];
        $directory = 'resale/' . $organization->id . '/' . Str::uuid();
        foreach ($uploads as $kind => $upload) {
            $stored = (string) Storage::disk(ResaleImport::DISK)->putFileAs($directory, $upload, $kind . '.' . strtolower((string) $upload->getClientOriginalExtension()));
            $files[$kind] = ['name' => Str::limit((string) $upload->getClientOriginalName(), 180, ''), 'path' => Storage::disk(ResaleImport::DISK)->path($stored), 'stored' => $stored];
        }

        try {
            $records = $importer->import($organization, $request->user(), $files, null, $request->genericProvider());
        } catch (\Throwable $e) {
            Log::error('resale.import failed', ['organization_id' => $organization->id, 'exception' => $e]);

            return redirect()->route('finance.resale.index')->with('error', __('resale.general.failed'));
        }
        $summary = [];
        $unassigned = 0;
        $skipped = 0;
        $failed = false;
        foreach ($records as $record) {
            $failed = $failed || $record->status === ImportStatus::Failed;
            $unassigned += $record->rows_unassigned;
            $skipped += $record->issueCount();
            $summary[] = $record->status === ImportStatus::Failed
                ? $record->kindLabel() . ': ' . $this->safeImportError($record)
                : __('resale.import.flash.line', ['kind' => $record->kindLabel(), 'created' => $record->rows_created, 'updated' => $record->rows_updated, 'unchanged' => $record->rows_unchanged, 'unassigned' => $record->rows_unassigned]);
        }
        if ($skipped > 0) {
            $summary[] = trans_choice('resale.import_review.flash_skipped', $skipped, ['count' => $skipped]);
        }
        $message = __('resale.import.flash.done') . ' ' . implode(' · ', $summary);

        // Übersprungene Zeilen sind kein Fehlschlag: der Lauf ist durch, die Inbox zeigt die Befunde je Zeile.
        return redirect()->route($unassigned > 0 || $skipped > 0 ? 'finance.resale.inbox' : 'finance.resale.index')->with($failed ? 'error' : 'success', $message);
    }

    /**
     * Fehlertext eines gescheiterten Laufs für den Flash (C9): die Reader
     * werfen übersetzte Meldungen ohne Pfade; alles, was nach PHP-Fehler oder
     * Serverpfad aussieht, wird protokolliert und durch den Sammelkey ersetzt.
     */
    private function safeImportError(ResaleImport $record): string {
        $error = trim((string) $record->error);
        $raw = $error === ''
            || str_contains($error, base_path())
            || preg_match('~(\.php(:\d+| on line)|Stack trace|Call to |Undefined |Uncaught |Argument #\d)~', $error) === 1;
        if ($raw) {
            Log::warning('resale.import record failed', ['import_id' => $record->id, 'kind' => $record->kind, 'error' => $error]);

            return (string) __('resale.general.failed');
        }

        return $error;
    }

    public function inbox(HolderResolver $resolver): View {
        $organization = $this->currentOrganizationOrAbort(404);
        $groups = [];
        $subscriptions = ResaleSubscription::query()->planning()->unassigned()->orderBy('company_name')->orderBy('label')->get();
        foreach ($subscriptions as $subscription) {
            $name = $subscription->company_name ?? '';
            $groups[$name] ??= ['company' => $name, 'subscriptions' => [], 'providers' => []];
            $groups[$name]['subscriptions'][] = $subscription;
            $groups[$name]['providers'][$subscription->provider->value] = $subscription->provider->label();
        }
        foreach (array_keys($groups) as $name) {
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
            'count' => $this->unassignedOf($company)->count(),
        ] + $this->holderPicker());
    }

    public function assignStore(AssignResaleHolderRequest $request, HolderResolver $resolver): RedirectResponse {
        $organization = $this->currentOrganizationOrAbort(404);
        $mode = $request->mode();
        $company = $request->company();
        $holder = $request->holder();
        $customer = $holder['customer'];
        $foreign = $holder['foreign'];
        if ($mode === 'partner' && $customer !== null) {
            $foreign = $resolver->foreignCustomerUnder($organization, $customer, $company);
        }

        $affected = $this->unassignedOf($company)->update([
            'customer_id' => $mode === 'customer' ? $customer?->id : null,
            'foreign_customer_id' => $mode === 'customer' ? null : $foreign?->id,
            'is_own_holding' => $mode === 'own',
        ]);

        // Merken, damit der nächste Import dieselbe Entscheidung trifft — auch
        // „eigener Bestand" (Review 2026-09-10): sonst landet die Firma beim
        // nächsten Import wieder in der Inbox.
        $mappingMode = match ($mode) {
            'customer' => CompanyMappingMode::Customer,
            'partner', 'foreign' => CompanyMappingMode::Partner,
            'own' => CompanyMappingMode::Own,
            default => null,
        };
        $mappingCustomerId = $mode === 'foreign' ? $foreign?->customer_id : $customer?->id;
        if ($mappingMode !== null && ($mappingMode === CompanyMappingMode::Own || $mappingCustomerId !== null)) {
            CompanyMapping::query()->updateOrCreate(
                ['organization_id' => $organization->id, 'normalized_name' => MarketplaceCompany::normalizeName($company)],
                ['company_name' => $company, 'mode' => $mappingMode, 'customer_id' => $mappingMode === CompanyMappingMode::Own ? null : $mappingCustomerId, 'contact_external_id' => null, 'created_by_user_id' => $request->user()?->id],
            );
        }

        return redirect()->route('finance.resale.inbox')->with('success', __('resale.inbox.flash.assigned', ['count' => $affected]));
    }

    /**
     * Abos ohne Halter einer Firma laut Anbieter — Dialogzähler und Zuordnung
     * treffen dieselben Zeilen (auch beendete: ein Halter gehört zur Historie).
     *
     * @return Builder<ResaleSubscription>
     */
    private function unassignedOf(string $company): Builder {
        return ResaleSubscription::query()->unassigned()->where('company_name', $company);
    }

    /**
     * ISO-Datum aus der Query (die Links des Abgleichs), alles andere gilt als
     * nicht angegeben — `parse('x')` wäre „heute", Überläufe wie 2025-13-45
     * würden still weitergerechnet, deshalb Format + Rundlauf-Prüfung.
     */
    private function queryDate(Request $request, string $key): ?CarbonImmutable {
        $value = trim((string) $request->query($key, ''));
        if ($value === '') {
            return null;
        }
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (InvalidFormatException) {
            return null;
        }

        return $date !== null && $date->toDateString() === $value ? $date : null;
    }

    /**
     * Halterwahl für die Dialoge: Kunden und ihre nicht archivierten Fremdkunden
     * (Sqids) — der Fremdkunden-Schritt erscheint nur bei Kunden, die welche haben.
     *
     * @return array{customers: \Illuminate\Database\Eloquent\Collection<int, Customer>, foreignByCustomer: array<string, list<array{sqid: string, name: string}>>}
     */
    private function holderPicker(): array {
        $foreignByCustomer = [];
        foreach (ForeignCustomer::query()->whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'customer_id']) as $foreign) {
            $foreignByCustomer[Sqid::encode(Customer::class, (int) $foreign->customer_id)][] = ['sqid' => $foreign->sqid, 'name' => (string) $foreign->name];
        }

        return [
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'foreignByCustomer' => $foreignByCustomer,
        ];
    }

    private function contractsEnabled(): bool {
        return app(FeatureFlagResolver::class)->isEnabled('module.contracts');
    }

    /**
     * Verträge (079) zur Auswahl im Dialog: laufende Kundenverträge der
     * Organisation, dazu der bereits verknüpfte (auch beendet, sonst ginge die
     * Verknüpfung beim Speichern verloren) — ohne Modul keine Auswahl.
     *
     * @return Collection<int, Contract>
     */
    private function contractOptions(?ResaleSubscription $subscription): Collection {
        if (! $this->contractsEnabled()) {
            return new Collection;
        }

        return Contract::query()
            ->with('customer:id,name')
            ->where(function (Builder $q) use ($subscription): void {
                $q->where(function (Builder $running): void {
                    $running->where('partner_type', ContractPartnerType::Customer->value)->open();
                });
                if ($subscription?->contract_id !== null) {
                    $q->orWhere('id', $subscription->contract_id);
                }
            })
            ->orderBy('number')
            ->get(['id', 'number', 'title', 'customer_id']);
    }

    /**
     * @param  array<string, mixed>  $prefill
     */
    private function dialog(?ResaleSubscription $subscription, array $prefill): View {
        $subscription?->loadMissing('parent');

        return view('finance.resale._form_dialog', [
            'subscription' => $subscription,
            'prefill' => $prefill,
            'locked' => SaveResaleSubscriptionRequest::lockedFieldsFor($subscription),
            'contracts' => $this->contractOptions($subscription),
            'articles' => Article::query()->where('sellable', true)->orderBy('name')->get(['id', 'number', 'name']),
            'lexofficeArticles' => LexofficeArticle::query()->active()->orderBy('name')->get(['id', 'article_number', 'name', 'unit_name', 'net_unit_price', 'currency']),
            'kinds' => SubscriptionKind::cases(),
            // Domains führt der Domain-Sync; von Hand ist der Anbieter nie wählbar (B7).
            'providers' => array_values(array_filter(SubscriptionProvider::cases(), static fn(SubscriptionProvider $p): bool => $p !== SubscriptionProvider::DomainReselling)),
            'statuses' => SubscriptionStatus::cases(),
            'intervals' => BillingFrequency::cases(),
            'renewals' => RenewalMode::cases(),
        ] + $this->holderPicker());
    }
}
