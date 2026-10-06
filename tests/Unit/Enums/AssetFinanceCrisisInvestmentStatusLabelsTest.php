<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceCrisisInvestmentStatusLabelsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\AssetFinance\{AssetFinanceDeadlineStatus, AssetFinanceEndProcessStatus, AssetFinanceRateScheduleStatus};
use App\Enums\Contracts\HasLabel;
use App\Enums\Crisis\{CrisisActionStatus, CrisisCaseStatus, CrisisCommunicationStatus, CrisisContinuityImpactStatus};
use App\Enums\Investments\{InvestmentBudgetRequestStatus, InvestmentCaseStatus, InvestmentDeviationStatus};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Die Status-Enums von Leasingakte, Krisenakte und Investitionsakte lesen
 * ihre Texte aus `values.<wert>`. Fehlt ein Eintrag, erscheint der Schlüssel
 * oder — in einer Fremdsprache — still der deutsche Text.
 */
final class AssetFinanceCrisisInvestmentStatusLabelsTest extends TestCase {
    /** @return array<string, array{class-string<\BackedEnum&HasLabel>}> */
    public static function enums(): array {
        return array_map(static fn (string $class): array => [$class], [
            'Leasingfrist' => AssetFinanceDeadlineStatus::class,
            'Ende-Prozess' => AssetFinanceEndProcessStatus::class,
            'Ratenzeile' => AssetFinanceRateScheduleStatus::class,
            'Krisenmaßnahme' => CrisisActionStatus::class,
            'Krisenakte' => CrisisCaseStatus::class,
            'Krisenkommunikation' => CrisisCommunicationStatus::class,
            'Wiederanlauf' => CrisisContinuityImpactStatus::class,
            'Budgetantrag' => InvestmentBudgetRequestStatus::class,
            'Investitionsakte' => InvestmentCaseStatus::class,
            'Abweichung' => InvestmentDeviationStatus::class,
        ]);
    }

    /** @param class-string<\BackedEnum&HasLabel> $class */
    #[DataProvider('enums')]
    public function test_every_case_is_translated_in_all_five_locales(string $class): void {
        app()->setLocale('de');
        $german = [];
        foreach ($class::cases() as $case) {
            $german[$case->name] = $case->label();
        }

        foreach (['de', 'en', 'es', 'fr', 'it'] as $locale) {
            app()->setLocale($locale);
            foreach ($class::cases() as $case) {
                $label = $case->label();
                $this->assertNotSame('', $label);
                $this->assertStringStartsNotWith('values.', $label, "{$class}::{$case->name} ohne Text in {$locale}");
                if ($locale !== 'de') {
                    $this->assertNotSame($german[$case->name], $label, "{$class}::{$case->name} fällt in {$locale} auf den deutschen Text zurück");
                }
            }
        }
    }

    /** Die Auswahl im Formular und die Validierung lesen dieselbe Menge. */
    public function test_named_subsets_keep_their_stored_values(): void {
        $values = static fn (array $cases): array => array_map(static fn (\BackedEnum $case): string => (string) $case->value, $cases);

        $this->assertSame(['open', 'done', 'missed'], AssetFinanceDeadlineStatus::values());
        $this->assertSame(['draft', 'in_progress', 'completed'], $values(AssetFinanceEndProcessStatus::cases()));
        $this->assertSame(['planned', 'paid', 'overdue'], $values(AssetFinanceRateScheduleStatus::cases()));
        $this->assertSame(['open', 'in_progress', 'done', 'cancelled'], CrisisActionStatus::values());
        $this->assertSame(['open', 'in_progress'], $values(CrisisActionStatus::pending()));
        $this->assertSame(['prepared', 'reported', 'assessed', 'activated', 'in_progress', 'stabilized', 'recovery', 'all_clear', 'post_review', 'closed', 'discarded'], CrisisCaseStatus::values());
        $this->assertSame(['reported', 'assessed', 'activated', 'in_progress', 'stabilized', 'recovery'], $values(CrisisCaseStatus::active()));
        $this->assertSame(['assessed', 'in_progress', 'stabilized', 'recovery'], $values(CrisisCaseStatus::stages()));
        $this->assertSame(['all_clear', 'post_review', 'closed'], $values(CrisisCaseStatus::ended()));
        $this->assertSame(['draft', 'approved', 'sent'], $values(CrisisCommunicationStatus::cases()));
        $this->assertSame(['down', 'degraded', 'workaround', 'restored'], CrisisContinuityImpactStatus::values());
        $this->assertSame(['draft', 'in_approval', 'approved', 'rejected', 'superseded'], $values(InvestmentBudgetRequestStatus::cases()));
        $this->assertSame(['draft', 'in_approval'], $values(InvestmentBudgetRequestStatus::open()));
        $this->assertSame(['idea', 'screening', 'comparison', 'budget_request', 'in_approval', 'approved', 'rejected', 'deferred', 'in_progress', 'completed', 'cancelled', 'post_review'], InvestmentCaseStatus::values());
        $this->assertSame(['idea', 'screening', 'comparison', 'budget_request'], $values(InvestmentCaseStatus::planning()));
        $this->assertSame(['open', 'approved', 'rejected'], $values(InvestmentDeviationStatus::cases()));
    }
}
