<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubStarterPackService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Club;

use App\Enums\Club\{ClubEventKind, ClubResourceKind};
use App\Models\Club\{ClubAttendanceRequirement, ClubDepartment, ClubGradingSystem, ClubGroup, ClubHorse, ClubResource, ClubSportProfile};
use App\Models\Platform\{Organization, User};
use CommonToolkit\Helper\FileSystem\{File as ToolkitFile, Folder};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Startpakete je Sportart (Feature 159, MVP-848): dateibasierter Katalog
 * (`database/data/club/sportpacks/*.php`) — ein Paket legt Sportartenprofil,
 * Abteilung, Gruppen, Ressourcen und je nach Familie Graduierungsordnung,
 * Pferde oder Nachweisanforderung an. Idempotent nach Name; nichts wird
 * überschrieben, der Verein passt danach alles an.
 */
class ClubStarterPackService {
    public function __construct(
        private readonly ClubTeamService $teams,
        private readonly ClubGroupService $groups,
        private readonly ClubResourceService $resources,
        private readonly ClubGradingService $grading,
        private readonly ClubHorseService $horses,
        private readonly ClubCompetitionService $competitions,
    ) {}

    /**
     * Katalog: Code → Label, sortiert nach Label.
     *
     * @return array<string, string>
     */
    public function available(): array {
        $packs = [];
        foreach (Folder::findByPattern(database_path('data/club/sportpacks'), '*.php') as $file) {
            $pack = require $file;
            if (is_array($pack) && isset($pack['code'], $pack['label'])) {
                $packs[(string) $pack['code']] = (string) $pack['label'];
            }
        }
        asort($packs);

        return $packs;
    }

    /** @return array<string, mixed> */
    public function load(string $code): array {
        $code = preg_replace('/[^a-z0-9\-]/', '', mb_strtolower($code)) ?? '';
        $file = database_path('data/club/sportpacks/' . $code . '.php');
        if ($code === '' || ! ToolkitFile::exists($file)) {
            throw ValidationException::withMessages(['pack' => __('club.teams.error.pack_unknown')]);
        }
        $pack = require $file;
        if (! is_array($pack) || ! isset($pack['code'], $pack['profile'])) {
            throw ValidationException::withMessages(['pack' => __('club.teams.error.pack_unknown')]);
        }

        return $pack;
    }

    /**
     * Paket anlegen; vorhandene Einträge gleichen Namens bleiben unverändert.
     *
     * @return array{profile: ClubSportProfile, department: ClubDepartment|null, groups: int, resources: int, grading: bool, horses: int, requirement: bool, created: bool}
     */
    public function install(Organization $organization, string $code, User $actor): array {
        $pack = $this->load($code);

        return DB::transaction(function () use ($organization, $pack, $actor): array {
            $created = false;
            /** @var ClubSportProfile|null $profile */
            $profile = ClubSportProfile::query()->where('organization_id', $organization->id)->where('name', (string) $pack['profile']['name'])->first();
            if ($profile === null) {
                $profile = $this->teams->createProfile($organization, $pack['profile']);
                $created = true;
            }

            $department = null;
            if (isset($pack['department']['name'])) {
                $department = ClubDepartment::query()->where('organization_id', $organization->id)->where('name', (string) $pack['department']['name'])->first();
                if ($department === null) {
                    $department = ClubDepartment::query()->create([
                        'organization_id' => $organization->id,
                        'name' => (string) $pack['department']['name'],
                        'discipline' => $pack['department']['discipline'] ?? null,
                        'club_sport_profile_id' => $profile->id,
                        'is_active' => true,
                        'sort_order' => (int) ClubDepartment::query()->where('organization_id', $organization->id)->max('sort_order') + 1,
                    ]);
                } elseif ($department->club_sport_profile_id === null) {
                    $department->update(['club_sport_profile_id' => $profile->id]);
                }
            }

            $groups = 0;
            foreach ((array) ($pack['groups'] ?? []) as $spec) {
                if (ClubGroup::query()->where('organization_id', $organization->id)->where('name', (string) $spec['name'])->exists()) {
                    continue;
                }
                $this->groups->createGroup($organization, [
                    'name' => (string) $spec['name'],
                    'club_department_id' => $department?->id,
                    'admission_mode' => 'leader',
                    'min_age' => $spec['min_age'] ?? null,
                    'max_age' => $spec['max_age'] ?? null,
                    'discipline' => $spec['discipline'] ?? ($pack['department']['discipline'] ?? null),
                    'is_team' => (bool) ($spec['is_team'] ?? false),
                    'club_sport_profile_id' => ! empty($spec['is_team']) ? $profile->id : null,
                    'age_class' => $spec['age_class'] ?? null,
                    'is_active' => true,
                ]);
                $groups++;
            }

            $resources = 0;
            foreach ((array) ($pack['resources'] ?? []) as $spec) {
                $parent = $this->resource($organization, $spec, null, $resources);
                foreach ((array) ($spec['children'] ?? []) as $child) {
                    $this->resource($organization, $child, $parent, $resources);
                }
            }

            $grading = false;
            if (isset($pack['grading']['name']) && ! ClubGradingSystem::query()->where('organization_id', $organization->id)->where('name', (string) $pack['grading']['name'])->exists()) {
                $this->installGrading($organization, $pack['grading'], $actor);
                $grading = true;
            }

            $horses = 0;
            foreach ((array) ($pack['horses'] ?? []) as $spec) {
                if (ClubHorse::query()->where('organization_id', $organization->id)->where('name', (string) $spec['name'])->exists()) {
                    continue;
                }
                $this->horses->create($organization, $spec);
                $horses++;
            }

            $requirement = false;
            if (isset($pack['attendance_requirement']['name']) && ! ClubAttendanceRequirement::query()->where('organization_id', $organization->id)->where('name', (string) $pack['attendance_requirement']['name'])->exists()) {
                $spec = $pack['attendance_requirement'];
                $group = isset($spec['group']) ? ClubGroup::query()->where('organization_id', $organization->id)->where('name', (string) $spec['group'])->first() : null;
                $this->competitions->createRequirement($organization, [
                    'name' => (string) $spec['name'],
                    'club_group_id' => $group?->id,
                    'required_count' => (int) ($spec['required_count'] ?? 1),
                    'period_months' => (int) ($spec['period_months'] ?? 12),
                    'event_kind' => isset($spec['event_kind']) && ClubEventKind::tryFrom((string) $spec['event_kind']) !== null ? (string) $spec['event_kind'] : null,
                ]);
                $requirement = true;
            }

            $profile->audit('club.team.packInstalled', ['pack' => (string) $pack['code'], 'groups' => $groups, 'resources' => $resources, 'grading' => $grading, 'horses' => $horses, 'actor_id' => $actor->id]);

            return ['profile' => $profile, 'department' => $department, 'groups' => $groups, 'resources' => $resources, 'grading' => $grading, 'horses' => $horses, 'requirement' => $requirement, 'created' => $created];
        });
    }

    /** @param  array<string, mixed>  $spec */
    private function resource(Organization $organization, array $spec, ?ClubResource $parent, int &$count): ClubResource {
        /** @var ClubResource|null $existing */
        $existing = ClubResource::query()->where('organization_id', $organization->id)->where('name', (string) $spec['name'])->first();
        if ($existing !== null) {
            return $existing;
        }
        $count++;

        return $this->resources->create($organization, [
            'name' => (string) $spec['name'],
            'kind' => (ClubResourceKind::tryFrom((string) ($spec['kind'] ?? '')) ?? ClubResourceKind::Other)->value,
            'parent_id' => $parent?->id,
            'capacity' => (int) ($spec['capacity'] ?? 1),
            'setup_minutes' => (int) ($spec['setup_minutes'] ?? 0),
            'teardown_minutes' => (int) ($spec['teardown_minutes'] ?? 0),
            'requires_clearance' => (bool) ($spec['requires_clearance'] ?? false),
            'is_active' => true,
        ]);
    }

    /** @param  array<string, mixed>  $spec */
    private function installGrading(Organization $organization, array $spec, User $actor): void {
        $system = $this->grading->createSystem($organization, ['name' => (string) $spec['name'], 'discipline' => (string) ($spec['discipline'] ?? '')]);
        $grades = [];
        foreach ((array) ($spec['grades'] ?? []) as $rank => $name) {
            $grades[] = $this->grading->createGrade($system, ['name' => (string) $name, 'rank' => $rank + 1]);
        }
        $version = $this->grading->createVersion($system);
        $requirements = (array) ($spec['requirements'] ?? []);
        $previous = null;
        foreach ($grades as $grade) {
            $this->grading->saveRequirement($version, $grade, $requirements + ['previous_grade_id' => $previous?->id, 'counting_basis' => 'since_previous_grade']);
            $previous = $grade;
        }
        $this->grading->activateVersion($version, $actor);
        if (! $this->grading->isEnabled($organization)) {
            $this->grading->setEnabled($organization, true);
        }
    }
}
