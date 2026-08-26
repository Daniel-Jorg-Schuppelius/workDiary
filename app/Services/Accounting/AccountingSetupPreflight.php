<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingSetupPreflight.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\Finance\{AccountingSovereignty, BillingMode, DatevBatchStatus};
use App\Enums\Migration\AccountingMigrationStatus;
use App\Models\Accounting\{AccountingFiscalYear, AccountingProfile, AccountingSovereigntyPeriod};
use App\Models\Finance\{BankTransaction, DatevBookingBatch};
use App\Models\{IncomingEInvoice, Invoice, Organization};
use App\Models\Migration\AccountingMigrationRun;
use App\Services\Accounting\Preflight\{AccountingPreflightCheck, AccountingPreflightReport};
use App\Support\Query\DateRange;
use App\Support\Setting;
use Carbon\CarbonImmutable;

/**
 * Einrichtungs-Preflight der lokalen Buchhaltung (Feature 125, MVP-671).
 *
 * Die Prüfpunkte beantworten eine einzige Frage: Kann diese Organisation ab
 * dem gewünschten Stichtag lückenlos und widerspruchsfrei selbst buchen?
 * Blockierend ist deshalb nur, was hinterher nicht mehr sauber zu reparieren
 * wäre — ein fehlendes Geschäftsjahr, ein bereits fremd übergebener Zeitraum,
 * ein laufender Buchhaltungswechsel. Fremdwährungsbelege und externe
 * Fakturahoheit sind Hinweise: sie bleiben im Betrieb sichtbar, statt die
 * Einrichtung zu verhindern.
 */
class AccountingSetupPreflight {
    public function __construct(private readonly AccountingSovereigntyResolver $sovereignty) {}

    public function for(Organization $organization, ?AccountingProfile $profile = null): AccountingPreflightReport {
        $profile ??= $this->sovereignty->profile($organization);

        if (! $profile instanceof AccountingProfile) {
            return new AccountingPreflightReport([
                AccountingPreflightCheck::blocked('profile', (string) __('accounting.ledger.preflight.profile_missing')),
            ]);
        }

        $startsOn = $profile->starts_on !== null ? CarbonImmutable::parse($profile->starts_on)->startOfDay() : null;

        $checks = [$this->checkStartDate($startsOn)];

        if ($startsOn === null) {
            return new AccountingPreflightReport($checks);
        }

        return new AccountingPreflightReport(array_merge($checks, [
            $this->checkFiscalYear($organization, $startsOn),
            $this->checkMigrationRun($organization),
            $this->checkHandedOverPeriods($organization, $startsOn),
            $this->checkSovereigntyOverlap($organization, $startsOn),
            $this->checkForeignCurrency($organization, $profile, $startsOn),
            $this->checkBillingMode($organization),
            $this->checkMasterDataAuthority(),
        ]));
    }

    private function checkStartDate(?CarbonImmutable $startsOn): AccountingPreflightCheck {
        if ($startsOn === null) {
            return AccountingPreflightCheck::blocked('starts_on', (string) __('accounting.ledger.preflight.starts_on_missing'));
        }

        return AccountingPreflightCheck::passed('starts_on', (string) __('accounting.ledger.preflight.starts_on_ok', [
            'date' => $startsOn->format(\App\Support\Formats::date()),
        ]));
    }

    /** Ohne Geschäftsjahr samt Perioden hat eine Festbuchung keinen Ort. */
    private function checkFiscalYear(Organization $organization, CarbonImmutable $startsOn): AccountingPreflightCheck {
        $year = AccountingFiscalYear::query()
            ->where('organization_id', $organization->id)
            ->where('starts_on', '<=', DateRange::day($startsOn))
            ->where('ends_on', '>=', DateRange::day($startsOn))
            ->withCount('periods')
            ->first();

        if (! $year instanceof AccountingFiscalYear) {
            return AccountingPreflightCheck::blocked('fiscal_year', (string) __('accounting.ledger.preflight.fiscal_year_missing'));
        }

        if ((int) $year->getAttribute('periods_count') === 0) {
            return AccountingPreflightCheck::blocked('fiscal_year', (string) __('accounting.ledger.preflight.periods_missing', [
                'year' => $year->label,
            ]));
        }

        return AccountingPreflightCheck::passed('fiscal_year', (string) __('accounting.ledger.preflight.fiscal_year_ok', [
            'year' => $year->label,
            'count' => (int) $year->getAttribute('periods_count'),
        ]));
    }

    /** Während eines laufenden Wechsels ist die Führung per Definition unklar. */
    private function checkMigrationRun(Organization $organization): AccountingPreflightCheck {
        $active = AccountingMigrationRun::query()
            ->where('organization_id', $organization->id)
            ->whereNotIn('status', [
                AccountingMigrationStatus::Completed->value,
                AccountingMigrationStatus::Cancelled->value,
            ])
            ->first();

        if ($active instanceof AccountingMigrationRun) {
            return AccountingPreflightCheck::blocked('migration_run', (string) __('accounting.ledger.preflight.migration_active', [
                'status' => $active->status->label(),
            ]), ['run_id' => $active->id]);
        }

        return AccountingPreflightCheck::passed('migration_run', (string) __('accounting.ledger.preflight.migration_none'));
    }

    /**
     * Ein finalisierter DATEV-Stapel ist eine abgegebene Erklärung über
     * seinen Zeitraum. Wer denselben Zeitraum danach lokal noch einmal
     * festschreibt, hat zwei Wahrheiten statt einer.
     */
    private function checkHandedOverPeriods(Organization $organization, CarbonImmutable $startsOn): AccountingPreflightCheck {
        $batch = DatevBookingBatch::query()
            ->where('organization_id', $organization->id)
            ->where('status', DatevBatchStatus::Exported->value)
            ->where('period_to', '>=', DateRange::day($startsOn))
            ->orderByDesc('period_to')
            ->first();

        if ($batch instanceof DatevBookingBatch) {
            return AccountingPreflightCheck::blocked('handed_over', (string) __('accounting.ledger.preflight.handed_over', [
                'batch' => (string) $batch->batch_no,
                'to' => $batch->period_to->format(\App\Support\Formats::date()),
            ]), ['batch_id' => $batch->id]);
        }

        return AccountingPreflightCheck::passed('handed_over', (string) __('accounting.ledger.preflight.handed_over_none'));
    }

    /** Kein zweiter Hoheitsabschnitt darf denselben Zeitraum beanspruchen. */
    private function checkSovereigntyOverlap(Organization $organization, CarbonImmutable $startsOn): AccountingPreflightCheck {
        $conflicting = AccountingSovereigntyPeriod::query()
            ->where('organization_id', $organization->id)
            ->where('sovereignty', '!=', AccountingSovereignty::Local->value)
            ->where(function ($query) use ($startsOn): void {
                $query->whereNull('valid_to')->orWhere('valid_to', '>=', DateRange::day($startsOn));
            })
            ->where('valid_from', '>=', DateRange::day($startsOn))
            ->first();

        if ($conflicting instanceof AccountingSovereigntyPeriod) {
            return AccountingPreflightCheck::blocked('sovereignty', (string) __('accounting.ledger.preflight.sovereignty_conflict', [
                'from' => $conflicting->valid_from->format(\App\Support\Formats::date()),
                'holder' => $conflicting->external_provider ?? $conflicting->sovereignty->label(),
            ]), ['period_id' => $conflicting->id]);
        }

        return AccountingPreflightCheck::passed('sovereignty', (string) __('accounting.ledger.preflight.sovereignty_ok'));
    }

    /**
     * Der MVP führt genau eine Basiswährung; abweichende Belege werden in der
     * Buchungs-Inbox mit Grund angezeigt (MVP-673), nicht still umgerechnet.
     */
    private function checkForeignCurrency(Organization $organization, AccountingProfile $profile, CarbonImmutable $startsOn): AccountingPreflightCheck {
        $base = $profile->base_currency->value;
        $date = $startsOn->toDateString();

        $count = Invoice::query()
            ->where('organization_id', $organization->id)
            ->where('issued_on', '>=', DateRange::day($date))
            ->where('currency', '!=', $base)
            ->count();

        $count += IncomingEInvoice::query()
            ->where('organization_id', $organization->id)
            ->where('issue_date', '>=', DateRange::day($date))
            ->whereNotNull('currency')
            ->where('currency', '!=', $base)
            ->count();

        $count += BankTransaction::query()
            ->where('organization_id', $organization->id)
            ->where('booking_date', '>=', DateRange::day($date))
            ->whereNotNull('currency')
            ->where('currency', '!=', $base)
            ->count();

        if ($count > 0) {
            return AccountingPreflightCheck::warning('base_currency', (string) __('accounting.ledger.preflight.foreign_currency', [
                'count' => $count,
                'currency' => $base,
            ]), ['count' => $count]);
        }

        return AccountingPreflightCheck::passed('base_currency', (string) __('accounting.ledger.preflight.base_currency_ok', [
            'currency' => $base,
        ]));
    }

    /** Externe Fakturahoheit ist erlaubt — sie ändert nur, woher die Belege kommen. */
    private function checkBillingMode(Organization $organization): AccountingPreflightCheck {
        $stored = data_get($organization->settings, 'billing_mode');
        $mode = is_string($stored) ? BillingMode::tryFrom($stored) : null;

        if ($mode instanceof BillingMode && $mode->isExternal()) {
            return AccountingPreflightCheck::warning('billing_mode', (string) __('accounting.ledger.preflight.billing_external', [
                'program' => $mode->label(),
            ]));
        }

        return AccountingPreflightCheck::passed('billing_mode', (string) __('accounting.ledger.preflight.billing_local'));
    }

    /** Stammdatenhoheit: dritte Achse, ebenfalls nur ein Hinweis. */
    private function checkMasterDataAuthority(): AccountingPreflightCheck {
        if (Setting::get(\App\Services\Finance\Accounting\ContactPushService::AUTHORITY_KEY, 'workdiary') === 'accounting') {
            return AccountingPreflightCheck::warning('master_data', (string) __('accounting.ledger.preflight.master_data_external'));
        }

        return AccountingPreflightCheck::passed('master_data', (string) __('accounting.ledger.preflight.master_data_local'));
    }
}
