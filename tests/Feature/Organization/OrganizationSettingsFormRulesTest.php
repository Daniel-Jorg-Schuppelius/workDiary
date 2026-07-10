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

use App\Models\{Organization, User};
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
            ->assertRedirect(route('admin.organizations.index'));
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

        $this->assertRejected(['invoicing' => ['default_currency' => 'EU']], 'settings.invoicing.default_currency');
        $this->assertRejected(['einvoice' => ['country' => 'DEU']], 'settings.einvoice.country');
        $this->assertRejected(['einvoice' => ['contact_email' => 'keine-mail']], 'settings.einvoice.contact_email');
        $this->assertRejected(['einvoice' => ['payment_terms_days' => '400']], 'settings.einvoice.payment_terms_days');
        $this->assertAccepted(['einvoice' => ['country' => 'DE', 'small_business' => '1']]);
    }

    public function test_uploads_validation_and_ui_wildcards(): void {
        $this->assertAccepted(['uploads' => ['csv_import_kb' => '2048']]);
        $this->assertRejected(['uploads' => ['csv_import_kb' => '2000000']], 'settings.uploads.csv_import_kb');

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

    public function test_merge_semantics_of_empty_values(): void {
        // Override setzen …
        $this->assertAccepted(['pagination' => ['customers' => '30', 'tags' => '40']]);
        $settings = (array) $this->organization->refresh()->settings;
        $this->assertSame('30', (string) data_get($settings, 'pagination.customers'));
        $this->assertSame('40', (string) data_get($settings, 'pagination.tags'));

        // Leerer Wert = „kein neuer Override": bestehende Werte bleiben
        // unverändert (stripEmpty läuft VOR dem Merge), es landet kein
        // ''-Müll im JSON.
        $this->assertAccepted(['pagination' => ['customers' => '']]);
        $settings = $this->organization->refresh()->settings;
        $this->assertSame('30', (string) ($settings['pagination']['customers'] ?? ''));
        $this->assertSame('40', (string) ($settings['pagination']['tags'] ?? ''));

        // Ohne Bestand erzeugt ein leerer Wert auch keinen Eintrag; eine
        // komplett leere Gruppe wird nicht angelegt.
        $this->assertAccepted(['holidays' => ['provider' => '']]);
        $this->assertArrayNotHasKey('holidays', $this->organization->refresh()->settings ?? []);
    }
}
