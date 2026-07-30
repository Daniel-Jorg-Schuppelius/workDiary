<?php
/*
 * Created on   : Sat Jul 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoistPreflightService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Todoist\Services;

use App\Models\{ExternalReference, Organization, TodoistConnection, User};
use App\Plugins\Todoist\Api\TodoistApiClient;
use App\Plugins\Todoist\TodoistPlugin;

/**
 * Preflight vor der Aktivierung einer Projektzuordnung (Feature 055,
 * MVP-112): zählt cursor-basiert die aktiven Aufgaben und kennzeichnet, was
 * gesondert behandelt wird — Unteraufgaben, wiederkehrende Aufgaben,
 * Fälligkeiten mit Uhrzeit, nicht zuordenbare Bearbeiter und bereits
 * referenzierte Aufgaben. Kein unbeaufsichtigter Vollimport: Der Standard
 * bleibt „nur vorhandene zuordnen".
 */
class TodoistPreflightService {
    /**
     * @return array{
     *     tasks: int, subtasks: int, recurring: int, timed_due: int,
     *     unassignable: int, referenced: int,
     *     collaborators: list<array{id: string, name: string, email: string, mapped_user: string|null, suggestion: string|null}>
     * }
     */
    public function forProject(Organization $organization, TodoistConnection $connection, string $todoistProjectId): array {
        $api = new TodoistApiClient($connection);
        $tasks = $api->getTasks($todoistProjectId);
        $collaborators = $api->getCollaborators($todoistProjectId);

        $mappedCollaborators = ExternalReference::query()
            ->forPlugin($organization->id, TodoistPlugin::ID, TodoistPlugin::EXT_TYPE_COLLABORATOR)
            ->pluck('referenceable_id', 'external_id');

        $taskIds = array_values(array_filter(array_map(
            static fn (array $task): ?string => isset($task['id']) ? (string) $task['id'] : null,
            $tasks,
        )));
        $referenced = $taskIds === [] ? 0 : ExternalReference::query()
            ->forPlugin($organization->id, TodoistPlugin::ID, TodoistPlugin::EXT_TYPE_TASK)
            ->whereIn('external_id', $taskIds)
            ->count();

        $subtasks = 0;
        $recurring = 0;
        $timedDue = 0;
        $unassignable = 0;
        foreach ($tasks as $task) {
            if (! empty($task['parent_id'])) {
                $subtasks++;
            }
            if ((bool) ($task['due']['is_recurring'] ?? false)) {
                $recurring++;
            }
            if (! empty($task['due']['datetime'])) {
                $timedDue++;
            }
            $responsible = isset($task['responsible_uid']) ? (string) $task['responsible_uid'] : '';
            if ($responsible !== '' && ! $mappedCollaborators->has($responsible)) {
                $unassignable++;
            }
        }

        // Benutzer-Vorschläge: E-Mail-Gleichheit erzeugt NUR einen Vorschlag —
        // nie Auto-Anlage, nie org-fremde Konten.
        $usersByEmail = User::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('email')
            ->pluck('name', 'email')
            ->mapWithKeys(fn ($name, $email) => [mb_strtolower((string) $email) => (string) $name]);
        $mappedUserNames = User::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $mappedCollaborators->values())
            ->pluck('name', 'id');

        $collaboratorRows = [];
        foreach ($collaborators as $collaborator) {
            $externalId = isset($collaborator['id']) ? (string) $collaborator['id'] : '';
            $email = mb_strtolower(trim((string) ($collaborator['email'] ?? '')));
            $mappedUserId = $mappedCollaborators->get($externalId);
            $collaboratorRows[] = [
                'id' => $externalId,
                'name' => (string) ($collaborator['name'] ?? '—'),
                'email' => $email,
                'mapped_user' => $mappedUserId !== null ? (string) $mappedUserNames->get((int) $mappedUserId, '—') : null,
                'suggestion' => $mappedUserId === null && $email !== '' ? ($usersByEmail->get($email) ?? null) : null,
            ];
        }

        return [
            'tasks' => count($tasks),
            'subtasks' => $subtasks,
            'recurring' => $recurring,
            'timed_due' => $timedDue,
            'unassignable' => $unassignable,
            'referenced' => $referenced,
            'collaborators' => $collaboratorRows,
        ];
    }
}
