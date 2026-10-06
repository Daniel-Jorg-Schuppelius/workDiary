<?php
/*
 * Created on   : Thu Jul 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationSettingsFormRulesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Organization;

use App\Models\Platform\{Organization, User};
use App\Services\Mcp\OAuth\McpAuthorizationServer;
use App\Settings\SettingsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Charakterisierungs-Test des settings.*-Blocks im Organisationsformular
 * (067-P3b): friert das VOR dem Registry-Umbau bestehende
 * Validierungsverhalten je Regel-Familie ein (Gültig-/Ungültig-Paare +
 * Merge-Semantik). Muss vor UND nach der Umstellung auf
 * SettingsRegistry::formRulesForScope() unverändert grün sein.
 */
class OrganizationSettingsFormRulesTest extends TestCase {
    use RefreshDatabase;

    private User $admin;

    private Organization $organization;

    protected function setUp(): void {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->organization = Organization::query()->findOrFail($this->admin->organization_id);
    }

    /**
     * @param array<string, mixed> $settings
     * @return TestResponse<\Illuminate\Http\Response>
     */
    private function submit(array $settings): TestResponse {
        return $this->actingAs($this->admin)->put(route('admin.organizations.update', $this->organization), [
            'name' => $this->organization->name,
            'plan' => $this->organization->plan,
            'locale' => $this->organization->locale ?? 'de',
            'timezone' => $this->organization->timezone ?? 'Europe/Berlin',
            'is_active' => 1,
            'settings' => $settings,
        ]);
    }

    /** @param array<string, mixed> $settings */
    private function assertAccepted(array $settings): void {
        $this->submit($settings)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.organizations.edit', $this->organization));
    }

    /** @param array<string, mixed> $settings */
    private function assertRejected(array $settings, string $errorKey): void {
        $this->submit($settings)->assertSessionHasErrors($errorKey);
    }

    public function test_billing_mode_accepts_enum_and_rejects_garbage(): void {
        $valid = \App\Enums\Finance\BillingMode::values()[0];
        $this->assertAccepted(['billing_mode' => $valid]);
        $this->assertSame($valid, $this->organization->refresh()->settings['billing_mode'] ?? null);

        $this->assertRejected(['billing_mode' => 'quatsch'], 'settings.billing_mode');
    }

    public function test_personalization_formats_are_option_checked(): void {
        $valid = \App\Support\Formats::dateOptions()[0];
        $this->assertAccepted(['personalization' => ['date_format' => $valid]]);

        $this->assertRejected(['personalization' => ['date_format' => 'JJJJ-TT']], 'settings.personalization.date_format');
    }

    /** MVP-1010: Leeres Feld entfernt den gespeicherten Wert, nicht übermittelte bleiben. */
    public function test_empty_fields_clear_stored_values(): void {
        $this->assertAccepted(['shipping' => ['eori_number' => 'DE1234567'], 'finance' => ['fixed_assets' => ['gwg_limit' => '900', 'pool_years' => '4']]]);
        $this->assertSame('DE1234567', $this->organization->refresh()->settings['shipping']['eori_number'] ?? null);

        $this->assertAccepted(['shipping' => ['eori_number' => ''], 'finance' => ['fixed_assets' => ['gwg_limit' => '']]]);
        $settings = $this->organization->refresh()->settings;
        $this->assertArrayNotHasKey('shipping', $settings);
        $this->assertSame(['pool_years' => '4'], $settings['finance']['fixed_assets'] ?? null);
    }

    public function test_pagination_keeps_historic_wide_bounds(): void {
        // Historisch gültig: 3 (Wildcard min:1) — darf NICHT strenger werden.
        $this->assertAccepted(['pagination' => ['customers' => '3']]);
        $this->assertSame('3', (string) ($this->organization->refresh()->settings['pagination']['customers'] ?? ''));

        $this->assertRejected(['pagination' => ['customers' => '0']], 'settings.pagination.customers');
        $this->assertRejected(['pagination' => ['customers' => '2000']], 'settings.pagination.customers');
        // Unbekannter Unterkey bleibt über das Wildcard-Netz begrenzt.
        $this->assertRejected(['pagination' => ['zukunft' => 'abc']], 'settings.pagination.zukunft');
    }

    public function test_invoicing_and_einvoice_field_bounds(): void {
        $this->assertAccepted(['invoicing' => ['default_currency' => 'EUR', 'default_tax_rate' => '19.00']]);

        $this->assertAccepted(['invoicing' => ['billing_increment_minutes' => '15', 'billing_grouping_gap_minutes' => '30']]);
        $this->assertSame(15, (int) data_get($this->organization->refresh()->settings, 'invoicing.billing_increment_minutes'));
        $this->assertRejected(['invoicing' => ['billing_increment_minutes' => '0']], 'settings.invoicing.billing_increment_minutes');
        $this->assertRejected(['invoicing' => ['billing_grouping_gap_minutes' => '2000']], 'settings.invoicing.billing_grouping_gap_minutes');

        $this->assertRejected(['invoicing' => ['default_currency' => 'EU']], 'settings.invoicing.default_currency');
        $this->assertRejected(['einvoice' => ['country' => 'DEU']], 'settings.einvoice.country');
        $this->assertRejected(['einvoice' => ['contact_email' => 'keine-mail']], 'settings.einvoice.contact_email');
        $this->assertRejected(['einvoice' => ['payment_terms_days' => '400']], 'settings.einvoice.payment_terms_days');
        $this->assertAccepted(['einvoice' => ['country' => 'DE', 'small_business' => '1']]);
    }

    public function test_uploads_validation_and_ui_wildcards(): void {
        $this->assertAccepted(['uploads' => ['csv_import_kb' => '2048']]);
        $this->assertRejected(['uploads' => ['csv_import_kb' => '2000000']], 'settings.uploads.csv_import_kb');

        $this->assertAccepted(['uploads' => ['attachment_kb' => '1024']]);
        $this->assertRejected(['uploads' => ['attachment_kb' => '2000000']], 'settings.uploads.attachment_kb');

        $this->assertAccepted(['validation' => ['attendance' => ['max_comment' => '500']]]);
        $this->assertRejected(['validation' => ['attendance' => ['max_comment' => 'abc']]], 'settings.validation.attendance.max_comment');

        $this->assertAccepted(['ui' => ['dashboard' => ['recent_limit' => '5']]]);
        $this->assertRejected(['ui' => ['dashboard' => ['recent_limit' => 'x']]], 'settings.ui.dashboard.recent_limit');

        $this->assertRejected(['notifications' => ['push' => ['body_truncate' => '10']]], 'settings.notifications.push.body_truncate');
    }

    public function test_routing_urls_and_limits(): void {
        $this->assertAccepted(['routing' => ['nominatim' => ['base_url' => 'https://osm.example.test']]]);

        $this->assertRejected(['routing' => ['nominatim' => ['base_url' => 'kein url']]], 'settings.routing.nominatim.base_url');
        $this->assertRejected(['routing' => ['nominatim' => ['rate_limit_per_sec' => '100']]], 'settings.routing.nominatim.rate_limit_per_sec');
        $this->assertRejected(['routing' => ['osrm' => ['timeout' => '500']]], 'settings.routing.osrm.timeout');
        $this->assertRejected(['routing' => ['tiles' => ['max_zoom' => '30']]], 'settings.routing.tiles.max_zoom');
    }

    public function test_enum_backed_selects(): void {
        $schedule = \App\Enums\WorkSchedule\ScheduleType::values()[0];
        $this->assertAccepted(['timesheet' => ['default_schedule_type' => $schedule]]);
        $this->assertRejected(['timesheet' => ['default_schedule_type' => 'nope']], 'settings.timesheet.default_schedule_type');

        $provider = \App\Support\HolidayRegions::providers()[0];
        $this->assertAccepted(['holidays' => ['provider' => $provider]]);
        $this->assertRejected(['holidays' => ['provider' => 'Atlantis\\Nirgendwo']], 'settings.holidays.provider');

        $this->assertAccepted(['attendance' => ['self_correction' => 'self']]);
        $this->assertRejected(['attendance' => ['self_correction' => 'oops']], 'settings.attendance.self_correction');
    }

    public function test_travel_and_weather_and_maintenance(): void {
        $this->assertAccepted(['travel' => [
            'enabled' => '1', 'mode' => 'flat', 'flat_amount' => '12.50',
            'origin_lat' => '48.13', 'origin_lng' => '11.58', 'round_trip' => '0',
        ]]);
        $this->assertRejected(['travel' => ['mode' => 'xx']], 'settings.travel.mode');
        $this->assertRejected(['travel' => ['origin_lat' => '91']], 'settings.travel.origin_lat');
        $this->assertRejected(['travel' => ['flat_amount' => '-1']], 'settings.travel.flat_amount');

        $this->assertAccepted(['weather' => ['auto_fetch' => '1']]);
        $this->assertRejected(['weather' => ['auto_fetch' => '2']], 'settings.weather.auto_fetch');

        $this->assertAccepted(['maintenance' => ['enabled' => '1', 'message' => 'Kurz weg.', 'until' => '2026-08-01 10:00']]);
        $this->assertRejected(['maintenance' => ['until' => 'kein-datum']], 'settings.maintenance.until');
        $this->assertRejected(['maintenance' => ['message' => str_repeat('x', 301)]], 'settings.maintenance.message');
    }

    public function test_mcp_opt_in_is_off_by_default_and_switchable(): void {
        $registry = app(SettingsRegistry::class);
        $this->assertFalse((bool) $registry->effective('mcp.enabled', $this->organization)->value, 'KI-Assistenten sind ab Werk aus.');

        $this->assertAccepted(['mcp' => ['enabled' => '1']]);
        $this->assertTrue(app(McpAuthorizationServer::class)->enabledFor($this->organization->refresh()));
        $this->assertRejected(['mcp' => ['enabled' => 'vielleicht']], 'settings.mcp.enabled');
    }

    public function test_online_payment_settings(): void {
        $registry = app(SettingsRegistry::class);
        $this->assertTrue((bool) $registry->effective('payments.online.on_documents', $this->organization)->value, 'Zahlungslink auf Belegen ist ab Werk an.');

        $this->assertAccepted(['payments' => ['online' => ['provider' => 'mollie', 'on_documents' => '0']]]);
        $this->assertSame('mollie', $registry->effective('payments.online.provider', $this->organization->refresh())->value);
        $this->assertFalse((bool) $registry->effective('payments.online.on_documents', $this->organization)->value);
        $this->assertRejected(['payments' => ['online' => ['provider' => 'Stripe Inc.']]], 'settings.payments.online.provider');
    }

    /** Stufenart → Rolle (Entscheidung 2026-10-06) liegt in derselben Gruppe wie die Antragsstufen (MVP-531). */
    public function test_approval_step_roles_and_stages_share_the_group(): void {
        $registry = app(SettingsRegistry::class);
        $this->assertSame('buchhaltung', $registry->effective('approvals.step_role.commercial', $this->organization)->value);

        $this->actingAs($this->admin)->get(route('admin.organizations.edit', $this->organization))->assertOk()
            ->assertSee(__('settings.approvals.step_role', ['kind' => \App\Enums\Approval\ApprovalStepKind::Hr->label()]))
            ->assertSee(__('settings.approvals.default_role', ['role' => \App\Enums\User\UserRole::Personalverwaltung->label()]));

        $this->assertAccepted(['approvals' => ['overtime_stages' => '2', 'step_role' => ['commercial' => 'teamleitung', 'hr' => '']]]);
        $settings = (array) $this->organization->refresh()->settings;
        $this->assertSame('teamleitung', $registry->effective('approvals.step_role.commercial', $this->organization)->value);
        $this->assertSame('personalverwaltung', $registry->effective('approvals.step_role.hr', $this->organization)->value);
        $this->assertSame('2', (string) data_get($settings, 'approvals.overtime_stages'));

        $this->assertRejected(['approvals' => ['step_role' => ['technical' => 'chef']]], 'settings.approvals.step_role.technical');
        $this->assertRejected(['approvals' => ['time_correction_stages' => '3']], 'settings.approvals.time_correction_stages');
    }

    public function test_merge_semantics_of_empty_values(): void {
        // Override setzen …
        $this->assertAccepted(['pagination' => ['customers' => '30', 'tags' => '40']]);
        $settings = (array) $this->organization->refresh()->settings;
        $this->assertSame('30', (string) data_get($settings, 'pagination.customers'));
        $this->assertSame('40', (string) data_get($settings, 'pagination.tags'));

        // Leerer Wert entfernt den Override (MVP-1010), der Default greift
        // wieder; nicht übermittelte Felder bleiben, es landet kein ''-Müll.
        $this->assertAccepted(['pagination' => ['customers' => '']]);
        $settings = $this->organization->refresh()->settings;
        $this->assertArrayNotHasKey('customers', $settings['pagination'] ?? []);
        $this->assertSame('40', (string) ($settings['pagination']['tags'] ?? ''));

        // Ohne Bestand erzeugt ein leerer Wert auch keinen Eintrag; eine
        // komplett leere Gruppe wird nicht angelegt.
        $this->assertAccepted(['holidays' => ['provider' => '']]);
        $this->assertArrayNotHasKey('holidays', $this->organization->refresh()->settings ?? []);
    }
}
