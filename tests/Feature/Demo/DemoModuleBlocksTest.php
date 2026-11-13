<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DemoModuleBlocksTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Demo;

use App\Enums\Contract\{ContractObligationKind, ContractStatus};
use App\Enums\Demo\DemoIndustry;
use App\Enums\Expense\ExpenseStatus;
use App\Enums\Form\FormTemplateStatus;
use App\Enums\Gaeb\BoqItemStatus;
use App\Enums\Knowledge\ArticleStatus;
use App\Enums\Location\LocationPendingEntryStatus;
use App\Enums\Shift\{DutyPlanStatus, ShiftExchangeStatus};
use App\Models\Contract\{Contract, ContractObligation};
use App\Models\Diary\DiaryEntry;
use App\Models\Facility\{Room, Site};
use App\Models\Form\FormSubmission;
use App\Models\Gaeb\{BillOfQuantity, BoqItem};
use App\Models\Inventory\Warehouse;
use App\Models\Knowledge\{ContentReference, KnowledgeArticle};
use App\Models\Location\{CustomerGeofence, LocationPendingEntry};
use App\Models\Platform\{Organization, User};
use App\Models\Schedule\{CoverageRequirement, DutyPlan, ShiftExchange};
use App\Models\Time\TimeEntry;
use App\Models\Travel\{Expense, PerDiemTrip, TravelLog};
use App\Modules\ModuleRegistry;
use App\Services\Demo\DemoSeederService;
use App\Services\Inventory\StockLevelService;
use App\Support\MorphMap;
use Database\Seeders\PerDiemRateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Tests\TestCase;

/**
 * MVP-1532/1533 (nl-19): Demo-Blöcke für die zehn Module, die viele
 * Branchenprofile empfehlen — Planung, Formulare, Wissen, Lager, Spesen,
 * Team-Auswertungen, Standorterfassung, Verträge, Liegenschaften, Bau. Sie
 * folgen dem Funktionsumfang des Profils, legen im Vollumfang alles an und
 * hinterlassen beim Reset keine Reste.
 */
final class DemoModuleBlocksTest extends TestCase {
    use RefreshDatabase;

    /** Manifeste der zehn Module (Team-Auswertungen hat keine Tabellen, seine Zeiten zählen über time_entries). */
    private const array MODULES = ['schedule', 'form', 'knowledge', 'inventory', 'travel', 'location', 'contract', 'facility', 'gaeb'];

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
    }

    /** @return array{Organization, User} */
    private function organization(): array {
        $organization = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $organization->id]);

        return [$organization, $admin];
    }

    public function test_it_profile_seeds_the_recommended_blocks_and_skips_the_others(): void {
        [$organization, $admin] = $this->organization();

        $counts = app(DemoSeederService::class)->seed($organization, $admin, DemoIndustry::ItService);

        // IT empfiehlt Planung, Spesen, Formulare, Wissen, Team-Auswertungen, Verträge und Lager.
        $this->assertSame(2, $counts['duty_plans']);
        $this->assertSame(38, $counts['scheduled_shifts']);
        // Seit Feature 203 mit einem langen Bogen (Abschnitte) samt Entwurf.
        $this->assertSame(4, $counts['form_templates']);
        $this->assertSame(3, $counts['form_submissions']);
        $this->assertSame(3, $counts['knowledge_articles']);
        $this->assertSame(2, $counts['warehouses']);
        $this->assertSame(9, $counts['stock_movements']);
        $this->assertSame(4, $counts['expenses']);
        $this->assertSame(2, $counts['travel_logs']);
        $this->assertGreaterThan(40, (int) $counts['team_time_entries']);
        $this->assertSame(3, $counts['contracts']);
        // Standorterfassung, Liegenschaften und Bau empfiehlt das IT-Profil nicht.
        $this->assertSame(0, $counts['geofences']);
        $this->assertSame(0, $counts['sites']);
        $this->assertSame(0, $counts['bills_of_quantities']);
        $this->assertSame(0, CustomerGeofence::query()->where('organization_id', $organization->id)->count());
        $this->assertSame(0, Site::query()->where('organization_id', $organization->id)->count());
        $this->assertSame(0, BillOfQuantity::query()->where('organization_id', $organization->id)->count());
    }

    public function test_a_profile_without_these_modules_gets_no_data_for_them(): void {
        [$organization, $admin] = $this->organization();

        // Das Vermieter-Profil empfiehlt von den zehn Modulen nur die Verträge.
        $counts = app(DemoSeederService::class)->seed($organization, $admin, DemoIndustry::Vermieter);

        $this->assertSame(3, $counts['contracts']);
        foreach (['duty_plans', 'form_templates', 'knowledge_articles', 'warehouses', 'expenses', 'team_time_entries', 'geofences', 'sites', 'bills_of_quantities'] as $key) {
            $this->assertSame(0, $counts[$key], $key);
        }
        $this->assertSame(0, DutyPlan::query()->where('organization_id', $organization->id)->count());
        $this->assertSame(0, Warehouse::query()->where('organization_id', $organization->id)->count());
        $this->assertSame(0, Expense::query()->where('organization_id', $organization->id)->count());
        // Ohne Team-Auswertungen bleiben es die drei Zeiten des Hauptauftrags.
        $this->assertSame(3, TimeEntry::query()->where('organization_id', $organization->id)->count());
    }

    public function test_full_showcase_seeds_every_block_with_content_of_the_branch(): void {
        $this->seed(PerDiemRateSeeder::class);
        [$organization, $admin] = $this->organization();

        $counts = app(DemoSeederService::class)->seed($organization, $admin, DemoIndustry::Facility, true);
        $org = $organization->id;

        foreach (['duty_plans', 'form_templates', 'knowledge_articles', 'warehouses', 'expenses', 'travel_logs', 'per_diem_trips', 'team_time_entries', 'geofences', 'location_pending_entries', 'contracts', 'sites', 'rooms', 'bills_of_quantities'] as $key) {
            $this->assertGreaterThan(0, (int) $counts[$key], $key);
        }

        // Planung: veröffentlicht, zur Genehmigung beantragt, Lücke im Frühdienst, angenommener Tausch.
        $this->assertSame(DutyPlanStatus::Published, DutyPlan::query()->where('organization_id', $org)->orderBy('from_date')->firstOrFail()->status);
        $this->assertSame(1, DutyPlan::query()->where('organization_id', $org)->where('status', DutyPlanStatus::Submitted->value)->count());
        $this->assertSame(5, CoverageRequirement::query()->where('organization_id', $org)->where('min_staff', 2)->count());
        $this->assertSame(ShiftExchangeStatus::Accepted, ShiftExchange::query()->where('organization_id', $org)->firstOrFail()->status);

        // Formulare: drei aktive Vorlagen (eine als langer Bogen, Feature 203), Einreichungen am Hauptauftrag
        // und ein angefangener Entwurf daneben.
        $this->assertSame(3, \App\Models\Form\FormTemplate::query()->where('organization_id', $org)->where('status', FormTemplateStatus::Active->value)->count());
        $mainDiary = DiaryEntry::query()->where('organization_id', $org)->where('title', 'like', '%Beispielauftrag%')->firstOrFail();
        $this->assertSame(2, FormSubmission::query()->submitted()->where('subject_type', MorphMap::alias(DiaryEntry::class))->where('subject_id', $mainDiary->id)->count());
        $this->assertSame(1, FormSubmission::query()->drafts()->where('subject_type', MorphMap::alias(DiaryEntry::class))->where('subject_id', $mainDiary->id)->count());

        // Wissen: aus dem Hauptfall der Branche, am Auftrag verknüpft.
        $this->assertSame(2, KnowledgeArticle::query()->where('organization_id', $org)->where('status', ArticleStatus::Published->value)->count());
        $this->assertTrue(KnowledgeArticle::query()->where('organization_id', $org)->where('title', 'Leitfaden: Abnahme Wartungsrunde Büropark')->exists());
        $this->assertSame(1, ContentReference::query()->where('target_type', MorphMap::alias(DiaryEntry::class))->where('target_id', $mainDiary->id)->count());

        // Lager: Material der Branche als Artikel, ein Artikel unter dem Meldebestand.
        $this->assertTrue(\App\Models\Article\Article::query()->where('organization_id', $org)->where('name', 'Luftfilter G4')->exists());
        $main = Warehouse::query()->where('organization_id', $org)->where('code', 'HL')->firstOrFail();
        $this->assertCount(1, app(StockLevelService::class)->belowReorder($main));

        // Spesen: alle Stufen, Fahrten und Verpflegungspauschale.
        foreach ([ExpenseStatus::Draft, ExpenseStatus::Pending, ExpenseStatus::Approved, ExpenseStatus::Reimbursed] as $status) {
            $this->assertSame(1, Expense::query()->where('organization_id', $org)->where('status', $status->value)->count(), $status->value);
        }
        $this->assertSame(2, TravelLog::query()->where('organization_id', $org)->count());
        $this->assertGreaterThan(0, PerDiemTrip::query()->where('organization_id', $org)->firstOrFail()->days()->count());

        // Team-Auswertungen: mehrere Personen mit Zeiten über mehrere Tage.
        $team = TimeEntry::query()->where('organization_id', $org)->whereNull('diary_entry_id')->whereNull('travel_log_id');
        $this->assertGreaterThanOrEqual(4, (clone $team)->distinct()->count('user_id'));
        $this->assertGreaterThanOrEqual(10, (clone $team)->distinct()->count('date'));

        // Standorterfassung: erfundene Zonen, zwei offene Vorschläge einer Demo-Person.
        $this->assertSame('51.0000000', (string) CustomerGeofence::query()->where('organization_id', $org)->orderBy('id')->firstOrFail()->center_lat);
        $pending = LocationPendingEntry::query()->where('organization_id', $org)->where('status', LocationPendingEntryStatus::Open->value)->get();
        $this->assertCount(2, $pending);
        $this->assertSame(['demo+02@workdiary.test'], User::query()->whereKey($pending->pluck('user_id')->unique())->pluck('email')->all());

        // Verträge: zwei laufende, ein Entwurf, Kündigungsfrist im Kalender.
        $this->assertSame(2, Contract::query()->where('organization_id', $org)->where('status', ContractStatus::Active->value)->count());
        $this->assertSame(1, Contract::query()->where('organization_id', $org)->where('status', ContractStatus::Draft->value)->count());
        $this->assertTrue(ContractObligation::query()->where('organization_id', $org)->where('kind', ContractObligationKind::NoticeDeadline->value)->exists());

        // Liegenschaften: Standort des Hauptkunden im Ort der Musterbranche.
        $site = Site::query()->where('organization_id', $org)->firstOrFail();
        $this->assertSame('Büropark Muster KG – Hauptstandort', $site->name);
        $this->assertSame('Frankfurt', $site->address_city);
        $this->assertSame(6, Room::query()->where('organization_id', $org)->count());

        // Bau: LV über den GAEB-Import, beauftragt, erste Position im Aufmaß.
        $boq = BillOfQuantity::query()->where('organization_id', $org)->firstOrFail();
        $this->assertSame(BoqItemStatus::Ordered, $boq->status);
        $this->assertSame(5, BoqItem::query()->where('bill_of_quantity_id', $boq->id)->count());
        $first = BoqItem::query()->where('bill_of_quantity_id', $boq->id)->where('reference_no', '01.0010')->firstOrFail();
        $this->assertSame('Luftfilter G4', $first->short_text);
        $this->assertSame(BoqItemStatus::InProgress, $first->status);
    }

    public function test_reset_leaves_no_remnants_in_the_module_tables(): void {
        [$organization, $admin] = $this->organization();
        $service = app(DemoSeederService::class);

        $service->seed($organization, $admin, DemoIndustry::ItService, true);
        $before = $this->rowCounts($organization);
        $this->assertGreaterThan(0, $before['duty_plans']);
        $this->assertGreaterThan(0, $before['bill_of_quantities']);
        $this->assertGreaterThan(0, $before['location_points']);
        $this->assertSame(3, $before['document_render_snapshots']);

        $service->reset($organization->refresh(), $admin);

        $this->assertSame($before, $this->rowCounts($organization));
    }

    /** @return array<string, int> */
    private function rowCounts(Organization $organization): array {
        $registry = app(ModuleRegistry::class);
        $tables = ['customer_geofences', 'articles', 'article_variants', 'time_entries'];
        foreach (self::MODULES as $code) {
            $tables = [...$tables, ...($registry->byCode($code)?->tables() ?? [])];
        }

        $counts = [];
        foreach (array_unique($tables) as $table) {
            if (Schema::hasColumn($table, 'organization_id')) {
                $counts[$table] = DB::table($table)->where('organization_id', $organization->id)->count();
            }
        }
        // Nur der Designstand der Formular-Einreichungen gehört zu diesen Blöcken.
        $counts['document_render_snapshots'] = DB::table('document_render_snapshots')
            ->where('organization_id', $organization->id)
            ->where('documentable_type', MorphMap::alias(FormSubmission::class))
            ->count();
        ksort($counts);

        return $counts;
    }
}
