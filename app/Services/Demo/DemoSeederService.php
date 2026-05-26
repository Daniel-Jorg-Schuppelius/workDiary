<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DemoSeederService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Demo;

use App\Enums\Diary\{LocationMode, Mode, Priority, Status as DiaryStatus};
use App\Enums\OpenIssue\{OpenIssueSeverity, OpenIssueSource, OpenIssueStatus, OpenIssueVisibility};
use App\Enums\Project\ProjectStatus;
use App\Enums\User\UserRole;
use App\Models\{Customer, DiaryEntry, OpenIssue, Organization, Project, TimeEntry, User};
use Carbon\CarbonImmutable;
use Faker\{Factory as FakerFactory, Generator as Faker};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{DB, Hash};
use RuntimeException;

/**
 * Seedet einen Demo-Mandanten mit deterministischen Inhalten (MVP-050).
 *
 * Determinismus: Fester Faker-Seed `42`, stabile Reihenfolge der Inserts.
 * Gleicher Aufruf ergibt vergleichbare Inhalte; `reset()` löscht alle
 * Demo-Daten der Org und re-seedet.
 *
 * Bewusst aus dem MVP ausgeklammert: Protokoll mit Signatur (komplexes
 * Setup), Prozedur-Run mit Backup-Proof und Vier-Augen-Schritt. Beide
 * folgen als separate Iteration; das Onboarding-Datenmodell ist nicht
 * blockiert.
 */
class DemoSeederService {
    public const DEMO_FAKER_SEED = 42;

    /**
     * Zählt die Datensätze, die im aktuellen Seed-Lauf erzeugt wurden.
     *
     * @return array{
     *   organization_id:int,
     *   customers:int,
     *   projects:int,
     *   users:int,
     *   main_diary_entries:int,
     *   background_diary_entries:int,
     *   time_entries:int,
     *   open_issues:int,
     * }
     */
    public function seed(Organization $organization, ?User $actor = null): array {
        $faker = FakerFactory::create('de_DE');
        $faker->seed(self::DEMO_FAKER_SEED);

        $counts = [
            'organization_id' => $organization->id,
            'customers' => 0,
            'projects' => 0,
            'users' => 0,
            'main_diary_entries' => 0,
            'background_diary_entries' => 0,
            'time_entries' => 0,
            'open_issues' => 0,
        ];

        DB::transaction(function () use ($organization, $faker, $actor, &$counts): void {
            $organization->is_demo = true;
            if (! str_ends_with($organization->name, '(Demo)')) {
                $organization->name = trim($organization->name . ' (Demo)');
            }
            $organization->demo_seeded_at = \Carbon\Carbon::now();
            $organization->save();

            $users = $this->seedUsers($organization, $actor);
            $counts['users'] = $users->count();

            $customers = $this->seedCustomers($organization, $users->first());
            $counts['customers'] = $customers->count();

            $projects = $this->seedProjects($organization, $customers, $users->first());
            $counts['projects'] = $projects->count();

            $mainCustomer = $customers->first();
            $mainProject = $projects->first();
            if ($mainCustomer === null || $mainProject === null) {
                throw new RuntimeException('Demo-Seeder: keine Kunden oder Projekte erzeugt.');
            }

            $mainDiary = $this->seedMainCase($organization, $mainCustomer, $mainProject, $users);
            $counts['main_diary_entries'] = 1;
            $counts['time_entries'] = TimeEntry::query()
                ->where('organization_id', $organization->id)
                ->where('diary_entry_id', $mainDiary->id)
                ->count();
            $counts['open_issues'] = OpenIssue::query()
                ->where('organization_id', $organization->id)
                ->where('subject_type', DiaryEntry::class)
                ->where('subject_id', $mainDiary->id)
                ->count();

            $background = $this->seedBackgroundEntries($organization, $customers, $projects, $users, $faker);
            $counts['background_diary_entries'] = $background;
        });

        return $counts;
    }

    /**
     * Löscht alle Demo-Daten der Org und re-seedet. Wird nur ausgeführt,
     * wenn `organizations.is_demo = true` ist (Defense in Depth).
     *
     * @return array<string, int>
     */
    public function reset(Organization $organization, ?User $actor = null): array {
        if (! $organization->is_demo) {
            throw new RuntimeException('Reset ist nur für Demo-Mandanten erlaubt (is_demo=true).');
        }

        DB::transaction(function () use ($organization): void {
            $diaryIds = DiaryEntry::query()->where('organization_id', $organization->id)->pluck('id');
            OpenIssue::query()
                ->where('organization_id', $organization->id)
                ->where('subject_type', DiaryEntry::class)
                ->whereIn('subject_id', $diaryIds)
                ->delete();
            TimeEntry::query()->where('organization_id', $organization->id)->delete();
            DiaryEntry::query()->where('organization_id', $organization->id)->delete();
            Project::query()->where('organization_id', $organization->id)->delete();
            Customer::query()->where('organization_id', $organization->id)->delete();

            // User der Org bleiben — sie hängen am Auth-Konto. Nur Demo-User
            // mit Marker-Email werden entfernt; reguläre Admin-Konten bleiben.
            User::query()
                ->where('organization_id', $organization->id)
                ->where('email', 'like', 'demo+%@workdiary.test')
                ->delete();
        });

        return $this->seed($organization, $actor);
    }

    /** @return Collection<int, User> */
    private function seedUsers(Organization $organization, ?User $actor): Collection {
        $specs = [
            ['name' => 'Demo Admin', 'role' => UserRole::Admin],
            ['name' => 'Demo Operator A', 'role' => UserRole::User],
            ['name' => 'Demo Operator B', 'role' => UserRole::User],
            ['name' => 'Demo Disponent', 'role' => UserRole::Teamleitung],
            ['name' => 'Demo Buchhaltung', 'role' => UserRole::Buchhaltung],
            ['name' => 'Demo Read-Only', 'role' => UserRole::Geschaeftsfuehrung],
        ];

        $created = collect();
        $index = 1;
        foreach ($specs as $spec) {
            $email = sprintf('demo+%02d@workdiary.test', $index);
            $user = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $spec['name'],
                    'password' => Hash::make('demo-password'),
                    'organization_id' => $organization->id,
                    'email_verified_at' => CarbonImmutable::now(),
                    'is_new_system' => true,
                ]
            );
            // Falls existierend, Org/Felder sicherstellen.
            if ((int) $user->organization_id !== (int) $organization->id) {
                $user->organization_id = $organization->id;
                $user->save();
            }
            $created->push($user);
            $index++;
        }

        return $created;
    }

    /** @return Collection<int, Customer> */
    private function seedCustomers(Organization $organization, ?User $createdBy): Collection {
        $specs = [
            ['name' => 'ACME GmbH', 'city' => 'Berlin'],
            ['name' => 'Beispiel-Apotheke', 'city' => 'Köln'],
            ['name' => 'Mustermann KG', 'city' => 'München'],
        ];

        $created = collect();
        foreach ($specs as $spec) {
            $customer = Customer::query()->firstOrCreate(
                ['organization_id' => $organization->id, 'name' => $spec['name']],
                [
                    'company' => $spec['name'],
                    'address_city' => $spec['city'],
                    'country' => 'DE',
                    'created_by' => $createdBy?->id,
                ]
            );
            $created->push($customer);
        }

        return $created;
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @return Collection<int, Project>
     */
    private function seedProjects(Organization $organization, Collection $customers, ?User $createdBy): Collection {
        /** @var array<int, list<string>> $projectNames */
        $projectNames = [
            0 => ['Server-Migration ACME', 'Helpdesk ACME'],
            1 => ['Wartung Apotheken-System'],
            2 => ['Netzwerk-Refresh Mustermann', 'Outlook-Migration Mustermann'],
        ];

        $created = collect();
        foreach ($customers as $index => $customer) {
            foreach ($projectNames[$index] ?? [] as $name) {
                $project = Project::query()->firstOrCreate(
                    ['organization_id' => $organization->id, 'name' => $name],
                    [
                        'customer_id' => $customer->id,
                        'status' => ProjectStatus::Active->value,
                        'created_by' => $createdBy?->id,
                    ]
                );
                $created->push($project);
            }
        }

        return $created;
    }

    /** @param Collection<int, User> $users */
    private function seedMainCase(Organization $organization, Customer $customer, Project $project, Collection $users): DiaryEntry {
        $admin = $users->first();
        if ($admin === null) {
            throw new RuntimeException('Demo-Seeder: keine User für Hauptfall vorhanden.');
        }
        $operatorA = $users->get(1) ?? $admin;
        $operatorB = $users->get(2) ?? $admin;

        $now = CarbonImmutable::now();

        $entry = DiaryEntry::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $admin->id,
            'assigned_user_id' => $operatorA->id,
            'project_id' => $project->id,
            'customer_id' => $customer->id,
            'title' => 'Server-Migration ACME — Beispielauftrag',
            'content' => 'Migration des Datei- und Druckerservers nach ACME-Vorgabe. Plan: 480 min.',
            'status' => DiaryStatus::InProgress->value,
            'priority' => Priority::High->value,
            'planned_minutes' => 480,
            'mode' => Mode::Fixed->value,
            'location_mode' => LocationMode::Onsite->value,
            'start_at' => $now->subDays(2)->setTime(9, 0),
            'end_at' => $now->subDays(2)->setTime(17, 0),
        ]);

        foreach (
            [
                ['user' => $operatorA, 'minutes' => 180],
                ['user' => $operatorB, 'minutes' => 120],
                ['user' => $operatorA, 'minutes' => 220],
            ] as $offset => $row
        ) {
            $started = $now->subDays(2)->setTime(9 + $offset * 2, 0);
            /** @var User $rowUser */
            $rowUser = $row['user'];
            TimeEntry::query()->create([
                'organization_id' => $organization->id,
                'project_id' => $project->id,
                'diary_entry_id' => $entry->id,
                'user_id' => $rowUser->id,
                'date' => $started->toDateString(),
                'started_at' => $started,
                'ended_at' => $started->addMinutes($row['minutes']),
                'minutes' => $row['minutes'],
                'kind' => 'work',
                'description' => 'Demo-Zeiterfassung Server-Migration',
                'billable' => true,
            ]);
        }

        OpenIssue::query()->create([
            'organization_id' => $organization->id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'source_type' => OpenIssueSource::Manual->value,
            'title' => 'Backup-Verifikation steht aus',
            'description' => 'Wiederherstellungstest mit Demo-Daten innerhalb einer Woche.',
            'severity' => OpenIssueSeverity::Medium->value,
            'status' => OpenIssueStatus::Open->value,
            'visibility' => OpenIssueVisibility::Internal->value,
            'assignee_user_id' => $admin->id,
            'created_by_user_id' => $admin->id,
            'due_at' => $now->addDays(7),
        ]);

        return $entry;
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, Project>  $projects
     * @param  Collection<int, User>  $users
     */
    private function seedBackgroundEntries(
        Organization $organization,
        Collection $customers,
        Collection $projects,
        Collection $users,
        Faker $faker
    ): int {
        $count = 25;
        $now = CarbonImmutable::now();

        if ($users->isEmpty() || $projects->isEmpty()) {
            return 0;
        }

        for ($i = 0; $i < $count; $i++) {
            $offsetDays = $faker->numberBetween(1, 60);
            $started = $now->subDays($offsetDays)->setTime($faker->numberBetween(8, 16), 0);
            $minutes = $faker->numberBetween(30, 240);
            /** @var Project $project */
            $project = $projects[$i % $projects->count()];
            /** @var User $user */
            $user = $users[$i % $users->count()];

            DiaryEntry::query()->create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'project_id' => $project->id,
                'customer_id' => $project->customer_id,
                'title' => 'Demo-Wartung #' . ($i + 1),
                'content' => $faker->sentence(8),
                'status' => DiaryStatus::Done->value,
                'priority' => Priority::Normal->value,
                'mode' => Mode::Fixed->value,
                'location_mode' => LocationMode::Onsite->value,
                'planned_minutes' => $minutes,
                'service_minutes' => $minutes,
                'start_at' => $started,
                'end_at' => $started->addMinutes($minutes),
            ]);
        }

        return $count;
    }
}
