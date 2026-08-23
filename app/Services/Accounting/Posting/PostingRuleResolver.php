<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PostingRuleResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Posting;

use App\Enums\Finance\{PostingAccountRole, PostingSourceKind};
use App\Models\Accounting\AccountingPostingRule;
use App\Models\Organization;
use Carbon\CarbonImmutable;

/**
 * Löst „welches Konto für diese Rolle?" auf (Feature 125, MVP-673).
 *
 * Auswahl: gültige Regeln der Quelle und Rolle, deren Merkmale zum Kontext
 * passen; es gewinnt die höhere Priorität, bei Gleichstand die spezifischere
 * Regel. Findet sich keine, gibt es **kein** Ersatzkonto — der Aufrufer
 * erzeugt einen Blocker.
 */
class PostingRuleResolver {
    /** @param array<string, mixed> $context */
    public function resolve(
        Organization $organization,
        PostingSourceKind $kind,
        PostingAccountRole $role,
        array $context,
        CarbonImmutable $on,
    ): ?AccountingPostingRule {
        $candidates = AccountingPostingRule::query()
            ->where('organization_id', $organization->id)
            ->where('source_kind', $kind->value)
            ->where('role', $role->value)
            ->validOn($on)
            ->with(['account', 'taxCode'])
            ->get()
            ->filter(fn (AccountingPostingRule $rule): bool => $rule->matches($context))
            ->filter(fn (AccountingPostingRule $rule): bool => $rule->account?->is_active === true);

        return $candidates
            ->sortByDesc(fn (AccountingPostingRule $rule): string => sprintf('%05d%05d', $rule->priority, $rule->specificity()))
            ->first();
    }

    /**
     * Alle Regeln einer Organisation für die Pflegeoberfläche.
     *
     * @return \Illuminate\Support\Collection<int, AccountingPostingRule>
     */
    public function all(Organization $organization): \Illuminate\Support\Collection {
        return AccountingPostingRule::query()
            ->where('organization_id', $organization->id)
            ->with(['account', 'taxCode'])
            ->orderBy('source_kind')
            ->orderBy('role')
            ->orderByDesc('priority')
            ->get();
    }
}
