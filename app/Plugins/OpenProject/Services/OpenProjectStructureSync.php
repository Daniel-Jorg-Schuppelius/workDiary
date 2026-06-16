<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenProjectStructureSync.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\OpenProject\Services;

use App\Enums\Project\ProjectStatus;
use App\Enums\Task\{TaskPriority, TaskStatus};
use App\Models\{ExternalReference, Organization, Project, Task, User};
use App\Plugins\OpenProject\OpenProjectPlugin;
use App\Plugins\OpenProject\Sources\OpenProjectApiClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Synchronisiert die OpenProject-Projektstruktur in workDiary-Mappings und
 * stellt die gemeinsam von Import und Export genutzten Auflöser bereit:
 *
 *  - OpenProject-Projekt  → workDiary-Projekt   ({@see EXT_TYPE_PROJECT})
 *  - OpenProject-WorkPackage → workDiary-Aufgabe ({@see EXT_TYPE_WORK_PACKAGE})
 *  - OpenProject-Benutzer → workDiary-Benutzer   ({@see EXT_TYPE_USER})
 *
 * Zugeordnet wird ausschließlich über bestehende {@see ExternalReference} bzw.
 * Namens-/E-Mail-Gleichheit. Neu angelegt wird nur, wenn `create_missing_projects`
 * gesetzt ist; sonst bleibt Unzuordenbares unverknüpft (und landet beim Zeit-
 * Import in der Inbox).
 */
class OpenProjectStructureSync {
    public const EXT_TYPE_PROJECT = 'project';

    public const EXT_TYPE_WORK_PACKAGE = 'work_package';

    public const EXT_TYPE_USER = 'user';

    /**
     * Gleicht alle OpenProject-Projekte mit workDiary-Projekten ab.
     *
     * @param  array<string, mixed>  $config
     * @return array{linked: int, created: int, unmatched: int}
     */
    public function syncProjects(Organization $organization, array $config, OpenProjectApiClient $client): array {
        $linked = 0;
        $created = 0;
        $unmatched = 0;
        $createMissing = (bool) ($config['create_missing_projects'] ?? false);

        foreach ($client->fetchProjects() as $remote) {
            $existing = $this->resolveProject($organization, $remote->externalId);
            if ($existing !== null) {
                $this->rememberReference($organization, self::EXT_TYPE_PROJECT, $remote->externalId, $existing, ['name' => $remote->name]);
                $linked++;

                continue;
            }

            $match = $this->matchProjectByName($organization, $remote->name);
            if ($match === null && $createMissing) {
                $match = Project::query()->create([
                    'organization_id' => $organization->id,
                    'name' => $remote->name,
                    'status' => ProjectStatus::Active->value,
                    'is_default' => false,
                    'created_by' => Auth::id(),
                ]);
                $created++;
            }

            if ($match === null) {
                $unmatched++;

                continue;
            }

            $this->rememberReference($organization, self::EXT_TYPE_PROJECT, $remote->externalId, $match, ['name' => $remote->name]);
            $linked++;
        }

        return ['linked' => $linked, 'created' => $created, 'unmatched' => $unmatched];
    }

    /**
     * Gleicht alle OpenProject-Work-Packages mit workDiary-Aufgaben ab. Eine
     * Aufgabe kann nur angelegt/zugeordnet werden, wenn ihr OpenProject-Projekt
     * bereits einem workDiary-Projekt zugeordnet ist.
     *
     * @param  array<string, mixed>  $config
     * @return array{linked: int, created: int, unmatched: int}
     */
    public function syncWorkPackages(Organization $organization, array $config, OpenProjectApiClient $client): array {
        $linked = 0;
        $created = 0;
        $unmatched = 0;
        $createMissing = (bool) ($config['create_missing_projects'] ?? false);

        foreach ($client->fetchWorkPackages() as $remote) {
            $existing = $this->resolveTask($organization, $remote->externalId);
            if ($existing !== null) {
                $this->rememberReference($organization, self::EXT_TYPE_WORK_PACKAGE, $remote->externalId, $existing, ['subject' => $remote->subject]);
                $linked++;

                continue;
            }

            $project = $this->resolveProject($organization, $remote->projectExternalId);
            if ($project === null) {
                $unmatched++;

                continue;
            }

            $match = $this->matchTaskByTitle($organization, $project, $remote->subject);
            if ($match === null && $createMissing) {
                $match = Task::query()->create([
                    'organization_id' => $organization->id,
                    'project_id' => $project->id,
                    'title' => $remote->subject,
                    'status' => TaskStatus::Open->value,
                    'priority' => TaskPriority::Medium->value,
                    'created_by' => Auth::id(),
                ]);
                $created++;
            }

            if ($match === null) {
                $unmatched++;

                continue;
            }

            $this->rememberReference($organization, self::EXT_TYPE_WORK_PACKAGE, $remote->externalId, $match, ['subject' => $remote->subject]);
            $linked++;
        }

        return ['linked' => $linked, 'created' => $created, 'unmatched' => $unmatched];
    }

    /**
     * Ordnet OpenProject-Benutzer per E-Mail workDiary-Benutzern zu (Best-Effort;
     * E-Mails sind nur für OpenProject-Admins sichtbar).
     *
     * @return array{linked: int, unmatched: int}
     */
    public function syncUsers(Organization $organization, OpenProjectApiClient $client): array {
        $linked = 0;
        $unmatched = 0;

        foreach ($client->fetchUsers() as $remote) {
            if ($remote->email === null) {
                $unmatched++;

                continue;
            }

            $user = User::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($remote->email)])
                ->first();

            if ($user === null) {
                $unmatched++;

                continue;
            }

            $this->rememberReference($organization, self::EXT_TYPE_USER, $remote->externalId, $user, ['name' => $remote->name, 'email' => $remote->email]);
            $linked++;
        }

        return ['linked' => $linked, 'unmatched' => $unmatched];
    }

    /**
     * Merkt eine Projekt-Zuordnung (OpenProject-Projekt-ID → workDiary-Projekt),
     * z. B. wenn die Inbox eine Gruppe einem Projekt zuweist — Folgeimporte
     * matchen dann automatisch.
     */
    public function linkProject(Organization $organization, string $externalId, Project $project, ?string $name = null): void {
        $this->rememberReference($organization, self::EXT_TYPE_PROJECT, $externalId, $project, $name !== null ? ['name' => $name] : []);
    }

    /** OpenProject-Projekt-ID → workDiary-Projekt (über die gespeicherte Reference). */
    public function resolveProject(Organization $organization, ?string $externalId): ?Project {
        if ($externalId === null || $externalId === '') {
            return null;
        }

        $ref = $this->reference($organization, self::EXT_TYPE_PROJECT, $externalId);

        return $ref?->referenceable instanceof Project ? $ref->referenceable : null;
    }

    /** OpenProject-WorkPackage-ID → workDiary-Aufgabe (über die gespeicherte Reference). */
    public function resolveTask(Organization $organization, ?string $externalId): ?Task {
        if ($externalId === null || $externalId === '') {
            return null;
        }

        $ref = $this->reference($organization, self::EXT_TYPE_WORK_PACKAGE, $externalId);

        return $ref?->referenceable instanceof Task ? $ref->referenceable : null;
    }

    /** OpenProject-Benutzer-ID → workDiary-Benutzer-ID (über die gespeicherte Reference). */
    public function resolveUserId(Organization $organization, ?string $externalId): ?int {
        if ($externalId === null || $externalId === '') {
            return null;
        }

        $ref = $this->reference($organization, self::EXT_TYPE_USER, $externalId);

        return $ref?->referenceable instanceof User ? (int) $ref->referenceable->id : null;
    }

    /**
     * Rückwärtsauflösung (für den Export): workDiary-Modell → OpenProject-ID
     * unter dem gegebenen Mapping-Typ.
     */
    public function externalIdFor(Organization $organization, Model $model, string $type): ?string {
        $ref = ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', OpenProjectPlugin::ID)
            ->where('external_type', $type)
            ->where('referenceable_type', $model->getMorphClass())
            ->where('referenceable_id', $model->getKey())
            ->first();

        return $ref?->external_id;
    }

    /**
     * Alle gemerkten Struktur-Zuordnungen der Organisation (für die Mapping-UI),
     * inkl. aufgelöstem Ziel.
     *
     * @return Collection<int, ExternalReference>
     */
    public function mappings(Organization $organization): Collection {
        return ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', OpenProjectPlugin::ID)
            ->whereIn('external_type', [self::EXT_TYPE_PROJECT, self::EXT_TYPE_WORK_PACKAGE, self::EXT_TYPE_USER])
            ->with('referenceable')
            ->orderBy('external_type')
            ->orderBy('external_id')
            ->get();
    }

    private function matchProjectByName(Organization $organization, string $name): ?Project {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        return Project::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNull('archived_at')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();
    }

    private function matchTaskByTitle(Organization $organization, Project $project, string $title): ?Task {
        $title = trim($title);
        if ($title === '') {
            return null;
        }

        return Task::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('project_id', $project->id)
            ->whereNull('archived_at')
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)])
            ->first();
    }

    private function reference(Organization $organization, string $type, string $externalId): ?ExternalReference {
        return ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', OpenProjectPlugin::ID)
            ->where('external_type', $type)
            ->where('external_id', $externalId)
            ->with('referenceable')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function rememberReference(Organization $organization, string $type, string $externalId, Model $referenceable, array $payload = []): void {
        ExternalReference::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'plugin_id' => OpenProjectPlugin::ID,
                'external_type' => $type,
                'external_id' => $externalId,
            ],
            [
                'referenceable_type' => $referenceable->getMorphClass(),
                'referenceable_id' => $referenceable->getKey(),
                'payload' => $payload !== [] ? $payload : null,
                'synced_at' => now(),
            ],
        );
    }
}
