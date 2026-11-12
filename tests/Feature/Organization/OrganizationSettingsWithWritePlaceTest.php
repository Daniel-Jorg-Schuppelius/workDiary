<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationSettingsWithWritePlaceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Organization;

use App\Enums\ServiceTicket\{ServiceTicketPriority, TicketSeverity};
use App\Models\Audit\AuditLog;
use App\Models\Crisis\{CrisisCase, CrisisDeadlineTemplate};
use App\Models\Platform\{Organization, User};
use App\Services\Crisis\CrisisDeadlineService;
use App\Services\Retention\RetentionRegistry;
use App\Services\ServiceTicket\TicketIncidentService;
use App\Settings\SettingsRegistry;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Einstellungen mit Schreibstelle (MVP-1589): Rechtsraum, Prioritätsmatrix,
 * Kapazitätsschwelle, Führerscheinintervall, Investitionsschwelle, CSV-Export,
 * Bewerber-Löschfrist und Krisen-Meldefristen lassen sich je Organisation
 * setzen, und die Lesestellen nehmen den gesetzten Wert.
 */
class OrganizationSettingsWithWritePlaceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** @param  array<string, mixed>  $extra */
    private function saveOrganization(array $extra): \Illuminate\Testing\TestResponse {
        return $this->actingAs($this->admin)->put(route('admin.organizations.update', $this->organization), [
            'name' => $this->organization->name,
            'plan' => $this->organization->plan,
            'locale' => $this->organization->locale ?? 'de',
            'timezone' => $this->organization->timezone ?? 'Europe/Berlin',
            'is_active' => 1,
            ...$extra,
        ]);
    }

    public function test_legal_region_is_selectable_audited_and_drives_retention_periods(): void {
        $registry = app(RetentionRegistry::class);
        $this->assertContains('AT', $registry->regions());
        $this->assertSame('DE', $registry->regionFor($this->organization));

        $this->actingAs($this->admin)->get(route('admin.organizations.edit', $this->organization))->assertOk()
            ->assertSee('name="legal_region"', false);

        $this->saveOrganization(['legal_region' => 'AT'])->assertSessionHasNoErrors();

        $organization = $this->organization->refresh();
        $this->assertSame('AT', $organization->legal_region);
        $this->assertSame((int) config('retention.areas.' . array_key_first((array) config('retention.areas')) . '.years.AT'), $registry->yearsFor($organization, (string) array_key_first((array) config('retention.areas'))));
        $audit = AuditLog::query()->where('auditable_id', $organization->id)->where('event', 'updated')->orderByDesc('id')->firstOrFail();
        $this->assertStringContainsString('legal_region', (string) json_encode($audit->changes));

        $this->saveOrganization(['legal_region' => 'XX'])->assertSessionHasErrors('legal_region');
    }

    public function test_registered_settings_are_saved_and_read(): void {
        $this->actingAs($this->admin)->get(route('admin.organizations.edit', $this->organization))->assertOk()
            ->assertSee('settings[fleet][license_check_interval_months]', false)
            ->assertSee('settings[helpdesk][priority_matrix][1][1]', false)
            ->assertSee('settings[hr][capacity][threshold]', false)
            ->assertSee('settings[investments][approval_threshold]', false)
            ->assertSee('settings[reports][csv_delimiter_name]', false)
            ->assertSee('settings[applications][rejected_retention_months]', false);

        $this->saveOrganization(['settings' => [
            'fleet' => ['license_check_interval_months' => '12'],
            'helpdesk' => ['priority_matrix' => [1 => [1 => 'urgent', 2 => '']]],
            'hr' => ['capacity' => ['threshold' => '120']],
            'investments' => ['approval_threshold' => '2500'],
            'reports' => ['csv_delimiter_name' => 'comma', 'csv_meta_lines' => '0'],
            'applications' => ['rejected_retention_months' => '3'],
        ]])->assertSessionHasNoErrors();

        $organization = $this->organization->refresh();
        app()->instance('currentOrganization', $organization);
        $this->assertSame('12', (string) data_get($organization->settings, 'fleet.license_check_interval_months'));
        $this->assertSame(120, (int) Setting::get('hr.capacity.threshold', 100));
        $this->assertSame('2500', (string) data_get($organization->settings, 'investments.approval_threshold'));
        $this->assertSame('comma', Setting::get('reports.csv_delimiter_name'));
        $this->assertFalse((bool) Setting::get('reports.csv_meta_lines', true));
        $this->assertSame(3, (int) app(SettingsRegistry::class)->effective('applications.rejected_retention_months', $organization)->value);

        // Matrix: gesetzte Zelle gilt, leere Zelle bleibt beim Standard.
        $incidents = app(TicketIncidentService::class);
        $this->assertSame(ServiceTicketPriority::Urgent, $incidents->suggestPriority($organization, TicketSeverity::Low, TicketSeverity::Low));
        $this->assertSame($incidents->defaultPriority(TicketSeverity::Low, TicketSeverity::Medium), $incidents->suggestPriority($organization, TicketSeverity::Low, TicketSeverity::Medium));

        $this->saveOrganization(['settings' => ['fleet' => ['license_check_interval_months' => '0']]])->assertSessionHasErrors('settings.fleet.license_check_interval_months');
        $this->saveOrganization(['settings' => ['helpdesk' => ['priority_matrix' => [1 => [1 => 'sofort']]]]])->assertSessionHasErrors('settings.helpdesk.priority_matrix.1.1');
    }

    public function test_crisis_deadlines_can_be_maintained_per_organization(): void {
        CrisisDeadlineTemplate::query()->create(['organization_id' => null, 'category' => 'privacy', 'label' => 'DSGVO Art. 33', 'offset_hours' => 72, 'source' => 'Art. 33 DSGVO', 'active' => true]);
        $case = CrisisCase::query()->create(['organization_id' => $this->organization->id, 'title' => 'Datenabfluss', 'category' => 'privacy', 'severity' => 'major', 'status' => 'reported', 'created_by' => $this->admin->id]);
        $deadlines = fn (): array => array_column(app(CrisisDeadlineService::class)->deadlinesFor($case), 'label');

        $this->actingAs($this->admin)->get(route('crisis.deadlines.index'))->assertOk()->assertSee('DSGVO Art. 33');
        $this->get(route('crisis.deadlines.create'))->assertOk()->assertSee('name="offset_hours"', false);
        $this->assertSame(['DSGVO Art. 33'], $deadlines());

        // Vorgaben übernehmen, dann eine eigene Frist ergänzen.
        $this->post(route('crisis.deadlines.adopt'), ['category' => 'privacy'])->assertSessionHas('success');
        $this->post(route('crisis.deadlines.store'), ['category' => 'privacy', 'label' => 'Kundeninformation', 'offset_hours' => 24, 'active' => 1])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['DSGVO Art. 33', 'Kundeninformation'], $deadlines());
        $this->assertTrue(AuditLog::query()->where('event', 'crisis.deadline_template_created')->exists());

        $own = CrisisDeadlineTemplate::query()->where('organization_id', $this->organization->id)->where('label', 'Kundeninformation')->firstOrFail();
        $this->put(route('crisis.deadlines.update', $own), ['category' => 'privacy', 'label' => 'Kundeninformation', 'offset_hours' => 48, 'active' => 1])->assertSessionHasNoErrors();
        $this->assertSame(48, $own->refresh()->offset_hours);

        // Deaktivieren statt löschen; ohne aktive eigene Frist gelten wieder die Vorgaben.
        foreach (CrisisDeadlineTemplate::query()->where('organization_id', $this->organization->id)->get() as $template) {
            $this->post(route('crisis.deadlines.toggle', $template))->assertSessionHas('success');
        }
        $this->assertSame(['DSGVO Art. 33'], $deadlines());
        $this->assertSame(2, CrisisDeadlineTemplate::query()->where('organization_id', $this->organization->id)->where('active', false)->count());

        // Vorgaben und Fristen anderer Organisationen sind nicht änderbar.
        $global = CrisisDeadlineTemplate::query()->whereNull('organization_id')->firstOrFail();
        $foreign = CrisisDeadlineTemplate::query()->create(['organization_id' => Organization::factory()->create()->id, 'category' => 'privacy', 'label' => 'Fremd', 'offset_hours' => 1, 'active' => true]);
        $this->put(route('crisis.deadlines.update', $global), ['category' => 'privacy', 'label' => 'x'])->assertNotFound();
        $this->post(route('crisis.deadlines.toggle', $foreign))->assertNotFound();
    }
}
