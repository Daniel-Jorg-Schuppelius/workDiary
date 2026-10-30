<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleInvoiceDraftService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Finance\{TransferChannel, TransferTarget};
use App\Enums\Reselling\{LinkOrigin, PeriodStatus, SubscriptionStatus};
use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Platform\{Organization, User};
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResaleSubscription};
use App\Services\Billing\BillingModeResolver;
use App\Services\Finance\{BillingTransferException, BillingTransferService};
use App\Services\Finance\Targets\FacturationTargetRegistry;
use App\Services\Reselling\Draft\{DraftResult, InvoiceDraftTarget, InvoiceDraftTargets, LocalInvoiceDraftTarget};
use App\Support\{ErrorText, Tz};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\DateTimeFormat;
use CommonToolkit\Helper\Data\DateHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Rechnungsvorschlag aus offenen Perioden (Feature 152, MVP-764 / Review
 * 2026-09-11): alle offenen Perioden eines Rechnungsempfängers werden zu
 * Positionen — eine je Abo und zusammenhängendem Zeitraum ({@see entries()},
 * MVP-1436), bei Partnern mit Endkundennennung, Menge in Monaten bei
 * Monatsartikeln. Ein Stichtag nimmt Perioden hinzu, die erst bis dahin
 * beginnen (Vorauszahlung mehrerer Jahre, MVP-1437). Wohin der Entwurf geht, entscheidet
 * die Rechnungshoheit ({@see BillingModeResolver}): lokal das
 * {@see LocalInvoiceDraftTarget}, extern das vom Plugin registrierte
 * {@see InvoiceDraftTarget}. Der Kern stempelt die Perioden
 * (`draft_reference`/`draft_created_at`) und legt Bezüge an, wenn das Ziel
 * Positionen liefert; nichts wird festgeschrieben.
 *
 * @phpstan-import-type DraftEntry from InvoiceDraftTarget
 * @phpstan-import-type DraftLine from InvoiceDraftTarget
 * @phpstan-type DraftSummary array{draft_id: string, lines: int, net: float, periods: int, local: bool, target: string, url: string|null, transfer_id?: int}
 */
final class ResaleInvoiceDraftService {
    /** Weiter als so viele Jahre reicht kein Stichtag — Vorauszahlungen darüber hinaus sind keine Praxis. */
    public const MAX_LEAD_YEARS = 5;

    public function __construct(
        private readonly BillingModeResolver $billingModes,
        private readonly InvoiceDraftTargets $targets,
        private readonly \App\Services\Reselling\Plan\RecurringPlans $plans,
        private readonly BillingTransferService $transfers,
        private readonly FacturationTargetRegistry $facturation,
        private readonly PeriodPlanner $planner,
    ) {}

    /**
     * Offene und teilweise Perioden je Rechnungsempfänger — ohne die, die
     * schon in einem Entwurf stehen (`draft_reference`).
     *
     * @return Collection<int, ResalePeriod>
     */
    public function openPeriodsFor(Customer $recipient, ?CarbonImmutable $reference = null): Collection {
        if ($reference !== null) {
            $this->planAhead($reference, $recipient);
        }

        return $this->withCatalogArticles($this->pendingPeriodsFor($recipient, $reference)->whereNull('draft_reference')->get());
    }

    /**
     * Perioden bis zum Stichtag anlegen (MVP-1437): der Planer sieht sonst nur
     * 90 Tage voraus, eine Vorauszahlung über mehrere Jahre braucht die
     * Perioden als Quellen. Geplant wird so, dass der Horizont am Stichtag
     * endet; was darüber hinaus entstünde, räumte der tägliche Lauf wieder ab.
     * Ohne Empfänger alle Abos mit Halter (Dialog), sonst nur seine.
     */
    public function planAhead(CarbonImmutable $until, ?Customer $recipient = null): void {
        $today = ResalePeriod::today();
        if (! $until->greaterThan($today->addDays(PeriodPlanner::HORIZON_DAYS))) {
            return;
        }
        $reference = $until->subDays(PeriodPlanner::HORIZON_DAYS)->max($today);
        $query = ResaleSubscription::query()
            ->where('is_own_holding', false)
            ->whereIn('status', array_map(static fn(SubscriptionStatus $s): string => $s->value, array_filter(SubscriptionStatus::cases(), static fn(SubscriptionStatus $s): bool => $s->isPlanning())));
        if ($recipient !== null) {
            $query->where($this->holderScope($recipient));
        }
        foreach ($query->get() as $subscription) {
            $this->planner->sync($subscription, $reference);
        }
    }

    /** Abos, deren Rechnung an den Empfänger geht: Kunde direkt oder Partner des Fremdkunden. */
    private function holderScope(Customer $recipient): \Closure {
        return static fn($w) => $w->where('customer_id', $recipient->id)
            ->orWhereIn('foreign_customer_id', ForeignCustomer::query()->where('customer_id', $recipient->id)->select('id'));
    }

    /**
     * Offene und teilweise Perioden des Empfängers, die bereits in einem
     * Entwurf stehen (zweiter Klick).
     *
     * @return Collection<int, ResalePeriod>
     */
    public function draftedPeriodsFor(Customer $recipient, ?CarbonImmutable $reference = null): Collection {
        return $this->withCatalogArticles($this->pendingPeriodsFor($recipient, $reference)->whereNotNull('draft_reference')->get());
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<ResalePeriod>
     */
    private function pendingPeriodsFor(Customer $recipient, ?CarbonImmutable $reference): \Illuminate\Database\Eloquent\Builder {
        $reference ??= ResalePeriod::today();

        // Perioden, deren Abo eine laufende Serienvorlage berechnet (Feature 185, MVP-1411), gehören nicht in den Entwurf.
        $planned = $recipient->organization !== null ? $this->plans->plannedSubscriptionIds($recipient->organization) : [];

        return ResalePeriod::query()
            ->whereIn('status', [PeriodStatus::Open->value, PeriodStatus::Partial->value])
            ->where('starts_on', '<', DateRange::dayAfter($reference))
            ->when($planned !== [], static fn($q) => $q->whereNotIn('subscription_id', $planned))
            ->whereHas('subscription', fn($s) => $s->where('is_own_holding', false)->where($this->holderScope($recipient)))
            ->with(['subscription.foreignCustomer', 'links'])
            ->orderBy('starts_on');
    }

    /**
     * Positionen des Entwurfs: je offener Periode mit Verkaufspreis eine
     * Zeile. Nichts offen, aber schon etwas im Entwurf → kein zweiter Entwurf.
     * `$reference` = Stichtag (Serienlauf mit Vorlauf); null = heute.
     *
     * @return list<DraftEntry>
     *
     * @throws RuntimeException
     */
    public function openEntries(Customer $recipient, ?CarbonImmutable $reference = null): array {
        return $this->openLines($recipient, $reference);
    }

    /**
     * @return list<DraftEntry>
     *
     * @throws RuntimeException
     */
    private function openLines(Customer $recipient, ?CarbonImmutable $reference = null): array {
        $lines = $this->entries($this->openPeriodsFor($recipient, $reference));
        if ($lines !== []) {
            return $lines;
        }
        $drafted = $this->draftedPeriodsFor($recipient, $reference)->first();
        if ($drafted !== null) {
            $date = $drafted->draft_created_at !== null ? (string) Tz::toLocal($drafted->draft_created_at)?->format('d.m.Y') : '';
            throw new RuntimeException((string) __('resale.draft.already_drafted', ['reference' => (string) $drafted->draft_reference, 'date' => $date]));
        }

        throw new RuntimeException((string) __('resale.draft.error.nothing_open'));
    }

    /**
     * Rechnungsvorschlag je nach Rechnungshoheit des Empfängers: lokal der
     * Rechnungsentwurf, extern eine Übergabe (Feature 045) an das Ziel des
     * Buchhaltungsprogramms — angelegt, bestätigt und übertragen in einem
     * Schritt (Feature 185, MVP-1423). Die Perioden tragen danach die
     * Belegkennung als Stempel; der Spiegel schließt ihn, sobald der Beleg
     * abgeschlossen ist.
     *
     * @return DraftSummary
     *
     * @throws RuntimeException nichts offen / Entwurf steht aus / kein Ziel für die externe Hoheit / Übertragung gescheitert
     */
    public function draft(Organization $organization, Customer $recipient, ?User $user = null, ?CarbonImmutable $reference = null): array {
        $mode = $this->billingModes->effectiveFor($recipient);
        if (! $mode->isExternal()) {
            return $this->draftLocal($organization, $recipient, $user, $reference);
        }

        return $this->transfer($organization, $recipient, $this->transferTargetFor($recipient), $user, $reference);
    }

    /**
     * Stichtag aus einer Eingabe (MVP-1437): ein gültiges Datum `Y-m-d`, nicht
     * vor heute und höchstens {@see MAX_LEAD_YEARS} voraus — sonst heute.
     */
    public static function referenceFrom(?string $raw, ?CarbonImmutable $today = null): CarbonImmutable {
        $today ??= ResalePeriod::today();
        $raw = trim((string) $raw);
        // Toolkit-Datumsstrategie; der Rückvergleich fängt „2027-02-31“ ab, das die Normalisierung in den März rollt.
        $iso = $raw === '' ? null : DateHelper::normalizeToIso($raw, DateTimeFormat::ISO);
        $parsed = $iso === null ? null : CarbonImmutable::parse($iso)->startOfDay();
        if ($parsed === null || $parsed->format('Y-m-d') !== $raw || $parsed->lessThan($today)) {
            return $today;
        }

        return $parsed->min($today->addYears(self::MAX_LEAD_YEARS));
    }

    /**
     * Übergabeziel des Empfängers aus seiner Rechnungshoheit (Feature 045):
     * das Programm selbst, für DATEV das Datei-Paket — nur, wenn ein Adapter
     * das Ziel bedient (Plugin aktiv).
     *
     * @throws RuntimeException kein Ziel für die Hoheit
     */
    public function transferTargetFor(Customer $recipient): TransferTarget {
        $mode = $this->billingModes->effectiveFor($recipient);
        $target = $mode->transferTarget();
        try {
            $this->facturation->for($target);
        } catch (RuntimeException) {
            throw new RuntimeException((string) __('resale.draft.no_target', ['mode' => $mode->label()]));
        }

        return $target;
    }

    /**
     * Übergabe anlegen, bestätigen und übertragen. Scheitert das Ziel, bleibt
     * der Nachweis als „fehlgeschlagen“ stehen (erneut übertragbar in den
     * Finanzen) und die Perioden bleiben ungestempelt.
     *
     * @return DraftSummary
     */
    private function transfer(Organization $organization, Customer $recipient, TransferTarget $target, ?User $user, ?CarbonImmutable $reference = null): array {
        $entries = $this->openLines($recipient, $reference);
        $periodIds = [];
        $from = null;
        $to = null;
        foreach ($entries as $entry) {
            foreach ($entry['periods'] as $period) {
                $periodIds[] = (int) $period->id;
                $starts = $period->starts_on->toDateString();
                $ends = $period->ends_on->toDateString();
                $from = $from === null || $starts < $from ? $starts : $from;
                $to = $to === null || $ends > $to ? $ends : $to;
            }
        }
        try {
            $transfer = $this->transfers->createDraft($recipient, TransferChannel::Resale, $target, ['from' => $from, 'to' => $to], $periodIds, $user);
            $transfer = $this->transfers->confirm($transfer, $user);
        } catch (BillingTransferException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
        try {
            $result = $this->facturation->for($target)->transfer($transfer);
        } catch (\Throwable $e) {
            $this->transfers->markFailed($transfer, mb_substr($e->getMessage(), 0, 1000), $user);
            throw new RuntimeException((string) __('resale.draft.transfer_failed', ['target' => $target->label(), 'error' => ErrorText::for($e)]), 0, $e);
        }
        $transfer = $this->transfers->markTransferred($transfer, $result->externalReference, $result->filePath, $user);

        return [
            'draft_id' => ResalePeriodTransferSource::referenceOf($transfer),
            'lines' => (int) $transfer->position_count,
            'net' => round((float) $transfer->total_amount, 2),
            'periods' => self::periodCount($entries),
            'local' => false,
            'target' => $target->value,
            'url' => route('finance.transfers.show', $transfer),
            'transfer_id' => (int) $transfer->id,
        ];
    }

    /**
     * Lokales Ziel ohne Weiche — öffentlich für den Serienlauf
     * ({@see ResaleDraftRun}): ohne Nutzer, mit Stichtag inkl. Vorlauf;
     * die Hoheit prüft der Aufrufer, `draft()` bleibt der Weg mit Weiche.
     *
     * @return DraftSummary
     *
     * @throws RuntimeException nichts offen / Entwurf steht schon aus
     */
    public function draftLocal(Organization $organization, Customer $recipient, ?User $user, ?CarbonImmutable $reference = null): array {
        $target = $this->targets->local();
        if ($target === null) {
            throw new RuntimeException((string) __('resale.draft.no_target', ['mode' => LocalInvoiceDraftTarget::KEY]));
        }

        return $this->draftWith($target, $organization, $recipient, $user, $reference);
    }

    /**
     * Vorschau ohne Schreiben (Serienlauf `--dry-run`): dieselbe Auswahl und
     * dieselben Ausnahmen wie `draftLocal()` bzw. `draft()`.
     *
     * @return array{lines: int, net: float, periods: int}
     *
     * @throws RuntimeException nichts offen / Entwurf steht schon aus
     */
    public function preview(Customer $recipient, ?CarbonImmutable $reference = null): array {
        $lines = $this->openLines($recipient, $reference);
        $net = 0.0;
        foreach ($lines as $entry) {
            $net += $entry['line']['quantity'] * $entry['line']['unit_net'];
        }

        return ['lines' => count($lines), 'net' => round($net, 2), 'periods' => self::periodCount($lines)];
    }

    /**
     * Entwurf im Ziel anlegen und die Perioden stempeln: Bemerkung um das
     * Label ergänzt, `draft_reference` gesetzt; liefert das Ziel Positionen,
     * bekommt jede Periode einen vorgeschlagenen Bezug und gilt als berechnet
     * — beim Ausstellen bestätigt der Betreiber wie sonst auch.
     *
     * @return DraftSummary
     */
    private function draftWith(InvoiceDraftTarget $target, Organization $organization, Customer $recipient, ?User $user, ?CarbonImmutable $reference): array {
        $target->ensureAvailable($organization, $recipient);
        $entries = $this->openLines($recipient, $reference);
        $reference ??= ResalePeriod::today();

        return DB::transaction(function () use ($target, $organization, $recipient, $user, $reference, $entries): array {
            $result = $target->draft($organization, $recipient, $entries, $user, $reference);
            $today = Tz::now()->toDateString();
            foreach ($entries as $entry) {
                foreach ($entry['periods'] as $period) {
                    $this->stamp($organization, $recipient, $user, $period, $entry['line'], $result, $today);
                }
            }

            return [
                'draft_id' => $result->reference,
                'lines' => $result->lines,
                'net' => $result->net,
                'periods' => self::periodCount($entries),
                'local' => $target->key() === LocalInvoiceDraftTarget::KEY,
                'target' => $target->key(),
                'url' => $result->url,
            ];
        });
    }

    /**
     * @param  DraftLine  $line  Position, zu der die Periode gehört — ihr Anteil daran wird der Bezugsbetrag
     */
    private function stamp(Organization $organization, Customer $recipient, ?User $user, ResalePeriod $period, array $line, DraftResult $result, string $today): void {
        $attributes = ['draft_reference' => $result->reference, 'draft_created_at' => now()];
        $morphId = $result->morphIdFor((int) $period->id);
        if ($morphId !== null && $result->morphClass !== null) {
            $months = $period->openMonths();
            ResalePeriodLink::query()->create([
                'organization_id' => $organization->id,
                'period_id' => $period->id,
                'subscription_id' => $period->subscription_id,
                'linkable_type' => $result->morphClass,
                'linkable_id' => $morphId,
                'voucher_number' => $result->reference,
                'voucher_date' => $today,
                'quantity' => round($months / $period->termMonths(), 3),
                'months' => round($months, 2),
                'amount' => round($this->share($period, $this->isMonthly($period->subscription)) * $line['unit_net'], 2),
                'currency' => $recipient->currency->value,
                'origin' => LinkOrigin::Proposed,
                'note' => $result->label,
                'created_by_user_id' => $user?->id,
            ]);
            $attributes['status'] = PeriodStatus::Billed;
        }
        $period->appendNote($result->label);
        $period->forceFill($attributes)->save();
    }

    /**
     * Positionen aus offenen Perioden (MVP-1436): zusammenhängende, ganz offene
     * Perioden eines Abos werden eine Position („7 Jahr" für sieben Jahre einer
     * Domain, wie die Rechnungen des Inhabers bis 2023) — bei Monatsartikeln
     * immer, bei Jahresabos nur mit einer Lizenz: „6 Stück" für zwei Lizenzen
     * über drei Jahre läse der Spiegel als sechs Lizenzen. Teilweise gedeckte
     * Perioden und Lücken beginnen eine neue Position; Perioden ohne Preis oder
     * ohne offene Monate fehlen. Reihenfolge nach erstem Periodenbeginn.
     *
     * @param  Collection<int, ResalePeriod>  $periods
     * @return list<DraftEntry>
     */
    public function entries(Collection $periods): array {
        $this->withCatalogArticles($periods);
        /** @var list<non-empty-list<ResalePeriod>> $runs */
        $runs = [];
        /** @var array<int, int> $open Abo → Index der Position, die noch fortgesetzt werden kann */
        $open = [];
        foreach ($periods->sortBy([['subscription_id', 'asc'], ['starts_on', 'asc'], ['id', 'asc']]) as $period) {
            if ($this->lineFor($period) === null) {
                continue;
            }
            $index = $open[(int) $period->subscription_id] ?? null;
            if ($index !== null && $this->continues($runs[$index], $period)) {
                $runs[$index][] = $period;

                continue;
            }
            $runs[] = [$period];
            $open[(int) $period->subscription_id] = array_key_last($runs);
        }
        usort($runs, static fn(array $a, array $b): int => $a[0]->starts_on <=> $b[0]->starts_on ?: $a[0]->subscription_id <=> $b[0]->subscription_id ?: $a[0]->id <=> $b[0]->id);

        return array_map(fn(array $run): array => ['periods' => $run, 'line' => $this->lineForRun($run)], $runs);
    }

    /**
     * Setzt die Periode die Position fort: gleiches Abo und gleiche Laufzeit, lückenlos am Folgetag, beide zusammenfassbar?
     *
     * @param  non-empty-list<ResalePeriod>  $run
     */
    private function continues(array $run, ResalePeriod $next): bool {
        $last = $run[array_key_last($run)];
        if ((int) $last->subscription_id !== (int) $next->subscription_id || $last->termMonths() !== $next->termMonths()) {
            return false;
        }
        if (! $next->starts_on->isSameDay($last->ends_on->addDay())) {
            return false;
        }

        return $this->collapsible($last) && $this->collapsible($next);
    }

    /** Ganz offen und als Sammelposition eindeutig: Monatsartikel, oder eine Lizenz mit Jahreslaufzeit. */
    private function collapsible(ResalePeriod $period): bool {
        if ($period->coveredMonths() > 0.001) {
            return false;
        }

        return $this->isMonthly($period->subscription) || ($period->quantity === 1 && $period->termMonths() === 12);
    }

    /**
     * Position einer Periode für Entwurf und Übergabe (Feature 185, MVP-1422);
     * null ohne Verkaufspreis oder ohne offene Monate.
     *
     * @return DraftLine|null
     */
    public function lineFor(ResalePeriod $period): ?array {
        if ($period->subscription->sale_unit_price === null || $period->openMonths() <= 0.001) {
            return null;
        }

        return $this->lineForRun([$period]);
    }

    /**
     * Position einer zusammenhängenden Folge offener Perioden eines Abos: Menge in
     * Monaten (Monatsartikel) bzw. Jahren — Einheit „Jahr" bei einer Lizenz mit
     * Jahreslaufzeit, sonst „Stück" —, Zeitraum vom ersten Beginn bis zum letzten
     * Ende, Endkunde bei Partnern.
     *
     * @param  non-empty-list<ResalePeriod>  $periods
     * @return DraftLine
     */
    private function lineForRun(array $periods): array {
        $first = $periods[0];
        $last = $periods[array_key_last($periods)];
        $subscription = $first->subscription;
        $sale = $subscription->sale_unit_price?->toFloat() ?? 0.0;
        $termMonths = $first->termMonths();
        $monthly = $this->isMonthly($subscription);
        $quantity = 0.0;
        foreach ($periods as $period) {
            $quantity += $this->share($period, $monthly);
        }
        $yearly = ! $monthly && $termMonths === 12 && $first->quantity === 1;
        $holder = $subscription->foreignCustomer !== null ? (string) __('resale.draft.end_customer', ['name' => $subscription->foreignCustomer->name]) . ' · ' : '';
        $range = $first->starts_on->format('d.m.Y') . ' – ' . $last->ends_on->format('d.m.Y');

        return [
            'name' => $subscription->productName(),
            'description' => $holder . $range . ($first->quantity > 1 ? ' · ' . $first->quantity . ' × ' : ''),
            'quantity' => $monthly ? round($quantity, 2) : round($quantity, 3),
            'unit_name' => $monthly ? 'Monat' : (string) __($yearly ? 'resale.draft.unit_year' : 'resale.draft.unit_piece'),
            'unit_net' => $monthly ? round($sale / $termMonths, 4) : round($sale, 2),
            // Katalogschlüssel: das Ziel schreibt die Position mit Artikel, damit die Rechnung als Lizenzposition zurückkommt (Feature 185, MVP-1408).
            'article_ref' => $subscription->article_ref,
        ];
    }

    /** Anteil einer Periode an der Positionsmenge: offene Monate (Monatsartikel) bzw. offene Monate je Laufzeit. */
    private function share(ResalePeriod $period, bool $monthly): float {
        return $monthly ? $period->openMonths() : $period->openMonths() / $period->termMonths();
    }

    private function isMonthly(ResaleSubscription $subscription): bool {
        $article = $subscription->catalogArticle();

        return $article !== null && LicenseMonths::isMonthUnit($article->unitName);
    }

    /** @param  list<DraftEntry>  $entries */
    private static function periodCount(array $entries): int {
        return (int) array_sum(array_map(static fn(array $entry): int => count($entry['periods']), $entries));
    }

    /**
     * @param  Collection<int, ResalePeriod>  $periods
     * @return Collection<int, ResalePeriod>
     */
    private function withCatalogArticles(Collection $periods): Collection {
        ResaleSubscription::withCatalogArticles($periods->map(static fn (ResalePeriod $period): ResaleSubscription => $period->subscription));

        return $periods;
    }
}
