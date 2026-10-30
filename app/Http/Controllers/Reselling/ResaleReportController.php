<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reselling;

use App\Enums\Reselling\{PeriodStatus, ResaleArticleRole};
use App\Http\Controllers\Concerns\{ResolvesCurrentOrganization, ResolvesGlobalDateRange};
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, WritesReportCsv};
use App\Http\Requests\Reselling\{ResaleAutoDraftSettingsRequest, ResaleReportDraftRequest, ResaleReportProductRequest};
use App\Models\Customer\Customer;
use App\Models\Reselling\{ResaleArticleClassification, ResalePeriod, ResalePlanLink, ResaleSubscription};
use App\Services\Platform\Catalog\{ArticleCatalog, CatalogArticle};
use App\Services\Reselling\Plan\RecurringPlans;
use App\Services\Reselling\Register\{DomainSubscriptionSync, LicenseArticleClassifier, ResaleDraftRun, ResaleInvoiceDraftService, ResaleMarginReport, ResalePriceCheck, ResaleRenewalReport, ResaleUnbilledReport};
use App\Settings\SettingScope;
use App\Support\{CsvExport, Setting, XlsxExport};
use App\Support\ErrorText;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\{Response, StreamedResponse};

/**
 * Überblick (Feature 152, MVP-765): Margenbericht, Verlängerungen und „Abo
 * ohne Rechnung" als Reiter mit CSV/XLSX (Marge auch PDF über die
 * Report-Pipeline von Feature 002), der Rechnungsvorschlag (MVP-764) als
 * CSV/XLSX und als Lexoffice-/lokaler Entwurf, dazu Preisprüfung und
 * Produkt-Einstufung. Die Aggregation lebt in den Register-Services.
 */
class ResaleReportController extends Controller {
    use RendersReportPdf;
    use ResolvesCurrentOrganization;
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    private const EXPORT_FORMATS = ['csv', 'xlsx'];

    /** Margenbericht je Produkt und je Rechnungsempfänger — fällige Perioden mit Beginn im Zeitraum. */
    public function index(Request $request, ResaleMarginReport $margins): View {
        $today = ResalePeriod::today();
        [$from, $to] = $this->marginRange($request, $margins, $today);
        $report = $margins->build($from, $to);

        return view('finance.resale.report', [
            'tab' => 'margin',
            'report' => $report,
            'today' => $today,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /** Margenbericht als CSV, XLSX oder PDF (Report-Pipeline 002, ohne Diagramme). */
    public function marginExport(Request $request, string $format, ResaleMarginReport $margins): Response {
        $today = ResalePeriod::today();
        [$from, $to] = $this->marginRange($request, $margins, $today);
        $report = $margins->build($from, $to);
        $filename = (string) __('resale.margin.file') . '-' . $from->toDateString() . '_' . $to->toDateString();
        if ($format === 'pdf') {
            return $this->pdfDownload('finance.resale.report_pdf', [
                'report' => $report,
                'today' => $today,
                'from' => $from,
                'to' => $to,
            ], $filename . '.pdf', 'landscape', $request, 'resale.margin', ['from' => $from->toDateString(), 'to' => $to->toDateString()]);
        }
        $export = $margins->exportRows($report);

        return $this->tabular($request, 'resale.margin', ['from' => $from->toDateString(), 'to' => $to->toDateString()], $format, $filename, $export['header'], $export['rows']);
    }

    /** Verlängerungen: Abos, die sich im Zeitraum verlängern oder enden (Kacheln 30/60/90 Tage). */
    public function renewals(Request $request, ResaleRenewalReport $renewals): View {
        $today = ResalePeriod::today();
        [$from, $to] = $this->renewalRange($request, $today);
        $result = $renewals->build($from, $to, $today);

        return view('finance.resale.report', [
            'tab' => 'renewals',
            'rows' => $result['rows'],
            'buckets' => $result['buckets'],
            'today' => $today,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function renewalsExport(Request $request, string $format, ResaleRenewalReport $renewals): Response {
        $today = ResalePeriod::today();
        [$from, $to] = $this->renewalRange($request, $today);
        $export = $renewals->exportRows($renewals->build($from, $to, $today)['rows']);

        return $this->tabular($request, 'resale.renewals', ['from' => $from->toDateString(), 'to' => $to->toDateString()], $format, (string) __('resale.renewals.file') . '-' . $from->toDateString() . '_' . $to->toDateString(), $export['header'], $export['rows']);
    }

    /** „Abo ohne Rechnung > N Tage" (Prozesse 6): älteste offene fällige Periode älter als N Tage. */
    public function unbilled(Request $request, ResaleUnbilledReport $unbilled): View {
        $today = ResalePeriod::today();
        $days = $this->unbilledDays($request);

        return view('finance.resale.report', [
            'tab' => 'unbilled',
            'rows' => $unbilled->build($days, $today),
            'days' => $days,
            'today' => $today,
        ]);
    }

    public function unbilledExport(Request $request, string $format, ResaleUnbilledReport $unbilled): Response {
        $days = $this->unbilledDays($request);
        $export = $unbilled->exportRows($unbilled->build($days, ResalePeriod::today()));

        return $this->tabular($request, 'resale.unbilled', ['days' => $days], $format, (string) __('resale.unbilled.file') . '-' . $days, $export['header'], $export['rows']);
    }

    /** Offene Perioden als Rechnungsvorschlag (CSV, eine Zeile je Periode). */
    public function export(Request $request): StreamedResponse {
        [$header, $rows, $filename] = $this->proposalRows();
        $this->auditExport($request, 'resale.proposal', 'csv', []);

        return CsvExport::streamFromRows($filename . '.csv', $header, $rows);
    }

    /** Derselbe Rechnungsvorschlag als XLSX (Review 2026-09-10, A5). */
    public function exportXlsx(Request $request): StreamedResponse {
        [$header, $rows, $filename] = $this->proposalRows();
        $this->auditExport($request, 'resale.proposal', 'xlsx', []);

        return XlsxExport::streamFromArray($filename . '.xlsx', $header, $rows);
    }

    /**
     * Preisprüfung (aus Feature 151 übernommen): je Produkt Einkauf laut
     * Vertrag, aktueller Katalogpreis und UVP gegen die Verkaufspreise.
     */
    public function prices(ResalePriceCheck $check): View {
        $today = ResalePeriod::today();
        $result = $check->build($today);

        return view('finance.resale.prices', ['rows' => $result['rows'], 'today' => $today, 'catalogDate' => $result['catalog_date']]);
    }

    /**
     * Produkt-Einstufung: welche Katalogartikel (Artikelstamm, Lexoffice, …)
     * Abo-Produkte sind — erkannt über den Namen, vom Betreiber übersteuerbar
     * (nie Abo-Position / immer).
     */
    public const PRODUCT_SCOPES = ['subscriptions', 'all', 'unclassified'];

    /** Einstufung mit Filter (Feature 185, MVP-1417): Standard sind Abo-Produkte und Artikel mit Abos; dazu „in Vorlagen" aus der Plan-Quelle. */
    public function products(Request $request, LicenseArticleClassifier $classifier, ArticleCatalog $catalog, RecurringPlans $plans): View {
        $organization = $this->currentOrganizationOrAbort(404);
        $organizationId = (int) $organization->id;
        $filters = [
            'scope' => in_array((string) $request->query('scope', ''), self::PRODUCT_SCOPES, true) ? (string) $request->query('scope') : 'subscriptions',
            'q' => trim((string) $request->query('q', '')),
        ];
        $counts = ResaleSubscription::query()->whereNotNull('article_ref')->selectRaw('article_ref, COUNT(*) AS n')->groupBy('article_ref')->pluck('n', 'article_ref')->all();
        $roles = $classifier->roles($organizationId);
        /** @var array<string, int> $planCounts Artikelschlüssel → Positionen in aktiven Serienvorlagen */
        $planCounts = [];
        $plansAvailable = $plans->availableFor($organization);
        if ($plansAvailable) {
            foreach ($plans->templatesFor($organization) as $template) {
                if (! $template->isActive()) {
                    continue;
                }
                foreach ($template->lines as $line) {
                    if ($line->articleKey !== null) {
                        $planCounts[$line->articleKey] = ($planCounts[$line->articleKey] ?? 0) + 1;
                    }
                }
            }
        }
        $q = mb_strtolower($filters['q']);
        $rows = collect($catalog->active($organizationId))
            ->map(static fn (CatalogArticle $article): array => [
                'article' => $article,
                'role' => $roles[$article->key] ?? null,
                'detected' => $classifier->detected($article->name),
                'effective' => $classifier->isLicense($organizationId, $article->key, $article->name),
                'subscriptions' => (int) ($counts[$article->key] ?? 0),
                'plans' => (int) ($planCounts[$article->key] ?? 0),
            ])
            ->filter(static fn (array $row): bool => match ($filters['scope']) {
                'subscriptions' => $row['effective'] || $row['subscriptions'] > 0 || $row['plans'] > 0,
                'unclassified' => $row['role'] === null,
                default => true,
            })
            ->filter(static fn (array $row): bool => $q === '' || str_contains(mb_strtolower($row['article']->name), $q) || str_contains(mb_strtolower((string) $row['article']->number), $q))
            ->sortBy(static fn (array $row): string => ($row['effective'] ? '0' : '1') . mb_strtolower($row['article']->name))->values();

        return view('finance.resale.products', [
            'rows' => $rows,
            'roles' => ResaleArticleRole::cases(),
            'filters' => $filters,
            'scopes' => self::PRODUCT_SCOPES,
            'plansAvailable' => $plansAvailable,
        ]);
    }

    /**
     * Dialog „Abrechnung" (Feature 185, MVP-1405): bei lokaler Rechnungshoheit
     * Schalter und Vorlauf der Serienrechnung, bei externer Hoheit der Hinweis,
     * welches Programm die Rechnungen führt, und Schalter und Vorlauf des
     * monatlichen Serienlaufs nach außen (MVP-1438).
     */
    public function billingSettings(ResaleDraftRun $autoDrafts, \App\Services\Billing\BillingModeResolver $modes): View {
        $organization = $this->currentOrganizationOrAbort(404);
        $mode = $modes->forOrganization($organization);

        return view('finance.resale._billing_dialog', [
            'mode' => $mode,
            'external' => $mode->isExternal(),
            'autoDrafts' => $autoDrafts->enabledFor($organization),
            'autoDraftLeadDays' => $autoDrafts->leadDaysFor($organization),
            'externalDrafts' => $autoDrafts->enabledFor($organization, true),
            'externalLeadDays' => $autoDrafts->leadDaysFor($organization, true),
            'domainPriceClass' => DomainSubscriptionSync::directPriceClass($organization),
            'domainPriceClasses' => DomainSubscriptionSync::knownPriceClasses($organization),
        ]);
    }

    /**
     * Serienrechnung (Feature 152): Schalter und Vorlauf je Organisation über
     * die Settings-Registry (validiert, auditiert) — den Lauf macht der
     * Zeitplan: `resale:draft-local` bei lokaler Hoheit, `resale:draft-external`
     * bei externer (MVP-1438). Gespeichert wird das Paar der Hoheit der
     * Organisation; der Dialog zeigt nur dieses.
     */
    public function autoDraftSettingsStore(ResaleAutoDraftSettingsRequest $request, \App\Services\Billing\BillingModeResolver $modes): RedirectResponse {
        $organization = $this->currentOrganizationOrAbort(404);
        $userId = $request->user()?->id;
        $mode = $modes->forOrganization($organization);
        if ($mode->isExternal()) {
            Setting::set(ResaleDraftRun::SETTING_EXTERNAL_ENABLED, $request->externalEnabled(), SettingScope::Organization, $organization, $userId);
            Setting::set(ResaleDraftRun::SETTING_EXTERNAL_LEAD_DAYS, $request->externalLeadDays(), SettingScope::Organization, $organization, $userId);
            $flash = __($request->externalEnabled() ? 'resale.auto_draft.flash.external_enabled' : 'resale.auto_draft.flash.external_disabled', ['days' => $request->externalLeadDays(), 'program' => $mode->programName()]);
        } else {
            Setting::set(ResaleDraftRun::SETTING_ENABLED, $request->enabled(), SettingScope::Organization, $organization, $userId);
            Setting::set(ResaleDraftRun::SETTING_LEAD_DAYS, $request->leadDays(), SettingScope::Organization, $organization, $userId);
            $flash = __($request->enabled() ? 'resale.auto_draft.flash.enabled' : 'resale.auto_draft.flash.disabled', ['days' => $request->leadDays()]);
        }
        Setting::set(DomainSubscriptionSync::SETTING_DIRECT_PRICE_CLASS, $request->domainPriceClass(), SettingScope::Organization, $organization, $userId);

        return redirect()->toList('finance.resale.index')->with('success', $flash);
    }

    public function productsStore(ResaleReportProductRequest $request): RedirectResponse {
        $organization = $this->currentOrganizationOrAbort(404);
        $article = $request->catalogArticle() ?? abort(404);
        $role = $request->role();
        $existing = ResaleArticleClassification::query()->where('article_ref', $article->key)->first();
        if ($role === null) {
            $existing?->delete();
        } else {
            $existing ??= new ResaleArticleClassification(['organization_id' => $organization->id, 'article_ref' => $article->key, 'created_by' => $request->user()?->id]);
            $existing->fill(['role' => $role, 'updated_by' => $request->user()?->id])->save();
        }

        return redirect()->toList('finance.resale.products')->with('success', __('resale.products.flash.saved', ['article' => $article->name, 'role' => $role?->label() ?? __('resale.products.role.auto')]));
    }

    /**
     * Vorlagen-Vorschlag (Feature 185, MVP-1440): was je Rechnungsempfänger mit
     * externer Hoheit in eine Serienvorlage seines Programms gehört — die
     * Lexoffice-API liest Vorlagen nur, angelegt wird dort von Hand. Je Abo
     * ohne Zuordnung zu einer Vorlage: Artikel, Positionstext, Menge, Einheit,
     * Einzelpreis, Intervall und erste Ausführung (nächster offener
     * Periodenbeginn; liegt er zurück, „fällig seit"). Empfänger, deren
     * Rechnungen der Serienlauf des Registers entwirft, sind markiert.
     */
    public function planProposal(ResaleDraftRun $run, \App\Services\Billing\BillingModeResolver $modes): View {
        $organization = $this->currentOrganizationOrAbort(404);
        $today = ResalePeriod::today();
        $linked = array_map('intval', ResalePlanLink::query()->distinct()->pluck('subscription_id')->all());
        $subscriptions = ResaleSubscription::query()->planning()
            ->where('is_own_holding', false)
            ->where(static fn(Builder $w) => $w->whereNotNull('customer_id')->orWhereNotNull('foreign_customer_id'))
            ->when($linked !== [], static fn(Builder $q) => $q->whereNotIn('id', $linked))
            ->with(['customer:id,name,currency,billing_mode', 'foreignCustomer:id,name,customer_id', 'foreignCustomer.customer:id,name,currency,billing_mode'])
            ->with(['periods' => static fn($p) => $p->whereIn('status', [PeriodStatus::Open->value, PeriodStatus::Partial->value])->orderBy('starts_on')])
            ->get();
        ResaleSubscription::withCatalogArticles($subscriptions);
        /** @var array<int, array{recipient: Customer, covered: bool, rows: list<array{subscription: ResaleSubscription, product: string, article: string|null, description: string, quantity: string, unit: string, unit_price: Money|null, interval: string, first: CarbonImmutable|null, overdue: bool}>}> $groups */
        $groups = [];
        foreach ($subscriptions as $subscription) {
            $recipient = $subscription->billedTo();
            if ($recipient === null || ! $modes->effectiveFor($recipient)->isExternal()) {
                continue;
            }
            $groups[$recipient->id] ??= ['recipient' => $recipient, 'covered' => $run->coversRecipient($organization, $recipient), 'rows' => []];
            $article = $subscription->catalogArticle();
            $monthly = $article !== null && \App\Services\Reselling\Register\LicenseMonths::isMonthUnit($article->unitName);
            $term = max(1, (int) $subscription->term_months);
            $sale = $subscription->sale_unit_price;
            $next = $subscription->periods->first(static fn(ResalePeriod $p): bool => ! $p->starts_on->lessThan($today)) ?? $subscription->periods->first();
            $holder = $subscription->foreignCustomer !== null ? (string) __('resale.draft.end_customer', ['name' => $subscription->foreignCustomer->name]) . ' · ' : '';
            $groups[$recipient->id]['rows'][] = [
                'subscription' => $subscription,
                'product' => $subscription->productName(),
                'article' => $article?->label(),
                'description' => $holder . $subscription->label,
                'quantity' => NumberHelper::toGermanFormat($monthly ? $subscription->quantity * $term : $subscription->quantity, 0),
                'unit' => $monthly ? 'Monat' : (string) __($term === 12 && (int) $subscription->quantity === 1 ? 'resale.draft.unit_year' : 'resale.draft.unit_piece'),
                'unit_price' => $sale === null ? null : ($monthly ? $sale->dividedBy($term)->withScale(2) : $sale->withScale(2)),
                'interval' => match ($term) {
                    1 => (string) __('resale.plan.interval.MONTHLY'),
                    3 => (string) __('resale.plan.interval.QUARTERLY'),
                    6 => (string) __('resale.plan.interval.BIANNUALLY'),
                    12 => (string) __('resale.plan.interval.ANNUALLY'),
                    default => (string) __('resale.plan.proposal.every_months', ['months' => $term]),
                },
                'first' => $next?->starts_on,
                'overdue' => $next !== null && $next->starts_on->lessThan($today),
            ];
        }
        usort($groups, static fn(array $a, array $b): int => strcmp($a['recipient']->name, $b['recipient']->name));
        foreach ($groups as &$group) {
            usort($group['rows'], static fn(array $a, array $b): int => strcmp($a['product'] . $a['description'], $b['product'] . $b['description']));
        }
        unset($group);

        return view('finance.resale.plan_proposal', ['groups' => $groups, 'today' => $today]);
    }

    /**
     * Dialog „Rechnungsentwurf": Empfänger mit offenen Perioden (ohne die
     * schon gestempelten) — und je Empfänger, was bereits in einem Entwurf
     * steht, damit niemand einen zweiten anlegt (Review 2026-09-10, A4).
     * Dieselben Regeln wie `ResaleInvoiceDraftService::openPeriodsFor()`/
     * `draftedPeriodsFor()`, hier in einer Abfrage über alle Empfänger.
     */
    public function draftCreate(Request $request, ResaleInvoiceDraftService $drafts): View {
        $today = ResalePeriod::today();
        // Stichtag (MVP-1437): Perioden, die bis dahin beginnen — heute, oder Jahre voraus für Vorauszahlungen;
        // die Perioden dafür legt der Planer an, damit der Dialog zählt, was der Entwurf bekäme.
        $until = ResaleInvoiceDraftService::referenceFrom((string) $request->query('until', ''), $today);
        $drafts->planAhead($until);
        /** @var array<int, array{customer: Customer, periods: int, net: Money, drafted: int, draft_reference: string|null, draft_created_at: CarbonImmutable|null}> $recipients */
        $recipients = [];
        $periods = ResalePeriod::query()
            ->whereIn('status', [PeriodStatus::Open->value, PeriodStatus::Partial->value])
            ->where('starts_on', '<', DateRange::dayAfter($until))
            ->whereHas('subscription', static fn(Builder $s) => $s->where('is_own_holding', false))
            ->with(['subscription.customer:id,name,currency', 'subscription.foreignCustomer:id,name,customer_id', 'subscription.foreignCustomer.customer:id,name,currency', 'links'])
            ->get();
        foreach ($periods as $period) {
            $recipient = $period->subscription->billedTo();
            if ($recipient === null) {
                continue;
            }
            $entry = $recipients[$recipient->id] ?? ['customer' => $recipient, 'periods' => 0, 'net' => Money::zero($period->currency, 2), 'drafted' => 0, 'draft_reference' => null, 'draft_created_at' => null];
            if ($period->draft_reference !== null && $period->draft_reference !== '') {
                $entry['drafted']++;
                $entry['draft_reference'] ??= $period->draft_reference;
                $entry['draft_created_at'] ??= $period->draft_created_at;
            } else {
                // Ohne Verkaufspreis oder ohne offene Monate entsteht keine Position (ResaleInvoiceDraftService::lineFor).
                $amount = $period->openAmount();
                if ($amount !== null && $period->openMonths() > 0.001) {
                    $entry['periods']++;
                    if ($amount->isSameCurrency($entry['net'])) {
                        $entry['net'] = $entry['net']->plus($amount);
                    }
                }
            }
            $recipients[$recipient->id] = $entry;
        }
        $open = array_values(array_filter($recipients, static fn(array $row): bool => $row['periods'] > 0));
        $drafted = array_values(array_filter($recipients, static fn(array $row): bool => $row['periods'] === 0 && $row['drafted'] > 0));
        $byName = static fn(array $a, array $b): int => strcmp($a['customer']->name, $b['customer']->name);
        usort($open, $byName);
        usort($drafted, $byName);

        return view('finance.resale._draft_dialog', ['recipients' => $open, 'drafted' => $drafted, 'until' => $until, 'today' => $today]);
    }

    /**
     * Entwurf anlegen. Fachliche Abbrüche des Services (Doppelklick, nichts
     * offen, Lexoffice inaktiv) sind übersetzte `RuntimeException`s und
     * landen als 422 im Dialog; alles andere wird protokolliert und generisch
     * gemeldet — nie ein API-Body oder Pfad im UI (Review 2026-09-10, C1/C9).
     */
    public function draftStore(ResaleReportDraftRequest $request, ResaleInvoiceDraftService $drafts): RedirectResponse {
        $recipient = $request->recipient();
        $organization = $this->currentOrganizationOrAbort(404);
        try {
            $result = $drafts->draft($organization, $recipient, $request->user(), $request->until());
        } catch (\Throwable $e) {
            if ($e::class === RuntimeException::class) {
                throw ValidationException::withMessages(['customer_id' => ErrorText::for($e)]);
            }
            Log::warning('resale: Rechnungsentwurf fehlgeschlagen', ['customer_id' => $recipient->id, 'exception' => $e::class, 'message' => $e->getMessage()]);
            throw ValidationException::withMessages(['customer_id' => (string) __('resale.draft_flash.failed')]);
        }

        $key = $result['local'] ? 'resale.draft_flash.created_local' : 'resale.draft_flash.created';
        $net = Money::ofFloat($result['net'], $recipient->currency, 2)->format();
        $target = \App\Enums\Finance\TransferTarget::tryFrom($result['target'])?->label() ?? $result['target'];

        return redirect()->toList('finance.resale.periods.index')->with('success', __($key, ['customer' => $recipient->name, 'lines' => $result['lines'], 'net' => $net, 'id' => $result['draft_id'], 'target' => $target]));
    }

    /**
     * Zeilen des Rechnungsvorschlags: offene und teilweise Perioden fremder
     * Halter, eine Zeile je Periode, Beträge als Dezimalstring.
     *
     * @return array{0: list<string>, 1: list<list<int|float|string|null>>, 2: string}
     */
    private function proposalRows(): array {
        $today = ResalePeriod::today();
        $periods = ResalePeriod::query()
            ->whereIn('status', [PeriodStatus::Open->value, PeriodStatus::Partial->value])
            ->where('starts_on', '<', DateRange::dayAfter($today))
            ->whereHas('subscription', static fn(Builder $s) => $s->where('is_own_holding', false))
            ->with(['subscription.customer:id,name', 'subscription.foreignCustomer:id,name,customer_id', 'subscription.foreignCustomer.customer:id,name', 'links'])
            ->get();
        ResaleSubscription::withCatalogArticles($periods->map(static fn (ResalePeriod $period): ResaleSubscription => $period->subscription));
        $periods = $periods
            ->sortBy([static fn(ResalePeriod $a, ResalePeriod $b): int => strcmp((string) $a->subscription->billedTo()?->name, (string) $b->subscription->billedTo()?->name) ?: $a->starts_on <=> $b->starts_on]);

        $header = [
            (string) __('resale.field.billed_to'), (string) __('resale.field.holder'), (string) __('resale.field.label'), (string) __('resale.field.article'),
            (string) __('resale.field.period'), (string) __('resale.field.quantity'), (string) __('resale.link.months'), (string) __('resale.export.open_months'),
            (string) __('resale.field.sale_unit_price'), (string) __('resale.export.open_amount'), (string) __('resale.margin.currency'), (string) __('resale.field.status'),
        ];
        $rows = [];
        foreach ($periods as $period) {
            $subscription = $period->subscription;
            $rows[] = [
                $subscription->billedTo()?->name,
                $subscription->holderLabel(),
                $subscription->label,
                $subscription->productLabel() ?? '',
                $period->label(),
                $period->quantity,
                NumberHelper::toGermanFormat($period->requiredMonths(), 2),
                NumberHelper::toGermanFormat($period->openMonths(), 2),
                $subscription->sale_unit_price?->withScale(2)->format(withSymbol: false, withThousandsSeparator: false) ?? '',
                $period->openAmount()?->format(withSymbol: false, withThousandsSeparator: false) ?? '',
                $period->currency->value,
                $period->status->label(),
            ];
        }

        return [$header, $rows, (string) __('resale.export_files.proposal') . '-' . $today->format('Y-m-d')];
    }

    /**
     * Zeitraum des Margenberichts: Periodenbeginn von … bis; Vorgabe = alles
     * bis heute (frühester Periodenbeginn der Organisation).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function marginRange(Request $request, ResaleMarginReport $margins, CarbonImmutable $today): array {
        return $this->resolveRangeWithDefault($request, static fn(): array => [$margins->earliestStart($today->startOfYear()), $today]);
    }

    /**
     * Zeitraum des Verlängerungsberichts: Vorgabe heute bis heute + 90 Tage.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function renewalRange(Request $request, CarbonImmutable $today): array {
        return $this->resolveRangeWithDefault($request, static fn(): array => [$today, $today->addDays(max(ResaleRenewalReport::BUCKETS))]);
    }

    private function unbilledDays(Request $request): int {
        return min(3650, max(0, $request->integer('days', ResaleUnbilledReport::DEFAULT_DAYS)));
    }

    /**
     * CSV oder XLSX mit Export-Audit wie beim PDF (Phase 137, MVP-1101).
     *
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $header
     * @param  list<list<int|float|string|null>>  $rows
     */
    private function tabular(Request $request, string $reportCode, array $filters, string $format, string $filename, array $header, array $rows): Response {
        abort_unless(in_array($format, self::EXPORT_FORMATS, true), 404);
        $this->auditExport($request, $reportCode, $format, $filters);
        if ($format === 'xlsx') {
            return XlsxExport::streamFromArray($filename . '.xlsx', $header, $rows);
        }

        return CsvExport::streamFromRows($filename . '.csv', $header, $rows);
    }

    /** Fachbericht außerhalb der Auswertungen: Den Export deckt das Fachrecht der Seite (E28). */
    protected function exportNeedsReportPermission(): bool {
        return false;
    }
}
