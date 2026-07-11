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
use App\Models\{Asset, Attachment, CommunicationNote, Customer, DiaryEntry, Invoice, Material, MaterialUsage, OpenIssue, Organization, ProcedureRun, ProcedureTemplate, Project, Protocol, ProtocolItem, TimeEntry, Timesheet, User};
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
     * materials, material_usages, assets, protocols, communication_notes,
     * attachments, procedure_runs.
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

            // Vorführ-Ausbau (Feature 040 Nachtrag): Beispiel-Anhänge +
            // vollständig durchgespielter Prozedurlauf inkl. Backup-Proof
            // und Vier-Augen-Freigabe.
            $counts['attachments'] = $this->seedAttachments($organization, $mainDiary, $users);
            $counts['procedure_runs'] = $this->seedProcedureRun($organization, $mainDiary, $users);

            // Agile Vorführ-Boards (Feature 064, P7): Scrum mit Sprint-
            // Historie über mehrere Wochen + Kanban mit WIP/Blockierung.
            $counts['agile_boards'] = $this->seedAgileBoards($projects, $users);

            // IT-Demoszenario Helpdesk (Feature 065, P10):
            // Anfrage → Incident → Problem → Change durchgängig.
            $counts['helpdesk_tickets'] = $this->seedHelpdesk($organization, $mainCustomer, $users);

            // Kleinunternehmer-Faktura §19 (Feature 066, MVP-169): Angebot →
            // Annahme → Rechnung (0 % USt., §-19-Hinweis) → Ausstellung.
            $counts['invoices'] = $this->seedSmallBusinessInvoicing($organization, $mainCustomer, $users->first());

            // Bewerbungs-/Ausschreibungs-Demo (Feature 068, MVP-194/198):
            // gewonnene Ausschreibung + Bewerbung bis zum Mitarbeiter-Entwurf.
            $counts['applications'] = $this->seedApplications($organization, $mainCustomer, $users->first());

            // Investitions-Demo (Feature 069, MVP-209): Akte mit Varianten
            // und offenem Budgetantrag (zeigt die Freigabekette).
            $counts['investments'] = $this->seedInvestments($organization, $users->first());

            // Krisen-Demo (Feature 070, MVP-222): geplante Übung — bewusst
            // KEINE echte Krisenakte im Demo-Datenbestand.
            $counts['crisis_exercises'] = $this->seedCrisisExercise($organization, $users->first());

            // Nachhaltigkeits-Demo (Feature 071, MVP-235): Kriterien +
            // Aktivitätsdaten + finalisierte Gerätebewertung.
            $counts['sustainability'] = $this->seedSustainability($organization, $users->first());

            // Reklamations-Demo (Feature 072, MVP-256): Fall mit Bewertung
            // und Entscheidung — zeigt den geführten Ablauf.
            $counts['claims'] = $this->seedClaims($organization, $users->first());

            // Verleih-Demo (Feature 073, MVP-269): leihfähiges Gerät mit
            // Preisliste, reservierter Akte und Konditionen-Snapshot.
            $counts['rental'] = $this->seedRental($organization, $users->first());

            // Leasing-Demo (Feature 074, MVP-280): aktivierter Vertrag mit
            // eingefrorenen Konditionen, Ratenplan und Kündigungsfrist.
            $counts['asset_finance'] = $this->seedAssetFinance($organization, $users->first());

            // Prüfmittel-Demo (Feature 075, MVP-292): Prüfpflicht aus dem
            // globalen Katalog mit dokumentierter Prüfung inkl. Zertifikat.
            $counts['asset_compliance'] = $this->seedAssetCompliance($organization, $users->first());
        });

        return $counts;
    }

    /**
     * §-19-Demo-Ablauf (Feature 066): dokumentiert die Belegkette einer
     * Kleinunternehmer-Org — Angebot mit Annahme, Überführung in eine
     * Entwurfsrechnung (TaxResolver → 0 %, §-19-Hinweistext) und
     * Ausstellung. Bewusst OHNE die Org global auf §19 zu stellen: der
     * Steuerkontext wird am Demo-Kunden über den Org-Setting-Schalter nur
     * für die Belegerzeugung aktiviert und danach zurückgesetzt.
     */
    private function seedSmallBusinessInvoicing(Organization $organization, Customer $customer, ?User $actor): int {
        if ($actor === null) {
            return 0;
        }

        // §-19-Kontext temporär aktivieren (data_get-Konvention, s. TaxResolver).
        $settings = (array) ($organization->settings ?? []);
        $before = $settings['einvoice']['small_business'] ?? null;
        $settings['einvoice']['small_business'] = '1';
        $organization->settings = $settings;
        $organization->save();

        try {
            $quotes = app(\App\Services\Invoicing\QuoteService::class);
            $quote = $quotes->create([
                'customer_id' => $customer->id,
                'valid_until' => \Carbon\Carbon::now()->addDays(30)->toDateString(),
                'terms' => (string) __('Demo-Angebot: Wartung inkl. Anfahrt, Abrechnung nach Aufwand.'),
            ], [
                ['description' => (string) __('Wartungspauschale (Demo)'), 'quantity' => '1', 'unit' => 'Pauschale', 'unit_price' => '480.00'],
                ['description' => (string) __('Erweiterte Dokumentation (Option)'), 'quantity' => '1', 'unit' => 'Pauschale', 'unit_price' => '120.00', 'optional' => true],
            ], $actor);
            $quote = $quotes->approve($quote, $actor);
            ['quote' => $quote] = $quotes->send($quote, $actor);
            $quote = $quotes->accept($quote); // Vollannahme (Optionen bleiben draußen)
            $invoice = $quotes->convertToInvoice($quote, $actor);

            // Ausstellen: friert Parteien ein; §-19-Hinweis steht in den Notes.
            $invoice->freezeParties();
            $invoice->update([
                'status' => Invoice::STATUS_ISSUED,
                'issued_on' => \Carbon\Carbon::now(),
                'due_on' => \Carbon\Carbon::now()->addDays((int) ($invoice->payment_terms_days ?? 14)),
            ]);

            return 1;
        } catch (\Throwable $e) {
            // Demo-Seeder bleibt robust: fehlende Vertriebs-Voraussetzungen
            // (z. B. deaktiviertes Modul) brechen den Gesamt-Seed nicht ab.
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: §19-Fakturakette übersprungen: ' . $e->getMessage());

            return 0;
        } finally {
            $settings = (array) ($organization->settings ?? []);
            if ($before === null) {
                unset($settings['einvoice']['small_business']);
            } else {
                $settings['einvoice']['small_business'] = $before;
            }
            $organization->settings = $settings;
            $organization->save();
        }
    }

    /**
     * Demo Feature 068: Ausschreibung (Go → Anforderung erledigt →
     * Einreichung → gewonnen) + Personalbewerbung (Gespräch → Bewertung →
     * Zusage → Mitarbeiter-Entwurf). Robust: Fehler brechen den Seed nicht.
     */
    private function seedApplications(Organization $organization, Customer $customer, ?User $actor): int {
        if ($actor === null) {
            return 0;
        }

        try {
            $tenders = app(\App\Services\Applications\TenderService::class);
            $opportunity = \App\Models\Applications\ApplicationOpportunity::query()->create([
                'organization_id' => $organization->id,
                'title' => (string) __('Rahmenvertrag Wartung Bürokomplex (Demo)'),
                'kind' => 'framework',
                'source' => 'Vergabeportal (Demo)',
                'customer_id' => $customer->id,
                'status' => 'in_progress',
                'submission_deadline' => \Carbon\Carbon::now()->addDays(14)->toDateString(),
                'estimated_value' => '48000.00',
                'probability' => 60,
                'responsible_user_id' => $actor->id,
                'created_by' => $actor->id,
            ]);
            $opportunity->requirements()->create([
                'organization_id' => $organization->id,
                'label' => (string) __('Referenzliste vergleichbarer Objekte'),
                'kind' => 'proof',
                'required' => true,
                'status' => 'done',
                'position' => 1,
            ]);
            $tenders->decideGo($opportunity, 'go', (string) __('Passt zur Auslastung im Winterhalbjahr.'), $actor);
            $tenders->submit($opportunity->refresh(), 'portal', null, $actor);
            $tenders->decide($opportunity->refresh(), 'won', null, $actor);

            $recruiting = app(\App\Services\Applications\RecruitingService::class);
            $requisition = \App\Models\Applications\JobRequisition::query()->create([
                'organization_id' => $organization->id,
                'title' => (string) __('Servicetechniker:in (Demo)'),
                'employment_type' => 'full_time',
                'status' => 'open',
                'responsible_user_id' => $actor->id,
                'created_by' => $actor->id,
            ]);
            ['application' => $application] = $recruiting->intake([
                'job_requisition_id' => $requisition->id,
                'candidate_name' => 'Kim Beispiel',
                'email' => 'kim.beispiel@example.test',
                'source' => 'website',
            ], $actor);
            $application->interviews()->create([
                'organization_id' => $organization->id,
                'scheduled_at' => \Carbon\Carbon::now()->subDays(3),
                'mode' => 'onsite',
                'interviewer_id' => $actor->id,
                'status' => 'done',
                'rating' => 5,
            ]);
            $recruiting->decide($application->refresh(), 'accepted', null, $actor);
            $recruiting->createEmployeeDraft($application->refresh(), $actor, [(string) __('Elektrofachkraft')]);

            return 2; // 1 Ausschreibung + 1 Bewerbungskette
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Bewerbungs-Demo übersprungen: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Demo Feature 069: Investitionsakte mit Variantenvergleich und
     * eingereichtem Budgetantrag (Freigabe bewusst offen — Vorführung
     * der Kette). Robust: Fehler brechen den Seed nicht.
     */
    private function seedInvestments(Organization $organization, ?User $actor): int {
        if ($actor === null) {
            return 0;
        }

        try {
            $case = \App\Models\Investments\InvestmentCase::query()->create([
                'organization_id' => $organization->id,
                'title' => (string) __('Ersatz Servicefahrzeug (Demo)'),
                'category' => 'machine',
                'reason' => (string) __('Bestandsfahrzeug hat 280.000 km und steigende Reparaturkosten.'),
                'objective' => (string) __('Ausfallsicherheit im Außendienst, geringere Werkstattkosten.'),
                'urgency' => 'high',
                'status' => 'comparison',
                'responsible_user_id' => $actor->id,
                'created_by' => $actor->id,
            ]);
            $case->options()->create([
                'organization_id' => $organization->id,
                'title' => (string) __('Neufahrzeug Kauf (Demo)'),
                'one_time_cost' => '42000.00',
                'recurring_cost_yearly' => '1800.00',
                'delivery_weeks' => 16,
                'quality_score' => 5,
                'recommended' => true,
            ]);
            $case->options()->create([
                'organization_id' => $organization->id,
                'title' => (string) __('Jahreswagen (Demo)'),
                'one_time_cost' => '31000.00',
                'recurring_cost_yearly' => '2400.00',
                'delivery_weeks' => 3,
                'quality_score' => 4,
            ]);
            app(\App\Services\Investments\InvestmentService::class)->submitBudget($case->refresh(), [
                'amount' => '42000.00',
                'cost_kind' => 'purchase',
                'financing' => 'loan',
            ], $actor);

            return 1;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Investitions-Demo übersprungen: ' . $e->getMessage());

            return 0;
        }
    }

    /** Demo Feature 070: geplante Krisenübung (Playbook-Verbesserung). */
    private function seedCrisisExercise(Organization $organization, ?User $actor): int {
        if ($actor === null) {
            return 0;
        }

        try {
            \App\Models\Crisis\CrisisExercise::query()->create([
                'organization_id' => $organization->id,
                'title' => (string) __('Stabsübung IT-Ausfall (Demo)'),
                'scenario' => (string) __('Zentraler Server fällt aus; Wiederanlauf nach Playbook, Kommunikation an Kunden binnen 4 Stunden.'),
                'next_due_on' => \Carbon\Carbon::now()->addDays(21)->toDateString(),
                'created_by' => $actor->id,
            ]);

            return 1;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Krisenübung übersprungen: ' . $e->getMessage());

            return 0;
        }
    }

    /** Demo Feature 071: E/S/G-Kriterien, Stromverbrauch + Gerätebewertung. */
    /**
     * Reklamations-Demo (Feature 072, MVP-256): ein bewerteter und
     * entschiedener Fall inkl. Nachweis — ohne Lager-/Faktura-Folgen,
     * damit der Demo-Bestand konsistent bleibt.
     */
    private function seedClaims(Organization $organization, ?User $actor): int {
        if ($actor === null) {
            return 0;
        }

        try {
            $customer = \App\Models\Customer::query()
                ->where('organization_id', $organization->id)
                ->orderBy('id')
                ->first();
            if ($customer === null) {
                return 0;
            }

            $service = app(\App\Services\Claims\ClaimCaseService::class);
            $case = $service->open($organization, $actor, [
                'title' => (string) __('Thermostatventil tropft nach Wartung (Demo)'),
                'source' => 'phone',
                'priority' => 'high',
                'severity' => 'minor',
                'customer_id' => $customer->id,
                'description' => (string) __('Kunde meldet Tropfbildung am neu eingebauten Ventil im Bad.'),
                'responsible_user_id' => $actor->id,
            ]);
            $case->evidence()->create([
                'organization_id' => $organization->id,
                'kind' => 'photo',
                'title' => (string) __('Foto der Tropfstelle (Demo)'),
                'recorded_by' => $actor->id,
                'recorded_at' => now(),
            ]);
            $service->assess($case, $actor, \App\Enums\Claims\ClaimKind::WarrantyLegal, \App\Enums\Claims\ClaimVerdict::Justified, (string) __('Einbau vor 3 Monaten — gesetzliche Gewährleistung greift, Nacherfüllung angeboten.'));
            $service->decide($case->refresh(), $actor, 'accepted', (string) __('Nacherfüllung durch erneuten Serviceeinsatz (§ 439 BGB).'));
            $case->refresh()->actions()->create([
                'organization_id' => $organization->id,
                'kind' => 'service_visit',
                'status' => 'planned',
                'title' => (string) __('Nachbesserung vor Ort einplanen (Demo)'),
                'assigned_user_id' => $actor->id,
                'created_by' => $actor->id,
            ]);

            return 1;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Reklamations-Demo übersprungen: ' . $e->getMessage());

            return 0;
        }
    }

    private function seedRental(Organization $organization, ?User $actor): int {
        if ($actor === null) {
            return 0;
        }

        try {
            $customer = \App\Models\Customer::query()
                ->where('organization_id', $organization->id)
                ->orderBy('id')
                ->first();
            $asset = \App\Models\Asset::query()
                ->where('organization_id', $organization->id)
                ->orderBy('id')
                ->first();
            if ($customer === null || $asset === null) {
                return 0;
            }

            // Versionierte Preisliste (D10) mit Tagessatz + Reinigung.
            $card = \App\Models\Rental\RentalRateCard::query()->create([
                'organization_id' => $organization->id,
                'name' => (string) __('Standard-Verleih (Demo)'),
                'version' => 1,
                'status' => \App\Enums\Rental\RentalRateCardStatus::Active->value,
                'valid_from' => now()->toDateString(),
                'created_by' => $actor->id,
            ]);
            $card->items()->createMany([
                ['organization_id' => $organization->id, 'kind' => 'daily_rate', 'label' => (string) __('Tagessatz (Demo)'), 'amount' => '45.00', 'unit' => 'day'],
                ['organization_id' => $organization->id, 'kind' => 'cleaning', 'label' => (string) __('Endreinigung (Demo)'), 'amount' => '25.00', 'unit' => 'flat'],
            ]);

            \App\Models\Rental\RentalProfile::query()->create([
                'organization_id' => $organization->id,
                'asset_id' => $asset->id,
                'is_rentable' => true,
                'group_code' => 'demo',
                'buffer_after_hours' => 2,
                'default_rate_card_id' => $card->id,
            ]);

            $service = app(\App\Services\Rental\RentalCaseService::class);
            $case = $service->open($organization, $actor, [
                'customer_id' => $customer->id,
                'starts_at' => now()->addDay()->setTime(8, 0),
                'ends_at' => now()->addDays(3)->setTime(17, 0),
                'responsible_user_id' => $actor->id,
                'rental_rate_card_id' => $card->id,
                'deposit_amount' => '150.00',
                'notes' => (string) __('Demo-Verleihvorgang mit Konditionen-Snapshot.'),
            ], [$asset->id]);
            $service->reserve($case, $actor);

            return 1;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Verleih-Demo übersprungen: ' . $e->getMessage());

            return 0;
        }
    }

    private function seedAssetFinance(Organization $organization, ?User $actor): int {
        if ($actor === null) {
            return 0;
        }

        try {
            $asset = \App\Models\Asset::query()
                ->where('organization_id', $organization->id)
                ->orderBy('id')
                ->first();
            if ($asset === null) {
                return 0;
            }

            $service = app(\App\Services\AssetFinance\AssetFinanceService::class);
            $contract = $service->create($organization, $actor, [
                'kind' => \App\Enums\AssetFinance\AssetFinanceKind::OperatingLease->value,
                'partner_name' => (string) __('Muster-Leasing GmbH (Demo)'),
                'contract_no' => 'ML-2026-0042',
                'starts_on' => now()->startOfMonth()->toDateString(),
                'ends_on' => now()->startOfMonth()->addMonths(11)->toDateString(),
                'payment_rhythm' => 'monthly',
                'rate_amount' => '390.00',
                'residual_value' => '4500.00',
                'responsible_user_id' => $actor->id,
                'notes' => (string) __('Demo-Leasingakte mit Ratenplan und Kündigungsfrist.'),
            ], [$asset->id]);
            $service->activate($contract, $actor);

            $contract->deadlines()->create([
                'organization_id' => $organization->id,
                'kind' => \App\Enums\AssetFinance\AssetFinanceDeadlineKind::Termination->value,
                'due_on' => now()->addMonths(8)->toDateString(),
                'warn_days_before' => 60,
                'responsible_user_id' => $actor->id,
                'note' => (string) __('Kündigung spätestens 3 Monate vor Vertragsende (Demo).'),
            ]);
            $contract->usageLimits()->create([
                'organization_id' => $organization->id,
                'kind' => \App\Enums\AssetFinance\AssetFinanceUsageLimitKind::OperatingHours->value,
                'limit_value' => '1200.00',
                'period' => 'yearly',
                'overrun_fee_per_unit' => '2.5000',
            ]);

            return 1;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Leasing-Demo übersprungen: ' . $e->getMessage());

            return 0;
        }
    }

    private function seedAssetCompliance(Organization $organization, ?User $actor): int {
        if ($actor === null) {
            return 0;
        }

        try {
            $asset = \App\Models\Asset::query()
                ->where('organization_id', $organization->id)
                ->orderBy('id')
                ->first();
            $profile = \App\Models\AssetCompliance\AssetComplianceProfile::query()
                ->whereNull('organization_id')
                ->where('code', 'dguv_v3_portable')
                ->first();
            if ($asset === null || $profile === null) {
                return 0;
            }

            $service = app(\App\Services\AssetCompliance\AssetComplianceService::class);
            $assignment = $service->assign($profile, $asset, $actor, [
                'last_done_on' => now()->subMonths(11)->toDateString(),
                'responsible_user_id' => $actor->id,
            ]);

            $service->recordInspection($assignment, $actor, [
                'result' => 'passed',
                'note' => (string) __('Demo-Prüfung ohne Befund.'),
                'signature_name' => $actor->name,
                'certificate' => [
                    'certificate_no' => 'KAL-2026-0001',
                    'issuer' => (string) __('Demo-Prüfstelle GmbH'),
                    'issued_on' => now()->toDateString(),
                    'valid_until' => now()->addYear()->toDateString(),
                ],
            ]);

            return 1;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Prüfmittel-Demo übersprungen: ' . $e->getMessage());

            return 0;
        }
    }

    private function seedSustainability(Organization $organization, ?User $actor): int {
        if ($actor === null) {
            return 0;
        }

        try {
            foreach ([['environment', 'Energieeffizienz', 3], ['environment', 'Reparierbarkeit', 2], ['social', 'Arbeitsschutz beim Einsatz', 2], ['governance', 'Lieferantennachweise', 1]] as [$dimension, $label, $weight]) {
                \App\Models\Sustainability\SustainabilityCriterion::query()->firstOrCreate([
                    'organization_id' => $organization->id,
                    'dimension' => $dimension,
                    'label' => $label,
                ], ['weight' => $weight, 'active' => true]);
            }

            \App\Models\Sustainability\SustainabilityActivityRecord::query()->create([
                'organization_id' => $organization->id,
                'activity_code' => 'electricity_kwh',
                'amount' => '1250.000',
                'unit' => 'kWh',
                'period_start' => \Carbon\Carbon::now()->startOfQuarter()->toDateString(),
                'period_end' => \Carbon\Carbon::now()->toDateString(),
                'data_quality' => 'measured',
                'source_note' => (string) __('Zählerstand Hauptgebäude (Demo)'),
                'created_by' => $actor->id,
            ]);

            $assessments = app(\App\Services\Sustainability\SustainabilityAssessmentService::class);
            $assessment = $assessments->createDraft($organization->id, null, null, (string) __('Akkuschrauber-Flotte (Demo)'), $actor);
            foreach ($assessment->items as $index => $item) {
                $item->update(['score' => [4, 3, 5, 2][$index % 4], 'data_quality' => 'calculated', 'source_note' => (string) __('Herstellerangaben + Wartungshistorie (Demo)')]);
            }
            $assessments->finalize($assessment->refresh(), $actor);

            return 1;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Nachhaltigkeits-Demo übersprungen: ' . $e->getMessage());

            return 0;
        }
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

    /**
     * IT-Demoszenario Helpdesk (Feature 065, P10): Portal-Queue + Incident
     * mit Konversation und Wartezustand, gelöstes Ticket mit Bewertung,
     * Problem aus Incidents, freigegebene Standard-Change-Vorlage + Change.
     *
     * @param \Illuminate\Support\Collection<int, User> $users
     */
    private function seedHelpdesk(Organization $organization, Customer $customer, Collection $users): int {
        if (\App\Models\ServiceQueue::query()->where('organization_id', $organization->id)->exists()) {
            return 0;
        }
        /** @var User $agent */
        $agent = $users->first();

        $queue = \App\Models\ServiceQueue::query()->create([
            'organization_id' => $organization->id,
            'name' => 'IT-Support',
            'purpose' => 'Zentrale Anlaufstelle für Störungen und Anfragen.',
            'is_default' => true,
            'visibility' => 'portal',
        ]);

        $tickets = app(\App\Services\ServiceTicket\ServiceTicketService::class);
        $conversation = app(\App\Services\ServiceTicket\TicketConversationService::class);

        // Incident mit Konversation + Wartezustand.
        $incident = $tickets->create($organization, $agent, [
            'title' => 'VPN bricht mehrmals täglich ab',
            'description' => 'Mehrere Nutzer melden Abbrüche seit dem letzten Update.',
            'kind' => 'incident',
            'queue_id' => $queue->id,
            'customer_id' => $customer->id,
        ]);
        $tickets->assign($incident, $agent, $agent->id);
        $conversation->reply($incident->fresh() ?? $incident, $agent, 'Wir haben das Problem reproduziert und analysieren die Ursache.');
        $conversation->note($incident->fresh() ?? $incident, $agent, 'Verdacht: MTU-Problem nach Firmware 2.4.1.');

        // Gelöstes zweites Ticket.
        $solved = $tickets->create($organization, $agent, [
            'title' => 'Neuer Arbeitsplatz für Auszubildende',
            'kind' => 'service_request',
            'queue_id' => $queue->id,
            'customer_id' => $customer->id,
        ]);
        $tickets->assign($solved, $agent, $agent->id);
        $solved = $tickets->transition($solved->fresh() ?? $solved, $agent, \App\Enums\ServiceTicket\ServiceTicketStatus::InProgress);
        $solved = $tickets->transition($solved, $agent, \App\Enums\ServiceTicket\ServiceTicketStatus::Done);

        // Problem aus dem Incident + freigegebene Standard-Change-Vorlage + Change.
        $problem = app(\App\Services\ServiceTicket\ProblemService::class)
            ->openFromIncidents([$incident->fresh() ?? $incident], 'Wiederkehrende VPN-Abbrüche nach Firmware-Update', $agent);
        app(\App\Services\ServiceTicket\ProblemService::class)->transition($problem, 'analyzing', $agent);

        $template = \App\Models\ChangeTemplate::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Firmware-Rollout Netzwerkgeräte',
            'implementation_plan' => 'Staging → Pilotgruppe → Flächenrollout.',
            'test_plan' => 'VPN-Dauerlast über 24h.',
            'rollback_plan' => 'Firmware-Downgrade auf 2.3.9.',
            'approved' => true,
        ]);
        app(\App\Services\ServiceTicket\ChangeService::class)->submit([
            'title' => 'Firmware-Downgrade VPN-Gateways',
            'change_type' => 'standard',
            'reason' => 'Behebt die VPN-Abbrüche (Problem-Analyse).',
            'problem_id' => $problem->id,
        ], $agent, [], $template);

        return \App\Models\ServiceTicket::query()->where('organization_id', $organization->id)->count();
    }

    /**
     * Agile Vorführ-Boards (Feature 064, P7): Projekt 1 als Scrum-Board mit
     * abgeschlossenem und aktivem Sprint samt mehrwöchiger Event-Historie
     * (Burndown/Velocity/CFD-Demos), Projekt 2 als Kanban-Board mit
     * WIP-Limit und Blockierung. Rückdatierung via Carbon::setTestNow —
     * im finally IMMER zurückgesetzt.
     */
    /**
     * @param Collection<int, Project> $projects
     * @param Collection<int, User> $users
     */
    private function seedAgileBoards(Collection $projects, Collection $users): int {
        $scrumProject = $projects->get(0);
        if ($scrumProject === null || \App\Models\Agile\AgileBoard::query()->where('project_id', $scrumProject->id)->exists()) {
            return 0;
        }
        $kanbanProject = $projects->get(1);

        $boards = app(\App\Services\Agile\AgileBoardService::class);
        $items = app(\App\Services\Agile\AgileWorkItemService::class);
        $sprints = app(\App\Services\Agile\AgileSprintService::class);
        /** @var User $actor */
        $actor = $users->first();
        $base = \Illuminate\Support\Carbon::now()->subWeeks(4)->startOfWeek()->setTime(9, 0);
        $at = fn(int $days, int $hour = 9) => \Illuminate\Support\Carbon::setTestNow($base->copy()->addDays($days)->setTime($hour, 0));

        try {
            // ── Scrum-Board mit zwei Sprints ─────────────────────────────
            $at(0);
            $board = $boards->activate($scrumProject, \App\Models\Agile\AgileBoard::METHOD_SCRUM, $actor);
            $inProgress = $board->columns()->where('name', 'In Arbeit')->firstOrFail();
            $done = $board->columns()->where('category', 'done')->firstOrFail();

            $stories = collect([
                ['Anmeldung mit Zwei-Faktor absichern', 5],
                ['Dashboard-Kacheln konfigurierbar machen', 3],
                ['Export nach XLSX bereitstellen', 8],
                ['Benachrichtigungen zusammenfassen', 2],
                ['Suche über alle Bereiche', 5],
                ['Mobile Ansicht für die Zeiterfassung', 3],
            ])->map(fn(array $row) => $items->create($board, [
                'title' => $row[0],
                'story_points' => $row[1],
            ], $actor))->values()->all();

            $sprintOne = $sprints->plan($board, [
                'name' => 'Sprint 1', 'goal' => 'Grundfunktionen lieferfähig machen',
                'starts_on' => $base->toDateString(), 'ends_on' => $base->copy()->addDays(11)->toDateString(),
            ], $actor);
            foreach (array_slice($stories, 0, 4) as $story) {
                $sprints->assign($sprintOne, $story, $actor);
            }
            $at(0, 10);
            $sprintOne = $sprints->start($sprintOne, $actor);

            $move = function (int $index, $column, int $day, int $hour = 9) use ($boards, $stories, $actor, $at): void {
                $at($day, $hour);
                $item = $stories[$index]->fresh() ?? $stories[$index];
                $boards->move($item, $column, (int) $item->lock_version, null, $actor);
            };
            $move(0, $inProgress, 2);
            $move(0, $done, 4);
            $move(1, $inProgress, 5);
            $move(1, $done, 7);
            $move(2, $inProgress, 8);

            $at(10);
            $sprintTwo = $sprints->plan($board, [
                'name' => 'Sprint 2', 'goal' => 'Auswertung und Suche ausbauen',
                'starts_on' => $base->copy()->addDays(14)->toDateString(),
                'ends_on' => $base->copy()->addDays(31)->toDateString(),
            ], $actor);

            $at(11, 16);
            $sprints->complete($sprintOne->fresh() ?? $sprintOne, [
                (int) $stories[2]->id => (string) $sprintTwo->id, // Carry-over in Sprint 2
                (int) $stories[3]->id => 'backlog',
            ], $actor);

            $sprintTwo = $sprintTwo->fresh() ?? $sprintTwo;
            $sprints->assign($sprintTwo, $stories[4], $actor);
            $at(14);
            $sprints->start($sprintTwo, $actor);
            $move(2, $inProgress, 15);
            $move(4, $inProgress, 16);
            $at(17);
            $boards->block($stories[2]->fresh() ?? $stories[2], 'Warten auf Kundenfreigabe', $actor);
            $at(18, 14);
            $boards->unblock($stories[2]->fresh() ?? $stories[2], $actor);
            $move(2, $done, 19);
            $at(20);
            $boards->block($stories[4]->fresh() ?? $stories[4], 'Testumgebung nicht erreichbar', $actor);

            // ── Kanban-Board mit WIP-Limit ───────────────────────────────
            if ($kanbanProject === null) {
                return 1;
            }
            $at(3);
            $kanban = $boards->activate($kanbanProject, \App\Models\Agile\AgileBoard::METHOD_KANBAN, $actor);
            $kanbanProgress = $kanban->columns()->where('name', 'In Arbeit')->firstOrFail();
            $boards->saveColumn($kanban, [
                'name' => (string) $kanbanProgress->name,
                'category' => 'in_progress',
                'wip_limit' => 2,
                'position' => (int) $kanbanProgress->position,
            ], $kanbanProgress, $actor);
            $kanbanDone = $kanban->columns()->where('category', 'done')->firstOrFail();

            $tasks = collect([
                'Serverwartung Standort Nord', 'Zertifikate erneuern',
                'Backup-Konzept prüfen', 'Monitoring-Alarme entrümpeln',
            ])->map(fn(string $title) => $items->create($kanban, ['title' => $title, 'item_type' => 'task'], $actor))->values()->all();

            $kanbanMove = function (int $index, $column, int $day) use ($boards, $tasks, $actor, $at): void {
                $at($day, 11);
                $item = $tasks[$index]->fresh() ?? $tasks[$index];
                $boards->move($item, $column, (int) $item->lock_version, null, $actor);
            };
            $kanbanMove(0, $kanbanProgress, 4);
            $kanbanMove(0, $kanbanDone, 6);
            $kanbanMove(1, $kanbanProgress, 7);
            $kanbanMove(2, $kanbanProgress, 12);

            return 2;
        } finally {
            \Illuminate\Support\Carbon::setTestNow();
        }
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
     */
    private function seedProcedureRun(Organization $organization, DiaryEntry $entry, Collection $users): int {
        $templates = app(ProcedureTemplateService::class);
        $executor = app(ProcedureExecutionService::class);
        $gate = app(SecondPersonGate::class);
        $backups = app(BackupProofService::class);

        // Bevorzugt eine Vorlage mit Backup- und Freigabe-Schritten (IT),
        // sonst die erste mit veröffentlichter Version.
        $candidates = ProcedureTemplate::query()
            ->where('organization_id', $organization->id)
            ->orderByRaw("CASE WHEN code = 'IT_NETWORK_CHANGE' THEN 0 ELSE 1 END")
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
