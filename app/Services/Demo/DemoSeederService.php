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
use App\Enums\Procedure\{ProcedureBackupScope, ProcedureBackupStorageTarget, ProcedureBackupVerifyMethod, ProcedureProofType, ProcedureStepRunStatus};
use App\Enums\Project\ProjectStatus;
use App\Enums\Protocol\{ProtocolItemResult, ProtocolItemType, ProtocolStatus, ProtocolType, ProtocolVisibility};
use App\Enums\Timesheet\{TimesheetKind, TimesheetStatus};
use App\Enums\User\UserRole;
use App\Models\{Asset, Attachment, AuditLog, CommunicationNote, Customer, DiaryEntry, Material, MaterialUsage, OpenIssue, Organization, ProcedureRun, ProcedureTemplate, Project, Protocol, ProtocolItem, TimeEntry, Timesheet, User};
use App\Services\Classification\BranchProfileInstaller;
use App\Services\Procedure\{BackupProofService, ProcedureExecutionService, ProcedureTemplateService, SecondPersonGate};
use Carbon\CarbonImmutable;
use Faker\{Factory as FakerFactory, Generator as Faker};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{DB, Hash, Storage};
use PDFToolkit\Entities\PDFContent;
use PDFToolkit\Registries\PDFWriterRegistry;
use RuntimeException;

/**
 * Seedet einen Demo-Mandanten mit deterministischen, branchenspezifischen
 * End-to-End-Inhalten (Feature 040 / MVP-050).
 *
 * Determinismus: fester Faker-Seed `42`, stabile Insert-Reihenfolge. `reset()`
 * löscht/re-seedet — nur für Orgs mit `is_demo = true` (harter Schutz).
 * Je Branche wird das passende Branchenprofil über den
 * {@see BranchProfileInstaller} installiert; Branchen-Inhalte liefert der
 * {@see DemoBlueprintProvider}, die Feature-Vorführszenarien der
 * {@see DemoShowcaseSeeder}.
 */
class DemoSeederService {
    public const DEMO_FAKER_SEED = 42;

    public function __construct(
        private readonly ?BranchProfileInstaller $branchProfiles = null,
        private readonly ?DemoBlueprintProvider $blueprints = null,
        private readonly ?DemoShowcaseSeeder $showcase = null,
    ) {}

    private function branchProfileInstaller(): BranchProfileInstaller {
        return $this->branchProfiles ?? app(BranchProfileInstaller::class);
    }

    private function blueprintProvider(): DemoBlueprintProvider {
        return $this->blueprints ?? app(DemoBlueprintProvider::class);
    }

    private function showcaseSeeder(): DemoShowcaseSeeder {
        return $this->showcase ?? app(DemoShowcaseSeeder::class);
    }

    /**
     * Zählt die Datensätze, die im aktuellen Seed-Lauf erzeugt wurden. Schlüssel:
     * organization_id, industry, branch_profile, customers, projects, users,
     * main_diary_entries, background_diary_entries, time_entries, open_issues,
     * materials, material_usages, assets, protocols, communication_notes,
     * attachments, procedure_runs.
     *
     * @return array<string, int|string>
     */
    public function seed(Organization $organization, ?User $actor = null, ?DemoIndustry $industry = null): array {
        return $this->withOrganizationContext($organization, fn(): array => $this->doSeed($organization, $actor, $industry));
    }

    /**
     * freshDemoOrg (demo-mandant.md §2): legt eine NEUE, isolierte Demo-Org an
     * (nie eine bestehende) und befüllt sie. Kern für CLI (`demo:fresh-org`)
     * und Plattform-Admin-UI (MVP-349). Audit (§8): `demo.orgCreated` +
     * `demo.seeded`. Optionales Mitglied bleibt Cross-Tenant-fähig.
     *
     * @return array{organization: Organization, counts: array<string, int|string>}
     */
    public function freshOrg(?DemoIndustry $industry = null, ?User $actor = null, ?User $member = null): array {
        $industry ??= DemoIndustry::default();

        // Eindeutiger Name — nie Kollision mit bestehenden (echten) Orgs.
        $base = 'Demo ' . $industry->label();
        $name = $base;
        $suffix = 2;
        while (Organization::query()->where('name', $name)->orWhere('name', $name . ' (Demo)')->exists()) {
            $name = $base . ' #' . $suffix++;
        }

        $organization = Organization::query()->create([
            'name' => $name,
            'plan' => 'enterprise',
            'locale' => 'de',
            'timezone' => config('app.timezone', 'Europe/Berlin'),
            'is_active' => true,
        ]);

        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $actor?->id,
            'event' => 'demo.orgCreated',
            'auditable_type' => Organization::class,
            'auditable_id' => $organization->id,
            'changes' => [
                'industry' => $industry->value,
                'organization_id' => $organization->id,
                'created_by' => $actor?->id,
                'member_user_id' => $member?->id,
            ],
        ]);

        $counts = $this->seed($organization, $actor, $industry);

        if ($member !== null) {
            $member->forceFill(['organization_id' => $organization->id])->save();
        }

        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $actor?->id,
            'event' => 'demo.seeded',
            'auditable_type' => Organization::class,
            'auditable_id' => $organization->id,
            'changes' => $counts,
        ]);

        return ['organization' => $organization->refresh(), 'counts' => $counts];
    }

    /**
     * @return array<string, int|string>
     */
    private function doSeed(Organization $organization, ?User $actor, ?DemoIndustry $industry): array {
        $industry ??= DemoIndustry::default();

        $faker = FakerFactory::create('de_DE');
        $faker->seed(self::DEMO_FAKER_SEED);

        $blueprint = $this->blueprintProvider()->blueprint($industry);

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
            'attachments' => 0,
            'procedure_runs' => 0,
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

            $users = $this->seedUsers($organization, $actor);
            $counts['users'] = $users->count();

            // Branchenprofil installieren (Klassifikationen, Tags, SLAs, Prozeduren …).
            // Ohne übergebenen Akteur dient der Demo-Admin als Versions-Autor —
            // sonst überspringt der Installer sämtliche Prozedurvorlagen.
            $profileActor = $actor ?? $users->first();
            $this->branchProfileInstaller()->install($organization, $industry->branchProfileCode(), $profileActor);

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

            // Vorführ-Ausbau (Feature 040 Nachtrag): Beispiel-Anhänge + Prozedurlauf.
            $counts['attachments'] = $this->seedAttachments($organization, $mainDiary, $users);
            $counts['procedure_runs'] = $this->seedProcedureRun($organization, $mainDiary, $users, $blueprint);

            // Agile Vorführ-Boards (Feature 064, P7): Scrum + Kanban.
            $showcase = $this->showcaseSeeder();
            $counts['agile_boards'] = $showcase->seedAgileBoards($projects, $users);

            // IT-Demoszenario Helpdesk (Feature 065, P10): Anfrage → Incident → Problem → Change.
            $counts['helpdesk_tickets'] = $showcase->seedHelpdesk($organization, $mainCustomer, $users);

            // Kleinunternehmer-Faktura §19 (Feature 066, MVP-169).
            $counts['invoices'] = $showcase->seedSmallBusinessInvoicing($organization, $mainCustomer, $users->first());

            // Bewerbungs-/Ausschreibungs-Demo (Feature 068, MVP-194/198).
            $counts['applications'] = $showcase->seedApplications($organization, $mainCustomer, $users->first());

            // Investitions-Demo (Feature 069, MVP-209).
            $counts['investments'] = $showcase->seedInvestments($organization, $users->first());

            // Krisen-Demo (Feature 070, MVP-222): geplante Übung, bewusst keine echte Krisenakte.
            $counts['crisis_exercises'] = $showcase->seedCrisisExercise($organization, $users->first());

            // Nachhaltigkeits-Demo (Feature 071, MVP-235).
            $counts['sustainability'] = $showcase->seedSustainability($organization, $users->first());

            // Phase-38-Basics (Vollaudit 2026-07, N23): Urlaubsübertrag, Kasse,
            // Abrechnungsplan, Rabatt/Skonto-Rechnung, Führerscheinkontrolle.
            $counts['phase38_basics'] = $showcase->seedPhase38Basics($organization, $mainCustomer, $users);

            // Reklamations-Demo (Feature 072, MVP-256).
            $counts['claims'] = $showcase->seedClaims($organization, $users->first());

            // Verleih-Demo (Feature 073, MVP-269).
            $counts['rental'] = $showcase->seedRental($organization, $users->first());
            $counts['disposal'] = $showcase->seedDisposal($organization, $users->first());

            // Leasing-Demo (Feature 074, MVP-280).
            $counts['asset_finance'] = $showcase->seedAssetFinance($organization, $users->first());

            // Prüfmittel-Demo (Feature 075, MVP-292).
            $counts['asset_compliance'] = $showcase->seedAssetCompliance($organization, $users->first());

            // Cloud-Dokumenteingang (Feature 080 P9; Audit 2026-08, W4.4).
            $counts['cloud_intake'] = $showcase->seedCloudIntake($organization, $users->first());
            // Lokale Buchhaltung (Feature 125, MVP-678): Durchstich vom Konto
            // bis zum offenen Posten.
            $counts['local_accounting'] = $showcase->seedLocalAccounting($organization, $users->first());
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

            // Demo-Anhänge inkl. Storage-Dateien (query()->delete() der
            // Aufträge feuert keine Model-Events — Leichen vermeiden).
            $attachments = Attachment::query()
                ->where('attachable_type', DiaryEntry::class)
                ->whereIn('attachable_id', $diaryIds)
                ->get();
            foreach ($attachments as $attachment) {
                Storage::disk($attachment->disk)->delete($attachment->path);
                $attachment->delete();
            }

            // Prozedurläufe (Step-Runs/Events/Backup-Proofs via FK-Cascade).
            ProcedureRun::query()->where('organization_id', $organization->id)->delete();

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
            $demoUserIds = User::query()
                ->where('organization_id', $organization->id)
                ->where('email', 'like', 'demo+%@workdiary.test')
                ->pluck('id');

            // Seit MVP-689 (RESTRICT-FKs auf die Nachweistabellen): Demo-
            // Nachweise sind KEINE echten Nachweise — vor dem User-Delete
            // leerräumen, sonst blockt der FK.
            foreach (\App\Services\Org\UserOffboardingService::RETENTION_FK_TABLES as $table => $column) {
                \Illuminate\Support\Facades\DB::table($table)->whereIn($column, $demoUserIds)->delete();
            }

            User::query()->whereKey($demoUserIds)->delete();
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
        // Bind+Restore zentral in OrganizationContext (Vollaudit 2026-07, M42).
        return \App\Support\OrganizationContext::run($organization, $callback);
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
                'unit_price' => $material->default_unit_price,
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
     * Beispiel-Anhänge am Hauptauftrag (lizenzfrei, synthetisch erzeugt):
     * ein Einsatzbericht-PDF (über das pdf-toolkit) und ein Vorher-Foto
     * (Minimal-PNG). Liefert die Anzahl der angelegten Anhänge.
     *
     * @param Collection<int, User> $users
     */
    private function seedAttachments(Organization $organization, DiaryEntry $entry, Collection $users): int {
        /** @var User $owner */
        $owner = $users->first();
        $dir = 'attachments/demo/' . $organization->id;
        $created = 0;

        $html = '<h1>Einsatzbericht (Demo)</h1>'
            . '<p>Auftrag: ' . e((string) $entry->title) . '</p>'
            . '<p>Dieser Beispielbericht wurde für Vorführzwecke erzeugt. '
            . 'Er zeigt, wie Berichte und Nachweise als Anhang am Auftrag liegen.</p>';
        $pdf = PDFWriterRegistry::getInstance()->createPdfString(PDFContent::fromHtml($html));
        if ($pdf !== null) {
            $path = $dir . '/einsatzbericht-demo.pdf';
            Storage::disk('local')->put($path, $pdf);
            $entry->attachments()->create([
                'organization_id' => $organization->id,
                'user_id' => $owner->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => 'einsatzbericht-demo.pdf',
                'mime' => 'application/pdf',
                'size' => strlen($pdf),
            ]);
            $created++;
        }

        // Minimal gültiges 1×1-PNG (selbst erzeugt, lizenzfrei).
        $png = (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            true,
        );
        $path = $dir . '/vorher-foto-demo.png';
        Storage::disk('local')->put($path, $png);
        $entry->attachments()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'vorher-foto-demo.png',
            'mime' => 'image/png',
            'size' => strlen($png),
        ]);
        $created++;

        return $created;
    }

    /**
     * Spielt einen vollständigen Prozedurlauf auf dem Hauptauftrag durch —
     * inkl. registriertem UND verifiziertem Backup-Proof sowie
     * Vier-Augen-Freigabe (SecondPersonGate) durch einen zweiten Demo-User.
     * Nutzt die Prozedurvorlagen des installierten Branchenprofils; ohne
     * veröffentlichte Vorlage wird still übersprungen (0).
     *
     * @param Collection<int, User> $users
     * @param array<string, mixed> $blueprint
     */
    private function seedProcedureRun(Organization $organization, DiaryEntry $entry, Collection $users, array $blueprint): int {
        $templates = app(ProcedureTemplateService::class);
        $executor = app(ProcedureExecutionService::class);
        $gate = app(SecondPersonGate::class);
        $backups = app(BackupProofService::class);

        // Bevorzugt die im Blueprint benannte Vorlage der Branche (MVP-710),
        // sonst die erste mit veröffentlichter Version.
        $preferredCode = (string) ($blueprint['procedure_code'] ?? '');
        $candidates = ProcedureTemplate::query()
            ->where('organization_id', $organization->id)
            ->orderByRaw('CASE WHEN code = ? THEN 0 ELSE 1 END', [$preferredCode])
            ->orderBy('id')
            ->get();
        $template = null;
        foreach ($candidates as $candidate) {
            if ($templates->currentVersionFor($candidate) !== null) {
                $template = $candidate;
                break;
            }
        }
        if (! $template instanceof ProcedureTemplate) {
            return 0;
        }

        /** @var User $executorUser Ausführender Techniker (zweiter Demo-User). */
        $executorUser = $users->skip(1)->first() ?? $users->first();
        /** @var User $approver Vier-Augen-Zweitperson/Verifizierer (Demo-Admin). */
        $approver = $users->first();

        $run = $executor->start($template, $entry, $approver, $executorUser);

        foreach ($run->stepRuns()->with('stepDef')->orderBy('id')->get() as $stepRun) {
            $def = $stepRun->stepDef;

            if ($def?->requires_proof_type === ProcedureProofType::Backup) {
                $proof = $backups->register($stepRun, $executorUser, [
                    'backup_scope' => ProcedureBackupScope::Config->value,
                    'source_label' => 'Demo-Konfigurationsbackup',
                    'taken_at' => now()->toDateTimeString(),
                    'size_bytes' => 1024 * 256,
                    'storage_target' => ProcedureBackupStorageTarget::External->value,
                    'external_ref' => '/srv/backup/demo-config.tar.gz',
                    'verify_method' => ProcedureBackupVerifyMethod::ManagerConfirmation->value,
                ]);
                $backups->verify($proof, $approver, null, 'Demo: Backup geprüft.');
            }

            $fresh = $stepRun->fresh();
            if ($fresh !== null && $gate->requiresSecondPerson($fresh)) {
                $gate->request($fresh, $executorUser);
                $gate->take($fresh->fresh() ?? $fresh, $approver);
                $gate->sign($fresh->fresh() ?? $fresh, $approver);
            }

            $fresh = $stepRun->fresh();
            if ($fresh !== null) {
                $executor->execute($fresh, $executorUser, ProcedureStepRunStatus::Done, [
                    'note' => 'Demo-Durchlauf',
                ]);
            }
        }

        $completed = $run->fresh();
        if ($completed instanceof ProcedureRun) {
            $executor->completeRun($completed, $executorUser);
        }

        return 1;
    }

}
