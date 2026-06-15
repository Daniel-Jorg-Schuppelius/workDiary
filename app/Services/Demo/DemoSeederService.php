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

use App\Enums\Asset\{AssetClass, AssetHealth, AssetOwnership, AssetStatus};
use App\Enums\Communication\{CommunicationDirection, CommunicationNoteType, CommunicationVisibility};
use App\Enums\Demo\DemoIndustry;
use App\Enums\Diary\{LocationMode, Mode, Priority, Status as DiaryStatus};
use App\Enums\OpenIssue\{OpenIssueSeverity, OpenIssueSource, OpenIssueStatus, OpenIssueVisibility};
use App\Enums\Project\ProjectStatus;
use App\Enums\Protocol\{ProtocolItemResult, ProtocolItemType, ProtocolStatus, ProtocolType, ProtocolVisibility};
use App\Enums\Timesheet\{TimesheetKind, TimesheetStatus};
use App\Enums\User\UserRole;
use App\Models\{Asset, CommunicationNote, Customer, DiaryEntry, Material, MaterialUsage, OpenIssue, Organization, Project, Protocol, ProtocolItem, TimeEntry, Timesheet, User};
use App\Services\Classification\BranchProfileInstaller;
use Carbon\CarbonImmutable;
use Faker\{Factory as FakerFactory, Generator as Faker};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{DB, Hash};
use RuntimeException;

/**
 * Seedet einen Demo-Mandanten mit deterministischen, branchenspezifischen
 * End-to-End-Inhalten (Feature 040 / MVP-050).
 *
 * Determinismus: Fester Faker-Seed `42`, stabile Reihenfolge der Inserts.
 * Gleicher Aufruf (gleiche Branche) ergibt vergleichbare Inhalte; `reset()`
 * löscht alle Demo-Daten der Org und re-seedet — ausschließlich für Orgs mit
 * `is_demo = true` (harter Schutz, niemals echte Mandanten).
 *
 * Je Musterbranche wird zusätzlich das passende Branchenprofil über den
 * {@see BranchProfileInstaller} installiert (Klassifikationen, Tags, SLAs,
 * Prozedurvorlagen …), sodass die Demo auf realer Branchenkonfiguration
 * aufsetzt. Über die {@see DemoIndustry} unterscheiden sich Firma, Kunden,
 * Projekte, Hauptauftrag, Material und Asset erkennbar.
 *
 * Das Onboarding-Hauptszenario deckt nun ab: Kunden, Projekte, Aufträge in
 * verschiedenen Stati, Zeitbuchungen, Material (über einen Stundenzettel),
 * ein signiertes Abnahmeprotokoll mit Prüfpunkten, einen offenen Punkt, ein
 * Asset und einen Kommunikationseintrag.
 */
class DemoSeederService {
    public const DEMO_FAKER_SEED = 42;

    public function __construct(
        private readonly ?BranchProfileInstaller $branchProfiles = null,
    ) {}

    private function branchProfileInstaller(): BranchProfileInstaller {
        return $this->branchProfiles ?? app(BranchProfileInstaller::class);
    }

    /**
     * Zählt die Datensätze, die im aktuellen Seed-Lauf erzeugt wurden. Schlüssel:
     * organization_id, industry, branch_profile, customers, projects, users,
     * main_diary_entries, background_diary_entries, time_entries, open_issues,
     * materials, material_usages, assets, protocols, communication_notes.
     *
     * @return array<string, int|string>
     */
    public function seed(Organization $organization, ?User $actor = null, ?DemoIndustry $industry = null): array {
        return $this->withOrganizationContext($organization, fn(): array => $this->doSeed($organization, $actor, $industry));
    }

    /**
     * @return array<string, int|string>
     */
    private function doSeed(Organization $organization, ?User $actor, ?DemoIndustry $industry): array {
        $industry ??= DemoIndustry::default();

        $faker = FakerFactory::create('de_DE');
        $faker->seed(self::DEMO_FAKER_SEED);

        $blueprint = $this->blueprint($industry);

        $counts = [
            'organization_id' => $organization->id,
            'industry' => $industry->value,
            'branch_profile' => $industry->branchProfileCode(),
            'customers' => 0,
            'projects' => 0,
            'users' => 0,
            'main_diary_entries' => 0,
            'background_diary_entries' => 0,
            'time_entries' => 0,
            'open_issues' => 0,
            'materials' => 0,
            'material_usages' => 0,
            'assets' => 0,
            'protocols' => 0,
            'communication_notes' => 0,
        ];

        DB::transaction(function () use ($organization, $faker, $actor, $industry, $blueprint, &$counts): void {
            $organization->is_demo = true;
            if (! str_ends_with($organization->name, '(Demo)')) {
                $organization->name = trim($organization->name . ' (Demo)');
            }
            $settings = is_array($organization->settings) ? $organization->settings : [];
            $settings['demo_industry'] = $industry->value;
            $organization->settings = $settings;
            $organization->demo_seeded_at = \Carbon\Carbon::now();
            $organization->save();

            // Branchenprofil installieren (Klassifikationen, Tags, SLAs, Prozeduren …).
            $this->branchProfileInstaller()->install($organization, $industry->branchProfileCode(), $actor);

            $users = $this->seedUsers($organization, $actor);
            $counts['users'] = $users->count();

            $customers = $this->seedCustomers($organization, $users->first(), $blueprint);
            $counts['customers'] = $customers->count();

            $projects = $this->seedProjects($organization, $customers, $users->first(), $blueprint);
            $counts['projects'] = $projects->count();

            $mainCustomer = $customers->first();
            $mainProject = $projects->first();
            if ($mainCustomer === null || $mainProject === null) {
                throw new RuntimeException('Demo-Seeder: keine Kunden oder Projekte erzeugt.');
            }

            $asset = $this->seedAsset($organization, $mainCustomer, $blueprint);
            $counts['assets'] = 1;

            $materials = $this->seedMaterials($organization, $blueprint);
            $counts['materials'] = $materials->count();

            $mainDiary = $this->seedMainCase($organization, $mainCustomer, $mainProject, $users, $blueprint);
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

            $counts['material_usages'] = $this->seedMaterialUsage($organization, $mainProject, $mainDiary, $materials, $asset);
            $counts['protocols'] = $this->seedAcceptanceProtocol($organization, $mainDiary, $users, $blueprint);
            $counts['communication_notes'] = $this->seedCommunication($organization, $mainDiary, $users, $blueprint);

            $background = $this->seedBackgroundEntries($organization, $customers, $projects, $users, $faker, $blueprint);
            $counts['background_diary_entries'] = $background;
        });

        return $counts;
    }

    /**
     * Löscht alle Demo-Daten der Org und re-seedet. Wird nur ausgeführt,
     * wenn `organizations.is_demo = true` ist (Defense in Depth, harter Schutz).
     *
     * @return array<string, int|string>
     */
    public function reset(Organization $organization, ?User $actor = null, ?DemoIndustry $industry = null): array {
        if (! $organization->is_demo) {
            throw new RuntimeException('Reset ist nur für Demo-Mandanten erlaubt (is_demo=true).');
        }

        // Beibehaltung der ursprünglich gewählten Branche, sofern nicht überschrieben.
        $industry ??= $this->resolveIndustry($organization);

        return $this->withOrganizationContext($organization, fn(): array => $this->doReset($organization, $actor, $industry));
    }

    /**
     * @return array<string, int|string>
     */
    private function doReset(Organization $organization, ?User $actor, ?DemoIndustry $industry): array {
        DB::transaction(function () use ($organization): void {
            $diaryIds = DiaryEntry::query()->where('organization_id', $organization->id)->pluck('id');

            // Protokolle der Demo-Aufträge (inkl. Items über DB-Cascade/explizit).
            $protocolIds = Protocol::query()
                ->where('organization_id', $organization->id)
                ->where('subject_type', DiaryEntry::class)
                ->whereIn('subject_id', $diaryIds)
                ->pluck('id');
            ProtocolItem::query()->whereIn('protocol_id', $protocolIds)->delete();
            Protocol::query()->whereIn('id', $protocolIds)->delete();

            CommunicationNote::query()
                ->where('organization_id', $organization->id)
                ->where('notable_type', DiaryEntry::class)
                ->whereIn('notable_id', $diaryIds)
                ->delete();

            OpenIssue::query()
                ->where('organization_id', $organization->id)
                ->where('subject_type', DiaryEntry::class)
                ->whereIn('subject_id', $diaryIds)
                ->delete();

            MaterialUsage::query()->where('organization_id', $organization->id)->delete();
            Timesheet::query()->where('organization_id', $organization->id)->delete();
            Material::query()->where('organization_id', $organization->id)->delete();
            Asset::query()->where('organization_id', $organization->id)->delete();
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

        return $this->doSeed($organization, $actor, $industry);
    }

    /** Ermittelt die aktuell hinterlegte Demo-Branche aus den Org-Einstellungen. */
    public function resolveIndustry(Organization $organization): DemoIndustry {
        $settings = is_array($organization->settings) ? $organization->settings : [];
        $key = isset($settings['demo_industry']) ? (string) $settings['demo_industry'] : null;

        return DemoIndustry::fromKey($key);
    }

    /**
     * Bindet `currentOrganization` auf die Ziel-Org, damit der OrganizationScope
     * (Multi-Tenancy) korrekt auf den Demo-Mandanten wirkt — unabhängig vom
     * Aufrufkontext (HTTP, Konsole, mehrere Orgs hintereinander). Der vorherige
     * Bindungszustand wird anschließend wiederhergestellt.
     *
     * @template T
     *
     * @param  \Closure():T  $callback
     * @return T
     */
    private function withOrganizationContext(Organization $organization, \Closure $callback): mixed {
        $hadPrevious = app()->bound('currentOrganization');
        $previous = $hadPrevious ? app('currentOrganization') : null;

        app()->instance('currentOrganization', $organization);

        try {
            return $callback();
        } finally {
            if ($hadPrevious && $previous instanceof Organization) {
                app()->instance('currentOrganization', $previous);
            } else {
                app()->forgetInstance('currentOrganization');
            }
        }
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

    /**
     * @param  array<string, mixed>  $blueprint
     * @return Collection<int, Customer>
     */
    private function seedCustomers(Organization $organization, ?User $createdBy, array $blueprint): Collection {
        /** @var list<array{name:string, city:string}> $specs */
        $specs = $blueprint['customers'];

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
     * @param  array<string, mixed>  $blueprint
     * @return Collection<int, Project>
     */
    private function seedProjects(Organization $organization, Collection $customers, ?User $createdBy, array $blueprint): Collection {
        /** @var array<int, list<string>> $projectNames */
        $projectNames = $blueprint['projects'];

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

    /** @param array<string, mixed> $blueprint */
    private function seedAsset(Organization $organization, Customer $customer, array $blueprint): Asset {
        /** @var array{name:string, manufacturer:string, model:string, class:AssetClass, location:string} $spec */
        $spec = $blueprint['asset'];

        return Asset::query()->create([
            'organization_id' => $organization->id,
            'asset_no' => 'AS-DEMO-0001',
            'asset_class' => $spec['class']->value,
            'name' => $spec['name'],
            'manufacturer' => $spec['manufacturer'],
            'model' => $spec['model'],
            'serial_no' => 'SN-DEMO-0001',
            'inventory_no' => 'INV-DEMO-0001',
            'customer_id' => $customer->id,
            'owned_by' => AssetOwnership::Customer->value,
            'location_text' => $spec['location'],
            'status' => AssetStatus::Active->value,
            'health' => AssetHealth::Ok->value,
            'commissioned_on' => CarbonImmutable::now()->subMonths(8)->toDateString(),
            'warranty_until' => CarbonImmutable::now()->addYear()->toDateString(),
            'notes' => 'Demo-Asset (generisch).',
        ]);
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @return Collection<int, Material>
     */
    private function seedMaterials(Organization $organization, array $blueprint): Collection {
        /** @var list<array{sku:string, name:string, unit:string, price:string}> $specs */
        $specs = $blueprint['materials'];

        $created = collect();
        foreach ($specs as $spec) {
            $material = Material::query()->firstOrCreate(
                ['organization_id' => $organization->id, 'sku' => $spec['sku']],
                [
                    'name' => $spec['name'],
                    'unit' => $spec['unit'],
                    'default_unit_price' => $spec['price'],
                    'tax_rate' => '19.00',
                    'is_active' => true,
                ]
            );
            $created->push($material);
        }

        return $created;
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  array<string, mixed>  $blueprint
     */
    private function seedMainCase(Organization $organization, Customer $customer, Project $project, Collection $users, array $blueprint): DiaryEntry {
        $admin = $users->first();
        if ($admin === null) {
            throw new RuntimeException('Demo-Seeder: keine User für Hauptfall vorhanden.');
        }
        $operatorA = $users->get(1) ?? $admin;
        $operatorB = $users->get(2) ?? $admin;

        $now = CarbonImmutable::now();

        /** @var array<string, string> $main */
        $main = $blueprint['main_case'];

        $entry = DiaryEntry::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $admin->id,
            'assigned_user_id' => $operatorA->id,
            'project_id' => $project->id,
            'customer_id' => $customer->id,
            'title' => $main['title'],
            'content' => $main['content'],
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
                'description' => $main['time_desc'],
                'billable' => true,
            ]);
        }

        OpenIssue::query()->create([
            'organization_id' => $organization->id,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'source_type' => OpenIssueSource::Manual->value,
            'title' => $main['open_issue_title'],
            'description' => $main['open_issue_desc'],
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
     * Legt einen abgeschlossenen Stundenzettel für den Hauptauftrag an und
     * bucht Material (über den Stundenzettel — MaterialUsage hängt am Timesheet).
     *
     * @param  Collection<int, Material>  $materials
     */
    private function seedMaterialUsage(Organization $organization, Project $project, DiaryEntry $entry, Collection $materials, Asset $asset): int {
        if ($materials->isEmpty()) {
            return 0;
        }

        $admin = User::query()->where('organization_id', $organization->id)->first();
        $now = CarbonImmutable::now();

        $timesheet = Timesheet::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'user_id' => $admin?->id,
            'kind' => TimesheetKind::Project->value,
            'work_date' => $now->subDays(2)->toDateString(),
            'status' => TimesheetStatus::Submitted->value,
            'notes' => 'Demo-Stundenzettel mit Materialbuchung zum Hauptauftrag.',
        ]);

        // Materialeinträge verknüpfen TimeEntries an den Stundenzettel.
        TimeEntry::query()
            ->where('organization_id', $organization->id)
            ->where('diary_entry_id', $entry->id)
            ->update(['timesheet_id' => $timesheet->id]);

        $usageSpecs = [
            ['index' => 0, 'quantity' => '5.000', 'asset' => true],
            ['index' => 1, 'quantity' => '2.000', 'asset' => false],
            ['index' => 2, 'quantity' => '1.000', 'asset' => false],
        ];

        $created = 0;
        foreach ($usageSpecs as $spec) {
            /** @var Material|null $material */
            $material = $materials->get($spec['index']);
            if ($material === null) {
                continue;
            }
            MaterialUsage::query()->create([
                'organization_id' => $organization->id,
                'timesheet_id' => $timesheet->id,
                'material_id' => $material->id,
                'asset_id' => $spec['asset'] ? $asset->id : null,
                'description' => $material->name,
                'quantity' => $spec['quantity'],
                'unit' => $material->unit,
                'unit_price' => (string) $material->default_unit_price,
                'tax_rate' => '19.00',
                'billed' => false,
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * Legt ein signiertes Abnahmeprotokoll mit Prüfpunkten zum Hauptauftrag an.
     *
     * @param  Collection<int, User>  $users
     * @param  array<string, mixed>  $blueprint
     */
    private function seedAcceptanceProtocol(Organization $organization, DiaryEntry $entry, Collection $users, array $blueprint): int {
        $admin = $users->first();
        if ($admin === null) {
            return 0;
        }

        /** @var array{protocol_title:string, protocol_items:list<array{label:string, result:ProtocolItemResult}>, ...} $main */
        $main = $blueprint['main_case'];
        $now = CarbonImmutable::now();

        $protocol = Protocol::query()->create([
            'organization_id' => $organization->id,
            'type' => ProtocolType::Acceptance->value,
            'subject_type' => DiaryEntry::class,
            'subject_id' => $entry->id,
            'title' => $main['protocol_title'],
            'description' => 'Abnahmeprotokoll zum Demo-Hauptauftrag (generisch).',
            'status' => ProtocolStatus::Signed->value,
            'revision' => 1,
            'visibility' => ProtocolVisibility::Customer->value,
            'occurred_at' => $now->subDays(2)->setTime(16, 30),
            'created_by_user_id' => $admin->id,
            'signed_at' => $now->subDays(2)->setTime(17, 0),
        ]);

        $sort = 0;
        foreach ($main['protocol_items'] as $item) {
            $sort += 10;
            ProtocolItem::query()->create([
                'protocol_id' => $protocol->id,
                'sort_order' => $sort,
                'item_type' => ProtocolItemType::Boolean->value,
                'label' => $item['label'],
                'required' => true,
                'result' => $item['result']->value,
                'measured_at' => $now->subDays(2)->setTime(16, 0),
                'measured_by_user_id' => $admin->id,
            ]);
        }

        return 1;
    }

    /**
     * Legt einen Kommunikationseintrag (Anruf mit Folgeaktion) zum Hauptauftrag an.
     *
     * @param  Collection<int, User>  $users
     * @param  array<string, mixed>  $blueprint
     */
    private function seedCommunication(Organization $organization, DiaryEntry $entry, Collection $users, array $blueprint): int {
        $admin = $users->first();
        if ($admin === null) {
            return 0;
        }

        /** @var array<string, string> $main */
        $main = $blueprint['main_case'];
        $now = CarbonImmutable::now();

        CommunicationNote::query()->create([
            'organization_id' => $organization->id,
            'notable_type' => DiaryEntry::class,
            'notable_id' => $entry->id,
            'type' => CommunicationNoteType::Call->value,
            'direction' => CommunicationDirection::Outbound->value,
            'occurred_at' => $now->subDays(2)->setTime(15, 0),
            'subject' => $main['comm_subject'],
            'body' => $main['comm_body'],
            'next_action' => 'Abnahmeprotokoll an Kunden senden',
            'next_action_due_at' => $now->addDay(),
            'next_action_user_id' => $admin->id,
            'visibility' => CommunicationVisibility::Internal->value,
            'confidential' => false,
            'created_by_user_id' => $admin->id,
        ]);

        return 1;
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, Project>  $projects
     * @param  Collection<int, User>  $users
     * @param  array<string, mixed>  $blueprint
     */
    private function seedBackgroundEntries(
        Organization $organization,
        Collection $customers,
        Collection $projects,
        Collection $users,
        Faker $faker,
        array $blueprint
    ): int {
        $count = 25;
        $now = CarbonImmutable::now();

        if ($users->isEmpty() || $projects->isEmpty()) {
            return 0;
        }

        /** @var string $titlePrefix */
        $titlePrefix = $blueprint['background_title'];

        // Mischung aus Stati, damit Auswertungen/Kanban sinnvoll aussehen.
        $statuses = [
            DiaryStatus::Done->value,
            DiaryStatus::Done->value,
            DiaryStatus::Done->value,
            DiaryStatus::InProgress->value,
            DiaryStatus::Planned->value,
        ];

        for ($i = 0; $i < $count; $i++) {
            $offsetDays = $faker->numberBetween(1, 60);
            $started = $now->subDays($offsetDays)->setTime($faker->numberBetween(8, 16), 0);
            $minutes = $faker->numberBetween(30, 240);
            /** @var Project $project */
            $project = $projects[$i % $projects->count()];
            /** @var User $user */
            $user = $users[$i % $users->count()];
            $status = $statuses[$i % count($statuses)];

            DiaryEntry::query()->create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'project_id' => $project->id,
                'customer_id' => $project->customer_id,
                'title' => $titlePrefix . ' #' . ($i + 1),
                'content' => $faker->sentence(8),
                'status' => $status,
                'priority' => Priority::Normal->value,
                'mode' => Mode::Fixed->value,
                'location_mode' => LocationMode::Onsite->value,
                'planned_minutes' => $minutes,
                'service_minutes' => $status === DiaryStatus::Done->value ? $minutes : null,
                'start_at' => $started,
                'end_at' => $status === DiaryStatus::Planned->value ? null : $started->addMinutes($minutes),
            ]);
        }

        return $count;
    }

    /**
     * Branchenspezifischer Demo-Inhalt (generisch, keine echten Firmen/Personen).
     *
     * @return array<string, mixed>
     */
    private function blueprint(DemoIndustry $industry): array {
        return match ($industry) {
            DemoIndustry::ItService => [
                'customers' => [
                    ['name' => 'ACME GmbH', 'city' => 'Berlin'],
                    ['name' => 'Beispiel-Apotheke', 'city' => 'Köln'],
                    ['name' => 'Mustermann KG', 'city' => 'München'],
                ],
                'projects' => [
                    0 => ['Server-Migration ACME', 'Helpdesk ACME'],
                    1 => ['Wartung Apotheken-System'],
                    2 => ['Netzwerk-Refresh Mustermann', 'Outlook-Migration Mustermann'],
                ],
                'asset' => [
                    'name' => 'Demo-Server ACME-SRV-01',
                    'manufacturer' => 'Beispiel Systems',
                    'model' => 'RX-2000',
                    'class' => AssetClass::Device,
                    'location' => 'Serverraum Berlin',
                ],
                'materials' => [
                    ['sku' => 'IT-SW-24', 'name' => 'Switch 24-Port Gigabit', 'unit' => 'Stk', 'price' => '189.0000'],
                    ['sku' => 'IT-PATCH-2M', 'name' => 'Patchkabel Cat6 2m', 'unit' => 'Stk', 'price' => '4.5000'],
                    ['sku' => 'IT-USV-1500', 'name' => 'USV 1500VA', 'unit' => 'Stk', 'price' => '349.0000'],
                ],
                'main_case' => [
                    'title' => 'Server-Migration ACME — Beispielauftrag',
                    'content' => 'Migration des Datei- und Druckerservers nach ACME-Vorgabe. Plan: 480 min.',
                    'time_desc' => 'Demo-Zeiterfassung Server-Migration',
                    'open_issue_title' => 'Backup-Verifikation steht aus',
                    'open_issue_desc' => 'Wiederherstellungstest mit Demo-Daten innerhalb einer Woche.',
                    'protocol_title' => 'Abnahme Server-Migration ACME',
                    'protocol_items' => [
                        ['label' => 'Dienste laufen nach Migration', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Backup erfolgreich eingerichtet', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Wiederherstellungstest durchgeführt', 'result' => ProtocolItemResult::Open],
                    ],
                    'comm_subject' => 'Abstimmung Wartungsfenster mit ACME',
                    'comm_body' => 'Telefonat mit Kunde zur Bestätigung des Migrationsfensters und Abnahme.',
                ],
                'background_title' => 'Demo-Wartung',
            ],
            DemoIndustry::Elektro => [
                'customers' => [
                    ['name' => 'Wohnbau Muster eG', 'city' => 'Hamburg'],
                    ['name' => 'Bäckerei Beispiel', 'city' => 'Dortmund'],
                    ['name' => 'Hausverwaltung Musterstadt', 'city' => 'Leipzig'],
                ],
                'projects' => [
                    0 => ['Wallbox-Installation Tiefgarage', 'E-Check Wohnanlage'],
                    1 => ['Verteilererneuerung Backstube'],
                    2 => ['PV-Anschluss Mehrfamilienhaus', 'Störungsdienst Musterstadt'],
                ],
                'asset' => [
                    'name' => 'Unterverteilung UV-Tiefgarage',
                    'manufacturer' => 'Beispiel Elektrotechnik',
                    'model' => 'UV-63A',
                    'class' => AssetClass::Installation,
                    'location' => 'Tiefgarage Hamburg',
                ],
                'materials' => [
                    ['sku' => 'EL-WB-11', 'name' => 'Wallbox 11 kW', 'unit' => 'Stk', 'price' => '649.0000'],
                    ['sku' => 'EL-NYM-3X', 'name' => 'NYM-J 3x2,5 mm²', 'unit' => 'm', 'price' => '1.2000'],
                    ['sku' => 'EL-LS-B16', 'name' => 'Leitungsschutzschalter B16', 'unit' => 'Stk', 'price' => '6.9000'],
                ],
                'main_case' => [
                    'title' => 'Wallbox-Installation Tiefgarage — Beispielauftrag',
                    'content' => 'Installation einer 11-kW-Wallbox inkl. Leitungsverlegung und Messung. Plan: 480 min.',
                    'time_desc' => 'Demo-Zeiterfassung Wallbox-Installation',
                    'open_issue_title' => 'Schlussmessung Isolationswiderstand offen',
                    'open_issue_desc' => 'Messprotokoll nach VDE 0100-600 vor Inbetriebnahme vervollständigen.',
                    'protocol_title' => 'Abnahme Wallbox-Installation',
                    'protocol_items' => [
                        ['label' => 'Schutzleiterprüfung bestanden', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Isolationsmessung dokumentiert', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Funktionsprüfung FI durchgeführt', 'result' => ProtocolItemResult::Open],
                    ],
                    'comm_subject' => 'Terminabstimmung Inbetriebnahme Wallbox',
                    'comm_body' => 'Telefonat mit Hausverwaltung zur Freigabe und Schlüsselübergabe Tiefgarage.',
                ],
                'background_title' => 'Demo-Elektroeinsatz',
            ],
            DemoIndustry::Facility => [
                'customers' => [
                    ['name' => 'Büropark Muster KG', 'city' => 'Frankfurt'],
                    ['name' => 'Einkaufszentrum Beispiel', 'city' => 'Stuttgart'],
                    ['name' => 'Wohnanlage Musterquartier', 'city' => 'Hannover'],
                ],
                'projects' => [
                    0 => ['Objektbetreuung Büropark', 'Winterdienst Büropark'],
                    1 => ['Haustechnik Einkaufszentrum'],
                    2 => ['Grünpflege Musterquartier', 'Hausmeisterdienst Musterquartier'],
                ],
                'asset' => [
                    'name' => 'Lüftungsanlage RLT-01',
                    'manufacturer' => 'Beispiel Klimatechnik',
                    'model' => 'RLT-4000',
                    'class' => AssetClass::Machine,
                    'location' => 'Technikzentrale Frankfurt',
                ],
                'materials' => [
                    ['sku' => 'FM-FILTER-G4', 'name' => 'Luftfilter G4', 'unit' => 'Stk', 'price' => '12.5000'],
                    ['sku' => 'FM-STREU-25', 'name' => 'Auftausalz 25 kg', 'unit' => 'Sack', 'price' => '8.9000'],
                    ['sku' => 'FM-LEUCHT-LED', 'name' => 'LED-Leuchtmittel E27', 'unit' => 'Stk', 'price' => '3.4000'],
                ],
                'main_case' => [
                    'title' => 'Wartungsrunde Büropark — Beispielauftrag',
                    'content' => 'Monatliche Objektkontrolle inkl. Filterwechsel Lüftung und Kleinreparaturen. Plan: 480 min.',
                    'time_desc' => 'Demo-Zeiterfassung Objektbetreuung',
                    'open_issue_title' => 'Defekte Beleuchtung Tiefgarage Ebene 2',
                    'open_issue_desc' => 'Austausch der defekten LED-Leuchten bis zur nächsten Wartungsrunde.',
                    'protocol_title' => 'Abnahme Wartungsrunde Büropark',
                    'protocol_items' => [
                        ['label' => 'Lüftungsfilter gewechselt', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Notbeleuchtung geprüft', 'result' => ProtocolItemResult::Ok],
                        ['label' => 'Kleinreparaturen erledigt', 'result' => ProtocolItemResult::Open],
                    ],
                    'comm_subject' => 'Rückmeldung Mängel an Objektleitung',
                    'comm_body' => 'Telefonat mit Objektleitung zur Freigabe der erforderlichen Kleinreparaturen.',
                ],
                'background_title' => 'Demo-Objektrunde',
            ],
        };
    }
}
