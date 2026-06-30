<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenProjectGroupBooker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OpenProject;

use App\Models\Organization;
use App\Plugins\OpenProject\Services\OpenProjectImportService;
use App\Services\Integration\Concerns\ResolvesInboxTargets;
use App\Services\Integration\InboxGroupBooker;
use Illuminate\Support\Collection;

/**
 * Bindet die gruppierten OpenProject-Zeit-Import-Einträge an die universelle
 * Zuordnungs-Inbox: liefert die offenen Projekt-Gruppen, löst ein Projekt
 * (existierend-oder-neu, kundenlos erlaubt) auf und delegiert Buchung/Verwerfen
 * an den {@see OpenProjectImportService}.
 */
class OpenProjectGroupBooker implements InboxGroupBooker {
    use ResolvesInboxTargets;

    public function __construct(private readonly OpenProjectImportService $service) {}

    public function groups(Organization $organization): Collection {
        /** @var Collection<int, array<string, mixed>> $groups */
        $groups = $this->service->openInboxGroups($organization)->map(fn(array $group): array => [
            'plugin_id' => OpenProjectPlugin::ID,
            'form' => 'project',
            'group_key' => $group['group_key'],
            'project_name' => $group['project_name'],
            'count' => $group['count'],
            'minutes' => $group['minutes'],
            'first_seen' => $group['first_seen'],
            'last_seen' => $group['last_seen'],
            'suggested_project_sqid' => null,
        ])->values();

        return $groups;
    }

    public function rules(): array {
        return [
            'project_mode' => ['required', 'in:existing,new'],
            'project' => ['nullable', 'string', 'required_if:project_mode,existing'],
            'new_project_name' => ['nullable', 'string', 'max:191', 'required_if:project_mode,new'],
        ];
    }

    public function book(Organization $organization, string $groupKey, array $input): array {
        $project = $this->resolveStandaloneProject($organization, $input);

        return $this->service->bookInboxGroup($organization, $groupKey, $project);
    }

    public function dismiss(Organization $organization, string $groupKey): int {
        return $this->service->dismissInboxGroup($organization, $groupKey);
    }
}
