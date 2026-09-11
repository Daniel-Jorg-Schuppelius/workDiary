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

namespace App\Http\Controllers\Finance;

use App\Enums\Article\ArticleStatus;
use App\Enums\Reselling\{PeriodStatus, ResaleArticleRole};
use App\Http\Controllers\Concerns\{ResolvesCurrentOrganization, ResolvesGlobalDateRange};
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, WritesReportCsv};
use App\Http\Requests\Finance\{ResaleAutoDraftSettingsRequest, ResaleReportDraftRequest, ResaleReportProductRequest};
use App\Models\{Article, Customer, LexofficeArticle};
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use App\Services\Reselling\Register\{LicenseArticleClassifier, ResaleInvoiceDraftService, ResaleLocalDraftRun, ResaleMarginReport, ResalePriceCheck, ResaleRenewalReport, ResaleUnbilledReport};
use App\Settings\SettingScope;
use App\Support\{CsvExport, Setting, XlsxExport};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
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

        return $this->tabular($format, $filename, $export['header'], $export['rows']);
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

        return $this->tabular($format, (string) __('resale.renewals.file') . '-' . $from->toDateString() . '_' . $to->toDateString(), $export['header'], $export['rows']);
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

        return $this->tabular($format, (string) __('resale.unbilled.file') . '-' . $days, $export['header'], $export['rows']);
    }

    /** Offene Perioden als Rechnungsvorschlag (CSV, eine Zeile je Periode). */
    public function export(): StreamedResponse {
        [$header, $rows, $filename] = $this->proposalRows();

        return CsvExport::streamFromRows($filename . '.csv', $header, $rows);
    }

    /** Derselbe Rechnungsvorschlag als XLSX (Review 2026-09-10, A5). */
    public function exportXlsx(): StreamedResponse {
        [$header, $rows, $filename] = $this->proposalRows();

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
     * Produkt-Einstufung: welche Lexoffice-Artikel Abo-Produkte sind — erkannt
     * über den Namen, vom Betreiber übersteuerbar (nie Abo-Position / immer).
     */
    public function products(LicenseArticleClassifier $classifier, ResaleLocalDraftRun $autoDrafts): View {
        $organization = $this->currentOrganizationOrNull();
        $counts = ResaleSubscription::query()->whereNotNull('lexoffice_article_id')->selectRaw('lexoffice_article_id, COUNT(*) AS n')->groupBy('lexoffice_article_id')->pluck('n', 'lexoffice_article_id')->all();
        $articles = LexofficeArticle::query()->active()->orderBy('name')->get()
            ->map(static fn(LexofficeArticle $article): array => [
                'article' => $article,
                'detected' => $classifier->detected($article),
                'effective' => $classifier->isLicense($article),
                'subscriptions' => (int) ($counts[$article->id] ?? 0),
            ])
            ->sortBy(static fn(array $row): string => ($row['effective'] ? '0' : '1') . mb_strtolower((string) $row['article']->name))->values();
        // Lokale Artikel (Review 2026-09-11): dieselbe Einstufung für den Belegspiegel lokaler Rechnungen.
        $localCounts = ResaleSubscription::query()->whereNotNull('article_id')->selectRaw('article_id, COUNT(*) AS n')->groupBy('article_id')->pluck('n', 'article_id')->all();
        $localArticles = Article::query()->where('status', ArticleStatus::Active->value)->orderBy('name')->get()
            ->map(static fn(Article $article): array => [
                'article' => $article,
                'detected' => $classifier->detected($article),
                'effective' => $classifier->isLicense($article),
                'subscriptions' => (int) ($localCounts[$article->id] ?? 0),
            ])
            ->sortBy(static fn(array $row): string => ($row['effective'] ? '0' : '1') . mb_strtolower((string) $row['article']->name))->values();

        return view('finance.resale.products', [
            'rows' => $articles,
            'localRows' => $localArticles,
            'roles' => ResaleArticleRole::cases(),
            // Serienrechnung (lokale Rechnungshoheit): Org-Schalter + Vorlauf, Lauf = resale:draft-local.
            'autoDrafts' => $organization !== null && $autoDrafts->enabledFor($organization),
            'autoDraftLeadDays' => $organization !== null ? $autoDrafts->leadDaysFor($organization) : 0,
        ]);
    }

    /**
     * Serienrechnung (Feature 152): Schalter und Vorlauf je Organisation über
     * die Settings-Registry (validiert, auditiert) — den Lauf macht der
     * Zeitplan (`resale:draft-local`), nur bei lokaler Rechnungshoheit.
     */
    public function autoDraftSettingsStore(ResaleAutoDraftSettingsRequest $request): RedirectResponse {
        $organization = $this->currentOrganizationOrAbort(404);
        $userId = $request->user()?->id;
        Setting::set(ResaleLocalDraftRun::SETTING_ENABLED, $request->enabled(), SettingScope::Organization, $organization, $userId);
        Setting::set(ResaleLocalDraftRun::SETTING_LEAD_DAYS, $request->leadDays(), SettingScope::Organization, $organization, $userId);

        return redirect()->route('finance.resale.products')->with('success', __($request->enabled() ? 'resale.auto_draft.flash.enabled' : 'resale.auto_draft.flash.disabled', ['days' => $request->leadDays()]));
    }

    public function productsStore(ResaleReportProductRequest $request): RedirectResponse {
        $article = $request->article();
        $role = $request->role();
        $article->forceFill(['resale_role' => $role])->save();

        return redirect()->route('finance.resale.products')->with('success', __('resale.products.flash.saved', ['article' => $article->name, 'role' => $role?->label() ?? __('resale.products.role.auto')]));
    }

    /**
     * Dialog „Rechnungsentwurf": Empfänger mit offenen Perioden (ohne die
     * schon gestempelten) — und je Empfänger, was bereits in einem Entwurf
     * steht, damit niemand einen zweiten anlegt (Review 2026-09-10, A4).
     * Dieselben Regeln wie `ResaleInvoiceDraftService::openPeriodsFor()`/
     * `draftedPeriodsFor()`, hier in einer Abfrage über alle Empfänger.
     */
    public function draftCreate(): View {
        $today = ResalePeriod::today();
        /** @var array<int, array{customer: Customer, periods: int, net: Money, drafted: int, draft_reference: string|null, draft_created_at: CarbonImmutable|null}> $recipients */
        $recipients = [];
        $periods = ResalePeriod::query()
            ->whereIn('status', [PeriodStatus::Open->value, PeriodStatus::Partial->value])
            ->where('starts_on', '<', DateRange::dayAfter($today))
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

        return view('finance.resale._draft_dialog', ['recipients' => $open, 'drafted' => $drafted]);
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
            $result = $drafts->draft($organization, $recipient, $request->user());
        } catch (\Throwable $e) {
            if ($e::class === RuntimeException::class) {
                throw ValidationException::withMessages(['customer_id' => $e->getMessage()]);
            }
            Log::warning('resale: Rechnungsentwurf fehlgeschlagen', ['customer_id' => $recipient->id, 'exception' => $e::class, 'message' => $e->getMessage()]);
            throw ValidationException::withMessages(['customer_id' => (string) __('resale.draft_flash.failed')]);
        }

        $key = $result['local'] ? 'resale.draft_flash.created_local' : 'resale.draft_flash.created';
        $net = Money::ofFloat($result['net'], $recipient->currency, 2)->format();

        return redirect()->route('finance.resale.periods.index')->with('success', __($key, ['customer' => $recipient->name, 'lines' => $result['lines'], 'net' => $net, 'id' => $result['draft_id']]));
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
            ->with(['subscription.customer:id,name', 'subscription.foreignCustomer:id,name,customer_id', 'subscription.foreignCustomer.customer:id,name', 'subscription.article', 'subscription.lexofficeArticle', 'links'])
            ->get()
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
                self::decimal($period->requiredMonths()),
                self::decimal($period->openMonths()),
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
     * @param  list<string>  $header
     * @param  list<list<int|float|string|null>>  $rows
     */
    private function tabular(string $format, string $filename, array $header, array $rows): Response {
        abort_unless(in_array($format, self::EXPORT_FORMATS, true), 404);
        if ($format === 'xlsx') {
            return XlsxExport::streamFromArray($filename . '.xlsx', $header, $rows);
        }

        return CsvExport::streamFromRows($filename . '.csv', $header, $rows);
    }

    private static function decimal(float $value): string {
        return number_format($value, 2, ',', '');
    }
}
