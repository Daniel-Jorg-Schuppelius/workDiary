<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiBudgetService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\Ai\AiFamily;
use App\Models\Ai\AiUsagePeriod;
use App\Models\Organization;
use App\Services\Ai\Exceptions\AiBudgetExceededException;
use Illuminate\Support\Carbon;

/**
 * Monatsbudget je Organisation und Provider-Familie (Feature 025,
 * MVP-399): LLM in Token, Übersetzung in Zeichen. Der Org-Override
 * liegt org-explizit in settings.ai.budget.monthly_units.<familie>
 * (bewusst data_get statt Setting::get — ein config-Fallback ist hier
 * als globaler Default fachlich gewollt und wird separat gelesen);
 * null = unbegrenzt.
 */
class AiBudgetService {
    public function limitFor(Organization $organization, AiFamily $family): ?int {
        $override = data_get($organization->settings, 'ai.budget.monthly_units.' . $family->value);
        $limit = $override ?? config('ai.budget.monthly_units.' . $family->value);

        if ($limit === null || $limit === '') {
            return null;
        }

        return max(0, (int) $limit);
    }

    public function usedThisPeriod(Organization $organization, AiFamily $family): int {
        return (int) AiUsagePeriod::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('period', $this->currentPeriod())
            ->where('family', $family->value)
            ->value('used_units');
    }

    /** Vorab-Check VOR dem Provider-Aufruf; wirft bei erschöpftem Budget. */
    public function assertWithinBudget(Organization $organization, AiFamily $family, int $plannedUnits): void {
        $limit = $this->limitFor($organization, $family);

        if ($limit === null) {
            return;
        }

        if ($this->usedThisPeriod($organization, $family) + max(0, $plannedUnits) > $limit) {
            throw AiBudgetExceededException::forFamily($family, $limit);
        }
    }

    /** Tatsächlichen Verbrauch nach erfolgreichem Aufruf festschreiben. */
    public function recordUsage(Organization $organization, AiFamily $family, int $units): void {
        if ($units <= 0) {
            return;
        }

        $row = AiUsagePeriod::query()
            ->withoutGlobalScopes()
            ->firstOrCreate([
                'organization_id' => $organization->id,
                'period' => $this->currentPeriod(),
                'family' => $family->value,
            ], [
                'used_units' => 0,
            ]);

        $row->increment('used_units', $units);
    }

    private function currentPeriod(): string {
        return Carbon::now()->format('Y-m');
    }
}
