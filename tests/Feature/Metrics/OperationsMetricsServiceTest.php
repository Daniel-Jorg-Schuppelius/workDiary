<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OperationsMetricsServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Metrics;

use App\Models\{FeatureUsageCounter, Organization};
use App\Services\Metrics\OperationsMetricsService;
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsMetricsServiceTest extends TestCase {
    use RefreshDatabase;

    private OperationsMetricsService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = app(OperationsMetricsService::class);
    }

    public function test_collect_returns_expected_structure(): void {
        $metrics = $this->service->collect();

        foreach (['version', 'queue', 'backups', 'plugin_errors', 'storage', 'active_users', 'module_counts', 'feature_usage', 'generated_at'] as $key) {
            $this->assertArrayHasKey($key, $metrics);
        }

        $this->assertSame((string) config('app.version'), $metrics['version']);
        $this->assertIsArray($metrics['module_counts']);
        foreach (['diary_entries', 'protocols', 'documents', 'form_submissions', 'knowledge_articles', 'communication_notes'] as $module) {
            $this->assertArrayHasKey($module, $metrics['module_counts']);
        }
        $this->assertIsArray($metrics['feature_usage']);
        $this->assertArrayHasKey('count', $metrics['plugin_errors']);
    }

    public function test_increment_is_idempotent_per_day_feature_and_org(): void {
        $org = Organization::factory()->create();

        $this->service->increment('documents.created', (int) $org->id);
        $this->service->increment('documents.created', (int) $org->id);
        $this->service->increment('documents.created', (int) $org->id);

        // Ein einziger Aggregat-Datensatz je Org + Feature + Tag, count = 3.
        $rows = FeatureUsageCounter::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('feature', 'documents.created')
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame(3, (int) $rows->first()->count);
        $this->assertSame(now()->toDateString(), $rows->first()->period_date->toDateString());
    }

    public function test_increment_separates_features_and_days(): void {
        $org = Organization::factory()->create();

        $this->service->increment('documents.created', (int) $org->id);
        $this->service->increment('forms.submitted', (int) $org->id);

        $this->assertSame(2, FeatureUsageCounter::withoutGlobalScopes()->where('organization_id', $org->id)->count());
    }

    public function test_counters_are_separated_per_organization(): void {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $this->service->increment('documents.created', (int) $orgA->id);
        $this->service->increment('documents.created', (int) $orgA->id);
        $this->service->increment('documents.created', (int) $orgB->id);

        $rowA = FeatureUsageCounter::withoutGlobalScopes()->where('organization_id', $orgA->id)->first();
        $rowB = FeatureUsageCounter::withoutGlobalScopes()->where('organization_id', $orgB->id)->first();

        $this->assertSame(2, (int) $rowA->count);
        $this->assertSame(1, (int) $rowB->count);

        // Org-Kontext A sieht in der Aggregation NUR die eigenen Zähler.
        app()->instance('currentOrganization', $orgA);
        $usage = $this->service->collect()['feature_usage'];
        app()->forgetInstance('currentOrganization');

        $this->assertCount(1, $usage);
        $this->assertSame('documents.created', $usage[0]['feature']);
        $this->assertSame(2, $usage[0]['total']);
    }

    public function test_increment_without_org_context_is_a_silent_noop(): void {
        $this->service->increment('documents.created');

        $this->assertSame(0, FeatureUsageCounter::withoutGlobalScopes()->count());
    }

    public function test_telemetry_default_is_enabled_because_counters_stay_local(): void {
        // Charakterisierung (MVP-337): Die Zähler verlassen die Installation
        // nie (nur feature_usage_counters, lokal) — deshalb ist der Default
        // bewusst AN (Opt-out), anders als updates.check_mode, dessen
        // Opt-in-Semantik EXTERNE Kommunikation gated.
        $this->assertTrue((bool) config('telemetry.enabled'));
        $this->assertTrue($this->service->telemetryEnabled());

        $org = Organization::factory()->create();
        $this->service->increment('documents.created', (int) $org->id);

        $this->assertSame(1, FeatureUsageCounter::withoutGlobalScopes()->where('organization_id', $org->id)->count());
    }

    public function test_increment_skips_when_disabled_system_wide(): void {
        $org = Organization::factory()->create();
        Setting::set('telemetry.enabled', false, SettingScope::System);

        $this->service->increment('documents.created', (int) $org->id);

        $this->assertSame(0, FeatureUsageCounter::withoutGlobalScopes()->count());

        // Wieder einschalten → es wird weitergezählt (Aggregate bleiben,
        // nur der Schreibpfad war gated).
        Setting::set('telemetry.enabled', true, SettingScope::System);
        $this->service->increment('documents.created', (int) $org->id);

        $this->assertSame(1, FeatureUsageCounter::withoutGlobalScopes()->count());
    }

    public function test_increment_respects_org_opt_out_on_every_path(): void {
        $optedOut = Organization::factory()->create();
        $counting = Organization::factory()->create();
        Setting::set('telemetry.enabled', false, SettingScope::Organization, $optedOut);

        // Pfad 1: gebundener Mandantenkontext (normaler HTTP-Fluss).
        app()->instance('currentOrganization', $optedOut->fresh());
        $this->service->increment('documents.created', (int) $optedOut->id);
        app()->forgetInstance('currentOrganization');

        // Pfad 2: OHNE gebundenen Kontext (Queue nach der Org-Hygiene aus
        // AppServiceProvider) — das Opt-out der Ziel-Org greift trotzdem.
        $this->service->increment('documents.created', (int) $optedOut->id);

        // Nachbar-Org ohne Override zählt weiter (Gate ist org-isoliert).
        $this->service->increment('documents.created', (int) $counting->id);

        $this->assertSame(0, FeatureUsageCounter::withoutGlobalScopes()->where('organization_id', $optedOut->id)->count());
        $this->assertSame(1, FeatureUsageCounter::withoutGlobalScopes()->where('organization_id', $counting->id)->count());
    }

    public function test_collect_exposes_telemetry_status_and_counter_catalogue(): void {
        $metrics = $this->service->collect();

        $this->assertTrue($metrics['telemetry']['enabled']);
        $this->assertSame(OperationsMetricsService::FEATURE_COUNTERS, $metrics['telemetry']['counters']);

        Setting::set('telemetry.enabled', false, SettingScope::System);

        $this->assertFalse($this->service->collect()['telemetry']['enabled']);
    }
}
