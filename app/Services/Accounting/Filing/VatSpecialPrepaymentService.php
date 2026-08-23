<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VatSpecialPrepaymentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Filing;

use App\Models\Accounting\{AccountingAccount, AccountingEntry, AccountingProfile, AccountingVatExtension};
use App\Models\{Organization, User};
use App\Services\Accounting\{AccountingReportService, JournalService, VatFilingProfileResolver};
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Sondervorauszahlung zur Dauerfristverlängerung (Feature 125, MVP-685).
 *
 * § 47 UStDV: ein Elftel der Vorauszahlungen des Vorjahres, fällig zum 10.02.,
 * angerechnet in der letzten Voranmeldung des Jahres (Kennziffer 39). Wer nur
 * vierteljährlich meldet, bekommt die Verlängerung ohne sie.
 *
 * Berechnet wird aus dem eigenen Journal — dieselbe Quelle wie die
 * USt-Auswertung. Gebucht wird **nie automatisch**: Die Zahlung ist eine
 * Entscheidung, keine Ableitung.
 */
class VatSpecialPrepaymentService {
    public function __construct(
        private readonly VatFilingProfileResolver $profile,
        private readonly AccountingReportService $reports,
        private readonly JournalService $journal,
    ) {}

    /**
     * Rechenweg für ein Jahr — offen ausgewiesen statt stillschweigend gerundet.
     *
     * @return array{required: bool, year: int, prior_year: int, prior_year_tax: string, months_active: int, annualised: string, amount: string, due_on: string}
     */
    public function calculate(Organization $organization, int $year): array {
        $priorFrom = CarbonImmutable::parse(sprintf('%04d-%02d-%02d', $year - 1, 1, 1));
        $priorTo = $priorFrom->endOfYear();

        $payable = (string) $this->reports->vatPreview($organization, $priorFrom, $priorTo)['payable'];
        $months = $this->activeMonths($organization, $priorFrom, $priorTo);

        // § 47 Abs. 3 UStDV: Wer im Vorjahr nur einen Teil des Jahres tätig
        // war, rechnet die Summe auf ein volles Jahr hoch.
        $annualised = $months > 0 && $months < 12
            ? number_format((float) $payable * 12 / $months, 2, '.', '')
            : $payable;

        return [
            'required' => $this->profile->at($organization, CarbonImmutable::parse(sprintf('%04d-%02d-%02d', $year, 2, 10)))->requiresSpecialPrepayment(),
            'year' => $year,
            'prior_year' => $year - 1,
            'prior_year_tax' => $payable,
            'months_active' => $months,
            'annualised' => $annualised,
            'amount' => number_format((float) $annualised / 11, 2, '.', ''),
            'due_on' => CarbonImmutable::parse(sprintf('%04d-%02d-%02d', $year, 2, 10))->toDateString(),
        ];
    }

    /**
     * Sondervorauszahlung buchen: Vorauszahlungskonto an Geldkonto.
     *
     * Sie ist eine echte Zahlung ans Finanzamt — bliebe sie außerhalb des
     * Journals, ginge die Bankabstimmung nicht auf.
     */
    public function post(
        Organization $organization,
        int $year,
        AccountingAccount $prepaymentAccount,
        AccountingAccount $moneyAccount,
        string $amount,
        CarbonImmutable $bookedOn,
        User $actor,
    ): AccountingEntry {
        if ((float) $amount <= 0.0) {
            throw ValidationException::withMessages([
                'amount' => [(string) __('accounting.filing.error.amount_positive')],
            ]);
        }

        if (! $moneyAccount->is_bank && ! $moneyAccount->is_cash) {
            throw ValidationException::withMessages([
                'money_account' => [(string) __('accounting.filing.error.not_a_money_account')],
            ]);
        }

        $extension = $this->profile->extensionFor($organization, $year);
        if (! $extension instanceof AccountingVatExtension) {
            throw ValidationException::withMessages([
                'year' => [(string) __('accounting.filing.error.no_extension', ['year' => (string) $year])],
            ]);
        }

        $entry = $this->journal->postDirect($organization, [
            'booked_on' => $bookedOn,
            'memo' => (string) __('accounting.filing.prepayment_memo', ['year' => (string) $year]),
            'source_key' => $extension->prepaymentSourceKey(),
            'lines' => [
                ['accounting_account_id' => $prepaymentAccount->id, 'debit' => $amount, 'credit' => '0.00'],
                ['accounting_account_id' => $moneyAccount->id, 'debit' => '0.00', 'credit' => $amount],
            ],
        ], $actor);

        $extension->update([
            'special_prepayment_amount' => $amount,
            'special_prepayment_entry_id' => $entry->id,
        ]);

        return $entry;
    }

    /**
     * Monate mit lokaler Buchhaltung im Vorjahr — angefangene zählen mit.
     */
    private function activeMonths(Organization $organization, CarbonImmutable $from, CarbonImmutable $to): int {
        $profile = AccountingProfile::query()->where('organization_id', $organization->id)->first();
        $startsOn = $profile instanceof AccountingProfile && $profile->starts_on !== null
            ? CarbonImmutable::parse($profile->starts_on)
            : null;

        if ($startsOn === null || $startsOn->lessThanOrEqualTo($from)) {
            return 12;
        }

        if ($startsOn->greaterThan($to)) {
            return 0;
        }

        return 12 - $startsOn->month + 1;
    }
}
