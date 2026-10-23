<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaskSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Project\Search;

use App\Models\Platform\User;
use App\Models\Project\Task;
use App\Services\Integration\RecordOrigins;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use App\Support\EntityUrl;
use Illuminate\Support\Facades\Gate;

/**
 * Aufgaben nach Titel; Person = Bearbeiter, Kunde = Kunde des Projekts. Verlinkt
 * auf das Projekt bzw. die globalen Aufgaben; übernommene Aufgaben nennen ihr
 * Plugin (Feature 181).
 */
final class TaskSearchDomain implements SearchDomain {
    public function __construct(private readonly RecordOrigins $origins) {}

    public function key(): string {
        return 'tasks';
    }

    public function label(): string {
        return (string) __('search.domain.tasks');
    }

    public function icon(): string {
        return 'task_alt';
    }

    public function order(): int {
        return 22;
    }

    public function available(User $user): bool {
        return true;
    }

    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array {
        $gate = Gate::forUser($user);
        $query = Task::query()
            ->where('organization_id', $organizationId)
            ->whereNull('archived_at')
            ->whereLikeEscaped('title', $term)
            ->when($filters->person !== null, fn ($q) => $q->where('assigned_to', $filters->person))
            ->when($filters->customer !== null, fn ($q) => $q->whereHas('project', fn ($p) => $p->where('customer_id', $filters->customer)))
            ->with('project:id,name');
        $filters->range($query, 'created_at');

        $tasks = $query->orderByDesc('updated_at')->limit($limit * 2)->get()
            ->filter(fn (Task $t): bool => $gate->allows('view', $t))
            ->take($limit);
        $origins = $this->origins->labels(Task::class, array_map('intval', $tasks->modelKeys()), $organizationId);

        return array_values($tasks
            ->map(fn (Task $t) => [
                'id' => $t->id,
                'title' => (string) $t->title,
                'subtitle' => implode(' · ', array_filter([$t->project?->name, $origins[$t->id] ?? null, $t->status->label()])) ?: null,
                'url' => ($t->project !== null ? EntityUrl::for($t->project) : null) ?? route('tasks.global.index'),
            ])
            ->all());
    }
}
