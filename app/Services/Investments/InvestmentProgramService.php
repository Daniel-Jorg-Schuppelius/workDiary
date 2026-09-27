<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentProgramService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Investments;

use App\Models\Investments\{InvestmentBudgetRequest, InvestmentCase, InvestmentProgram, InvestmentProgramBudget};
use Illuminate\Support\Facades\DB;

/**
 * Investitionsprogramme (MVP-927): Jahresbudgets pflegen und das Portfolio
 * je Jahr gegenüberstellen. Planwert einer Investition ist das genehmigte
 * Budget, sonst der letzte offene Antrag; Ist-Werte kommen aus der
 * bestehenden Projektion. Planjahr ist `planned_year`, sonst das Jahr des
 * Beginns, sonst das erste Programmjahr.
 */
final class InvestmentProgramService {
    public function __construct(private readonly InvestmentService $investments) {}

    /** @param array<int|string, mixed> $amounts Jahr → Betrag; leer entfernt das Jahr */
    public function saveBudgets(InvestmentProgram $program, array $amounts): void {
        DB::transaction(function () use ($program, $amounts): void {
            foreach ($program->years() as $year) {
                $raw = $amounts[$year] ?? null;
                if (! is_numeric($raw)) {
                    $program->budgets()->where('year', $year)->delete();

                    continue;
                }
                InvestmentProgramBudget::query()->updateOrCreate(
                    ['investment_program_id' => $program->id, 'year' => $year],
                    ['organization_id' => $program->organization_id, 'budget_amount' => bcadd((string) $raw, '0', 2)],
                );
            }
            $program->budgets()->where(fn ($q) => $q->where('year', '<', $program->starts_year)->orWhere('year', '>', $program->ends_year))->delete();
        });
    }

    /** @return numeric-string */
    public function plannedAmount(InvestmentCase $case): string {
        $request = $case->approvedBudget()
            ?? $case->budgetRequests()->whereIn('status', ['draft', 'in_approval'])->orderByDesc('version')->first();

        return $request instanceof InvestmentBudgetRequest ? bcadd($request->amount, '0', 2) : '0.00';
    }

    public function plannedYear(InvestmentCase $case, InvestmentProgram $program): int {
        return $case->planned_year ?? $case->starts_on->year ?? $program->starts_year;
    }

    /**
     * @return array{years: list<array{year: int, budget: string, planned: string, approved: float, actual: float, remaining: string, over: bool}>, cases: list<array{case: InvestmentCase, year: int, planned: string, approved: float, actual: float}>, by_status: array<string, int>, by_category: array<string, string>}
     */
    public function portfolio(InvestmentProgram $program): array {
        $budgets = $program->budgets()->get()->keyBy('year');
        $years = [];
        foreach ($program->years() as $year) {
            $years[$year] = ['year' => $year, 'budget' => $budgets->get($year)->budget_amount ?? '0.00', 'planned' => '0.00', 'approved' => 0.0, 'actual' => 0.0];
        }
        $cases = [];
        $byStatus = [];
        $byCategory = [];
        foreach ($program->cases()->orderBy('title')->get() as $case) {
            $year = $this->plannedYear($case, $program);
            $planned = $this->plannedAmount($case);
            $projection = $this->investments->projection($case);
            $cases[] = ['case' => $case, 'year' => $year, 'planned' => $planned, 'approved' => $projection['approved'], 'actual' => $projection['actual']];
            $byStatus[(string) $case->status] = ($byStatus[(string) $case->status] ?? 0) + 1;
            $byCategory[(string) $case->category] = bcadd($byCategory[(string) $case->category] ?? '0', $planned, 2);
            if (isset($years[$year])) {
                $years[$year]['planned'] = bcadd($years[$year]['planned'], $planned, 2);
                $years[$year]['approved'] = round($years[$year]['approved'] + $projection['approved'], 2);
                $years[$year]['actual'] = round($years[$year]['actual'] + $projection['actual'], 2);
            }
        }
        $rows = [];
        foreach ($years as $row) {
            $remaining = bcsub($row['budget'], $row['planned'], 2);
            $rows[] = $row + ['remaining' => $remaining, 'over' => bccomp($remaining, '0', 2) < 0];
        }

        return ['years' => $rows, 'cases' => $cases, 'by_status' => $byStatus, 'by_category' => $byCategory];
    }
}
