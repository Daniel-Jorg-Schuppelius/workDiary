<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SettingsRegistryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Settings;

use App\Models\{AuditLog, SystemSetting};
use App\Settings\{SettingScope, SettingSource, SettingsRegistry};
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class SettingsRegistryTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private SettingsRegistry $registry;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->registry = app(SettingsRegistry::class);
    }

    public function test_resolution_precedence_org_over_system_over_config(): void {
        // Config-Default (aus config/pagination.php)
        config(['pagination.customers' => 25]);
        $this->assertSame(25, Setting::get('pagination.customers'));

        // System-Override schlägt Config
        Setting::set('pagination.customers', 60, SettingScope::System);
        $this->assertSame(60, Setting::get('pagination.customers'));

        // Org-Override schlägt System
        Setting::set('pagination.customers', 90, SettingScope::Organization, $this->organization);
        app()->instance('currentOrganization', $this->organization->fresh());
        $this->assertSame(90, Setting::get('pagination.customers'));

        // effective() erklärt die Herkunft
        $effective = $this->registry->effective('pagination.customers', $this->organization->fresh());
        $this->assertSame(90, $effective->value);
        $this->assertSame(SettingSource::Organization, $effective->source);

        $effectiveWithoutOrg = $this->registry->effective('pagination.customers');
        $this->assertSame(60, $effectiveWithoutOrg->value);
        $this->assertSame(SettingSource::System, $effectiveWithoutOrg->source);
    }

    public function test_reset_removes_override_and_falls_back(): void {
        config(['pagination.customers' => 25]);
        Setting::set('pagination.customers', 60, SettingScope::System);
        Setting::set('pagination.customers', 90, SettingScope::Organization, $this->organization);

        Setting::reset('pagination.customers', SettingScope::Organization, $this->organization->fresh());
        app()->instance('currentOrganization', $this->organization->fresh());
        $this->assertSame(60, Setting::get('pagination.customers'));

        Setting::reset('pagination.customers', SettingScope::System);
        $this->assertSame(25, Setting::get('pagination.customers'));
        $this->assertDatabaseCount('system_settings', 0);
    }

    public function test_unknown_key_is_rejected(): void {
        $this->expectException(InvalidArgumentException::class);
        Setting::set('nonsense.key', 1, SettingScope::System);
    }

    public function test_disallowed_scope_is_rejected(): void {
        // archive.schedule_at ist nur system-scoped
        $this->expectException(InvalidArgumentException::class);
        Setting::set('archive.schedule_at', '04:00', SettingScope::Organization, $this->organization);
    }

    public function test_validation_rules_are_enforced(): void {
        $this->expectException(ValidationException::class);
        Setting::set('pagination.customers', 100000, SettingScope::System);
    }

    public function test_time_type_validates_format(): void {
        Setting::set('archive.schedule_at', '04:30', SettingScope::System);
        $this->assertSame('04:30', Setting::get('archive.schedule_at'));

        $this->expectException(ValidationException::class);
        Setting::set('archive.schedule_at', 'morgens', SettingScope::System);
    }

    public function test_org_write_is_audited_via_organization(): void {
        Setting::set('pagination.customers', 90, SettingScope::Organization, $this->organization);

        $this->assertTrue(
            AuditLog::query()
                ->where('auditable_type', \App\Models\Organization::class)
                ->where('auditable_id', $this->organization->id)
                ->where('event', 'updated')
                ->exists(),
        );
    }

    public function test_system_write_is_audited(): void {
        Setting::set('pagination.customers', 60, SettingScope::System);

        $this->assertTrue(
            AuditLog::query()
                ->where('auditable_type', SystemSetting::class)
                ->where('event', 'created')
                ->exists(),
        );
    }

    public function test_missing_table_falls_back_to_config(): void {
        config(['pagination.customers' => 25]);
        Schema::drop('system_settings');
        SystemSetting::valueMap(); // darf nicht werfen

        $this->assertSame(25, Setting::get('pagination.customers'));
    }

    public function test_get_without_org_binding_uses_system_then_config(): void {
        app()->forgetInstance('currentOrganization');
        config(['pagination.customers' => 25]);

        $this->assertSame(25, Setting::get('pagination.customers'));

        Setting::set('pagination.customers', 60, SettingScope::System);
        $this->assertSame(60, Setting::get('pagination.customers'));
    }

    public function test_form_rules_derive_raw_input_rules_per_type(): void {
        // Boolean: HTML-Formulare liefern "0"/"1", nicht true/false.
        $bool = \App\Settings\SettingDefinition::fromArray('t.bool', ['type' => 'boolean', 'scopes' => ['organization']]);
        $this->assertSame(['nullable', 'in:0,1'], $bool->formRules());

        // Integer: fachliche Grenzen bleiben erhalten, 'nullable' wird nicht gedoppelt.
        $int = \App\Settings\SettingDefinition::fromArray('t.int', [
            'type' => 'integer', 'scopes' => ['organization'], 'rules' => 'nullable|min:1|max:50',
        ]);
        $this->assertSame(['nullable', 'integer', 'min:1', 'max:50'], $int->formRules());

        // Enum mit statischen Optionen → in:-Regel.
        $enum = \App\Settings\SettingDefinition::fromArray('t.enum', [
            'type' => 'enum', 'scopes' => ['organization'], 'options' => ['a', 'b'],
        ]);
        $this->assertContains('in:a,b', $enum->formRules());
    }

    public function test_options_from_resolves_static_reference(): void {
        $definition = \App\Settings\SettingDefinition::fromArray('t.provider', [
            'type' => 'enum',
            'scopes' => ['organization'],
            'options_from' => [\App\Support\HolidayRegions::class, 'providers'],
        ]);

        $options = $definition->resolvedOptions();
        $this->assertNotEmpty($options);
        $this->assertSame(\App\Support\HolidayRegions::providers(), $options);
        $this->assertContains('in:' . implode(',', array_map('strval', $options)), $definition->formRules());
    }

    public function test_form_rules_for_scope_cover_keys_groups_and_nesting(): void {
        $rules = $this->registry->formRulesForScope(SettingScope::Organization);

        // Jeder org-fähige Key bekommt eine Regel mit Präfix.
        $this->assertArrayHasKey('settings.pagination.customers', $rules);
        $this->assertSame(['nullable', 'integer', 'min:1', 'max:1000'], $rules['settings.pagination.customers']);

        // Gruppen- und Zwischen-Arrays sind als sometimes|array erlaubt.
        $this->assertSame(['sometimes', 'array'], $rules['settings.pagination']);
        foreach (array_keys($rules) as $key) {
            $this->assertStringStartsWith('settings.', $key);
        }

        // System-only-Keys tauchen NICHT im Org-Formular auf.
        $this->assertArrayNotHasKey('settings.archive.schedule_at', $rules);
    }
}
