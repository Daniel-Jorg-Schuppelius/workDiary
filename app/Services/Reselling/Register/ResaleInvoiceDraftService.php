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
use App\Enums\Reselling\{LinkOrigin, PeriodStatus};
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResaleSubscription};
use App\Services\Billing\BillingModeResolver;
use App\Services\Finance\{BillingTransferException, BillingTransferService};
use App\Services\Finance\Targets\FacturationTargetRegistry;
use App\Services\Reselling\Draft\{DraftResult, InvoiceDraftTarget, InvoiceDraftTargets, LocalInvoiceDraftTarget};
use App\Support\{ErrorText, Tz};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Rechnungsvorschlag aus offenen Perioden (Feature 152, MVP-764 / Review
 * 2026-09-11): alle offenen Perioden eines Rechnungsempfängers werden zu
 * Positionen — eine je Abo und Zeitraum, bei Partnern mit Endkundennennung,
 * Menge in Monaten bei Monatsartikeln. Wohin der Entwurf geht, entscheidet
 * die Rechnungshoheit ({@see BillingModeResolver}): lokal das
 * {@see LocalInvoiceDraftTarget}, extern das vom Plugin registrierte
 * {@see InvoiceDraftTarget}. Der Kern stempelt die Perioden
 * (`draft_reference`/`draft_created_at`) und legt Bezüge an, wenn das Ziel
 * Positionen liefert; nichts wird festgeschrieben.
 *
 * @phpstan-import-type DraftEntry from InvoiceDraftTarget
 * @phpstan-import-type DraftLine from InvoiceDraftTarget
 * @phpstan-type DraftSummary array{draft_id: string, lines: int, net: float, periods: int, local: bool, target: string, url: string|null}
 */
final class ResaleInvoiceDraftService {
    public function __construct(
        private readonly BillingModeResolver $billingModes,
        private readonly InvoiceDraftTargets $targets,
        private readonly \App\Services\Reselling\Plan\RecurringPlans $plans,
        private readonly BillingTransferService $transfers,
        private readonly FacturationTargetRegistry $facturation,
    ) {}

    /**
     * Offene und teilweise Perioden je Rechnungsempfänger — ohne die, die
     * schon in einem Entwurf stehen (`draft_reference`).
     *
     * @return Collection<int, ResalePeriod>
     */
    public function openPeriodsFor(Customer $recipient, ?CarbonImmutable $reference = null): Collection {
        return $this->withCatalogArticles($this->pendingPeriodsFor($recipient, $reference)->whereNull('draft_reference')->get());
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
            ->whereHas('subscription', static fn($s) => $s->where('is_own_holding', false)->where(static fn($w) => $w->where('customer_id', $recipient->id)
                ->orWhereIn('foreign_customer_id', \App\Models\Customer\ForeignCustomer::query()->where('customer_id', $recipient->id)->select('id'))))
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
        $lines = [];
        foreach ($this->openPeriodsFor($recipient, $reference) as $period) {
            $line = $this->lineFor($period);
            if ($line !== null) {
                $lines[] = ['period' => $period, 'line' => $line];
            }
        }
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
    public function draft(Organization $organization, Customer $recipient, ?User $user = null): array {
        $mode = $this->billingModes->effectiveFor($recipient);
        if (! $mode->isExternal()) {
            return $this->draftLocal($organization, $recipient, $user);
        }

        return $this->transfer($organization, $recipient, $this->transferTargetFor($recipient), $user);
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
    private function transfer(Organization $organization, Customer $recipient, TransferTarget $target, ?User $user): array {
        $entries = $this->openLines($recipient);
        $periodIds = array_map(static fn(array $e): int => (int) $e['period']->id, $entries);
        $from = null;
        $to = null;
        foreach ($entries as $entry) {
            $starts = $entry['period']->starts_on->toDateString();
            $ends = $entry['period']->ends_on->toDateString();
            $from = $from === null || $starts < $from ? $starts : $from;
            $to = $to === null || $ends > $to ? $ends : $to;
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
            'periods' => count($entries),
            'local' => false,
            'target' => $target->value,
            'url' => route('finance.transfers.show', $transfer),
        ];
    }

    /**
     * Lokales Ziel ohne Weiche — öffentlich für den Serienlauf
     * ({@see ResaleLocalDraftRun}): ohne Nutzer, mit Stichtag inkl. Vorlauf;
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
     * dieselben Ausnahmen wie `draftLocal()`.
     *
     * @return array{lines: int, net: float, periods: int}
     *
     * @throws RuntimeException nichts offen / Entwurf steht schon aus
     */
    public function previewLocal(Customer $recipient, ?CarbonImmutable $reference = null): array {
        $lines = $this->openLines($recipient, $reference);
        $net = 0.0;
        foreach ($lines as $entry) {
            $net += $entry['line']['quantity'] * $entry['line']['unit_net'];
        }

        return ['lines' => count($lines), 'net' => round($net, 2), 'periods' => count($lines)];
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
                $this->stamp($organization, $recipient, $user, $entry, $result, $today);
            }

            return [
                'draft_id' => $result->reference,
                'lines' => $result->lines,
                'net' => $result->net,
                'periods' => count($entries),
                'local' => $target->key() === LocalInvoiceDraftTarget::KEY,
                'target' => $target->key(),
                'url' => $result->url,
            ];
        });
    }

    /**
     * @param  DraftEntry  $entry
     */
    private function stamp(Organization $organization, Customer $recipient, ?User $user, array $entry, DraftResult $result, string $today): void {
        $period = $entry['period'];
        $line = $entry['line'];
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
                'amount' => round($line['quantity'] * $line['unit_net'], 2),
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
     * Position einer Periode für Entwurf und Übergabe (Feature 185, MVP-1422).
     *
     * @return DraftLine|null
     */
    public function lineFor(ResalePeriod $period): ?array {
        $subscription = $period->subscription;
        $sale = $subscription->sale_unit_price;
        if ($sale === null) {
            return null; // ohne Verkaufspreis kein Vorschlag
        }
        $openMonths = $period->openMonths();
        if ($openMonths <= 0.001) {
            return null;
        }
        $termMonths = $period->termMonths();
        $article = $subscription->catalogArticle();
        $monthly = $article !== null && LicenseMonths::isMonthUnit($article->unitName);
        $holder = $subscription->foreignCustomer !== null ? (string) __('resale.draft.end_customer', ['name' => $subscription->foreignCustomer->name]) . ' · ' : '';

        return [
            'name' => $subscription->productName(),
            'description' => $holder . $period->label() . ($period->quantity > 1 ? ' · ' . $period->quantity . ' × ' : ''),
            'quantity' => $monthly ? round($openMonths, 2) : round($openMonths / $termMonths, 3),
            'unit_name' => $monthly ? 'Monat' : (string) __('resale.draft.unit_piece'),
            'unit_net' => $monthly ? round($sale->toFloat() / $termMonths, 4) : round($sale->toFloat(), 2),
            // Katalogschlüssel: das Ziel schreibt die Position mit Artikel, damit die Rechnung als Lizenzposition zurückkommt (Feature 185, MVP-1408).
            'article_ref' => $subscription->article_ref,
        ];
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
