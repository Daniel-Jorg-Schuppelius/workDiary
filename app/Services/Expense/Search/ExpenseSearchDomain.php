<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Expense\Search;

use App\Models\Platform\User;
use App\Models\Travel\Expense;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use App\Support\CarbonFmt;

/**
 * Spesen nach Lieferant, Beschreibung und Erstattungsreferenz. ExpensePolicy::viewAny
 * ist bewusst `true` (Seitenzugriff); sichtbar sind wie im Web-Index eigene Belege,
 * alle nur für Admins (Vollaudit-Nacharbeit M8).
 */
final class ExpenseSearchDomain implements SearchDomain {
    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'expenses';
    }

    public function label(): string {
        return (string) __('Spesen');
    }

    public function icon(): string {
        return 'receipt_long';
    }

    public function order(): int {
        return 40;
    }

    public function available(User $user): bool {
        return $this->features->routeEnabled('expenses.index');
    }

    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array {
        $query = Expense::query()
            ->where('organization_id', $organizationId)
            ->where(fn ($q) => $q->whereLikeEscaped('vendor', $term)
                ->orWhereLikeEscaped('description', $term)
                ->orWhereLikeEscaped('reimbursement_reference', $term))
            ->when($filters->person !== null, fn ($q) => $q->where('user_id', $filters->person))
            ->when($filters->customer !== null, fn ($q) => $q->where('customer_id', $filters->customer));
        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }
        $filters->range($query, 'date', dateColumn: true);

        return array_values($query->orderByDesc('date')->limit($limit)->get()
            ->map(fn (Expense $e) => [
                'id' => $e->id,
                'title' => $e->vendor ?: ($e->description ?: (string) __('Spese #:id', ['id' => $e->id])),
                'subtitle' => CarbonFmt::fdate($e->date) . ' · ' . ($e->amount_gross?->format() ?? '0,00 €'),
                // Es gibt keine Detailseite — die Liste, auf den Tag eingegrenzt.
                'url' => route('expenses.index', ['from' => $e->date->toDateString(), 'to' => $e->date->toDateString()]),
            ])
            ->all());
    }
}
