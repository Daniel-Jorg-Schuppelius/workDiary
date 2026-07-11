<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisDeadlineService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Crisis;

use App\Models\Crisis\{CrisisCase, CrisisDeadlineTemplate};

/**
 * Meldefristen-Auflösung (Feature 070, D9): Fristen-Templates sind
 * Katalogdaten je Krisen-Kategorie — Org-Zeilen überschreiben die
 * globalen Defaults (organization_id NULL); die konkreten Termine werden
 * aus activated_at (Fallback: created_at) berechnet, nie hart codiert.
 */
class CrisisDeadlineService {
    /**
     * @return list<array{label: string, source: ?string, due_at: \Carbon\Carbon|null, immediate: bool, overdue: bool}>
     */
    public function deadlinesFor(CrisisCase $case): array {
        $orgTemplates = CrisisDeadlineTemplate::query()
            ->where('organization_id', $case->organization_id)
            ->where('category', $case->category)
            ->where('active', true)
            ->get();

        $templates = $orgTemplates->isNotEmpty()
            ? $orgTemplates
            : CrisisDeadlineTemplate::query()
                ->whereNull('organization_id')
                ->where('category', $case->category)
                ->where('active', true)
                ->get();

        $reference = $case->activated_at ?? $case->created_at;
        $result = [];
        foreach ($templates as $template) {
            $due = $template->offset_hours !== null && $reference !== null
                ? $reference->copy()->addHours((int) $template->offset_hours)
                : null;
            $result[] = [
                'label' => (string) $template->label,
                'source' => $template->source,
                'due_at' => $due,
                'immediate' => $template->offset_hours === null,
                'overdue' => $due !== null && $due->isPast() && ! in_array($case->status, ['closed', 'post_review', 'discarded', 'all_clear'], true),
            ];
        }

        return $result;
    }
}
