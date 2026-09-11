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

use App\Enums\Reselling\{LinkOrigin, PeriodStatus};
use App\Models\{Customer, Organization, User};
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink};
use App\Services\Finance\BillingModeResolver;
use App\Services\Reselling\Draft\{DraftResult, InvoiceDraftTarget, InvoiceDraftTargets, LocalInvoiceDraftTarget};
use App\Support\Query\DateRange;
use App\Support\Tz;
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
    ) {}

    /**
     * Offene und teilweise Perioden je Rechnungsempfänger — ohne die, die
     * schon in einem Entwurf stehen (`draft_reference`).
     *
     * @return Collection<int, ResalePeriod>
     */
    public function openPeriodsFor(Customer $recipient, ?CarbonImmutable $reference = null): Collection {
        return $this->pendingPeriodsFor($recipient, $reference)->whereNull('draft_reference')->get();
    }

    /**
     * Offene und teilweise Perioden des Empfängers, die bereits in einem
     * Entwurf stehen (zweiter Klick).
     *
     * @return Collection<int, ResalePeriod>
     */
    public function draftedPeriodsFor(Customer $recipient, ?CarbonImmutable $reference = null): Collection {
        return $this->pendingPeriodsFor($recipient, $reference)->whereNotNull('draft_reference')->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<ResalePeriod>
     */
    private function pendingPeriodsFor(Customer $recipient, ?CarbonImmutable $reference): \Illuminate\Database\Eloquent\Builder {
        $reference ??= ResalePeriod::today();

        return ResalePeriod::query()
            ->whereIn('status', [PeriodStatus::Open->value, PeriodStatus::Partial->value])
            ->where('starts_on', '<', DateRange::dayAfter($reference))
            ->whereHas('subscription', static fn($s) => $s->where('is_own_holding', false)->where(static fn($w) => $w->where('customer_id', $recipient->id)
                ->orWhereIn('foreign_customer_id', \App\Models\ForeignCustomer::query()->where('customer_id', $recipient->id)->select('id'))))
            ->with(['subscription.foreignCustomer', 'subscription.lexofficeArticle', 'subscription.article', 'links'])
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
     * Rechnungsentwurf, extern das registrierte Ziel des Plugins.
     *
     * @return DraftSummary
     *
     * @throws RuntimeException nichts offen / Entwurf steht aus / kein Ziel für die externe Hoheit
     */
    public function draft(Organization $organization, Customer $recipient, ?User $user = null): array {
        return $this->draftWith($this->targetFor($recipient), $organization, $recipient, $user, null);
    }

    /**
     * Ziel des Empfängers: bei lokaler Hoheit das lokale Ziel, sonst das
     * externe Ziel, das den Empfänger bedient.
     *
     * @throws RuntimeException kein registriertes Ziel
     */
    public function targetFor(Customer $recipient): InvoiceDraftTarget {
        $target = $this->billingModes->effectiveFor($recipient)->isExternal() ? $this->targets->externalFor($recipient) : $this->targets->local();
        if ($target === null) {
            throw new RuntimeException((string) __('resale.draft.no_target', ['mode' => $this->billingModes->effectiveFor($recipient)->label()]));
        }

        return $target;
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
     * @return DraftLine|null
     */
    private function lineFor(ResalePeriod $period): ?array {
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
        $monthly = $subscription->lexofficeArticle !== null && LicenseMonths::isMonthUnit($subscription->lexofficeArticle->unit_name);
        $holder = $subscription->foreignCustomer !== null ? (string) __('resale.draft.end_customer', ['name' => $subscription->foreignCustomer->name]) . ' · ' : '';

        return [
            'name' => $subscription->lexofficeArticle !== null ? $subscription->lexofficeArticle->name : $subscription->label,
            'description' => $holder . $period->label() . ($period->quantity > 1 ? ' · ' . $period->quantity . ' × ' : ''),
            'quantity' => $monthly ? round($openMonths, 2) : round($openMonths / $termMonths, 3),
            'unit_name' => $monthly ? 'Monat' : (string) __('resale.draft.unit_piece'),
            'unit_net' => $monthly ? round($sale->toFloat() / $termMonths, 4) : round($sale->toFloat(), 2),
        ];
    }
}
