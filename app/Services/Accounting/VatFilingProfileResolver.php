<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VatFilingProfileResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\Finance\VatFilingInterval;
use App\Models\Accounting\{AccountingVatExtension, AccountingVatFilingPeriod};
use App\Models\{Organization, User};
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Meldeprofil der Umsatzsteuer (Feature 125, MVP-684) — EINZIGE Schreibstelle
 * für `accounting_vat_filing_periods` und `accounting_vat_extensions`.
 *
 * Ohne Abschnitt gilt das **Kalendervierteljahr**: Es ist der gesetzliche
 * Regelfall (§ 18 Abs. 2 S. 1 UStG). Monatlich oder befreit ist die Ausnahme,
 * und über sie entscheidet das Finanzamt — eine Software, die das von sich aus
 * annimmt, würde einen Bescheid unterstellen, den niemand erlassen hat.
 *
 * Der Resolver kennt bewusst keine Auswertung: Die Vorjahressteuer für den
 * Ableitungsvorschlag reicht der Aufrufer herein. Sonst hinge das Meldeprofil
 * am Berichtsdienst, der es seinerseits braucht.
 */
class VatFilingProfileResolver {
    /** § 18 Abs. 2 S. 2 UStG — ab dieser Vorjahressteuer monatlich (seit 2025). */
    public const MONTHLY_THRESHOLD = 9000.0;

    /** § 18 Abs. 2 S. 3 UStG — bis hierhin Befreiung möglich (seit 2025). */
    public const ANNUAL_THRESHOLD = 2000.0;

    /**
     * Bis einschließlich dieses Besteuerungszeitraums ist der Zwang zur
     * monatlichen Abgabe für Neugründer ausgesetzt (§ 18 Abs. 2 S. 4 UStG).
     * Ab 2027 greift er wieder.
     */
    public const FOUNDER_RULE_SUSPENDED_THROUGH = 2026;

    public function __construct(private readonly AccountingEventRecorder $events) {}

    public function at(Organization $organization, ?CarbonInterface $date = null): VatFilingInterval {
        $period = $this->periodAt($organization, $date);

        return $period instanceof AccountingVatFilingPeriod ? $period->interval : VatFilingInterval::Quarterly;
    }

    public function periodAt(Organization $organization, ?CarbonInterface $date = null): ?AccountingVatFilingPeriod {
        $day = CarbonImmutable::parse($date ?? now())->startOfDay();

        return AccountingVatFilingPeriod::query()
            ->where('organization_id', $organization->id)
            ->whereDate('valid_from', '<=', $day->toDateString())
            ->where(function ($query) use ($day): void {
                $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $day->toDateString());
            })
            ->orderByDesc('valid_from')
            ->first();
    }

    /**
     * Wechselt den Voranmeldungszeitraum ab einem Stichtag.
     *
     * Der Stichtag ist in der Praxis ein Jahreswechsel; erzwungen wird das
     * nicht — ein unterjähriger Wechsel kommt vor (Bescheid des Finanzamts,
     * Wegfall der Kleinunternehmerregelung).
     */
    public function switchTo(
        Organization $organization,
        VatFilingInterval $interval,
        CarbonImmutable $from,
        User $actor,
        ?string $reason = null,
    ): AccountingVatFilingPeriod {
        $from = $from->startOfDay();

        if ($this->at($organization, $from) === $interval && $this->periodAt($organization, $from) !== null) {
            throw ValidationException::withMessages([
                'interval' => (string) __('accounting.filing.error.unchanged'),
            ]);
        }

        $later = AccountingVatFilingPeriod::query()
            ->where('organization_id', $organization->id)
            ->whereDate('valid_from', '>', $from->toDateString())
            ->orderBy('valid_from')
            ->first();

        if ($later instanceof AccountingVatFilingPeriod) {
            throw ValidationException::withMessages([
                'valid_from' => (string) __('accounting.filing.error.later_section', [
                    'date' => $later->valid_from->format(\App\Support\Formats::date()),
                ]),
            ]);
        }

        return DB::transaction(function () use ($organization, $interval, $from, $actor, $reason): AccountingVatFilingPeriod {
            AccountingVatFilingPeriod::query()
                ->where('organization_id', $organization->id)
                ->whereDate('valid_from', '=', $from->toDateString())
                ->delete();

            AccountingVatFilingPeriod::query()
                ->where('organization_id', $organization->id)
                ->whereNull('valid_to')
                ->whereDate('valid_from', '<', $from->toDateString())
                ->update(['valid_to' => $from->subDay()->toDateString()]);

            $period = AccountingVatFilingPeriod::query()->create([
                'organization_id' => $organization->id,
                'interval' => $interval,
                'valid_from' => $from->toDateString(),
                'valid_to' => null,
                'reason' => $reason,
                'actor_user_id' => $actor->id,
            ]);

            $this->events->record($organization, 'accounting.filing_interval_switched', [
                'interval' => $interval->value,
                'valid_from' => $from->toDateString(),
            ], null, $actor);

            return $period;
        });
    }

    /**
     * Ableitungsvorschlag aus der Vorjahressteuer (§ 18 Abs. 2 UStG).
     *
     * Er wird **angezeigt, nie angewendet**: Über den Voranmeldungszeitraum
     * entscheidet das Finanzamt, und die Vorjahressteuer ist erst nach dem
     * Jahresabschluss belastbar.
     *
     * @return array{interval: VatFilingInterval, prior_year: int, prior_year_tax: string, reason_key: string, founder_rule_active: bool}
     */
    public function suggest(int $year, string $priorYearTax): array {
        $tax = (float) $priorYearTax;

        $interval = match (true) {
            $tax > self::MONTHLY_THRESHOLD => VatFilingInterval::Monthly,
            $tax <= self::ANNUAL_THRESHOLD => VatFilingInterval::Annual,
            default => VatFilingInterval::Quarterly,
        };

        return [
            'interval' => $interval,
            'prior_year' => $year - 1,
            'prior_year_tax' => number_format($tax, 2, '.', ''),
            'reason_key' => 'accounting.filing.suggestion.' . $interval->value,
            // Ab 2027 ist die Aussetzung beendet: Neugründungen melden dann
            // wieder zwingend monatlich, unabhängig von der Vorjahressteuer.
            'founder_rule_active' => $year > self::FOUNDER_RULE_SUSPENDED_THROUGH,
        ];
    }

    public function extensionFor(Organization $organization, int $year): ?AccountingVatExtension {
        return AccountingVatExtension::query()
            ->where('organization_id', $organization->id)
            ->where('year', $year)
            ->first();
    }

    /**
     * Gilt am Stichtag eine Dauerfristverlängerung?
     *
     * Sie gilt dauerhaft weiter, sobald sie einmal gewährt wurde — maßgeblich
     * ist das früheste erfasste Jahr, nicht eine Zeile je Jahr.
     */
    public function hasExtension(Organization $organization, ?CarbonInterface $date = null): bool {
        $year = CarbonImmutable::parse($date ?? now())->year;

        return AccountingVatExtension::query()
            ->where('organization_id', $organization->id)
            ->where('year', '<=', $year)
            ->whereNotNull('granted_on')
            ->exists();
    }

    /** Dauerfristverlängerung eines Jahres erfassen oder fortschreiben. */
    public function recordExtension(
        Organization $organization,
        int $year,
        ?CarbonImmutable $grantedOn,
        ?string $specialPrepayment,
        User $actor,
        ?string $note = null,
    ): AccountingVatExtension {
        $extension = $this->extensionFor($organization, $year) ?? new AccountingVatExtension([
            'organization_id' => $organization->id,
            'year' => $year,
        ]);

        $extension->fill([
            'organization_id' => $organization->id,
            'year' => $year,
            'granted_on' => $grantedOn?->toDateString(),
            'special_prepayment_amount' => $specialPrepayment,
            'note' => $note,
            'actor_user_id' => $actor->id,
        ]);
        $extension->save();

        $this->events->record($organization, 'accounting.vat_extension_recorded', [
            'year' => $year,
            'granted_on' => $grantedOn?->toDateString(),
            'special_prepayment' => $specialPrepayment,
        ], null, $actor);

        return $extension->refresh();
    }
}
