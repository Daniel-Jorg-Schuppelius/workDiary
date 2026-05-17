<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationSettingsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_helper_falls_back_to_config_when_no_org_bound(): void
    {
        $this->assertSame(
            (int) config('pagination.timesheets'),
            (int) setting('pagination.timesheets', 0),
        );
    }

    public function test_setting_helper_prefers_org_override_when_bound(): void
    {
        $org = Organization::factory()->create([
            'settings' => [
                'pagination' => ['timesheets' => 7],
                'invoicing' => ['default_tax_rate' => '7.00'],
            ],
        ]);

        $this->app->instance('currentOrganization', $org);

        $this->assertSame(7, (int) setting('pagination.timesheets'));
        $this->assertSame('7.00', setting('invoicing.default_tax_rate'));
        // missing keys still fall back to config / default
        $this->assertSame(
            (int) config('pagination.customers'),
            (int) setting('pagination.customers', 0),
        );
    }

    public function test_group_settings_merges_overrides_on_config_defaults(): void
    {
        $org = Organization::factory()->create([
            'settings' => [
                'pagination' => ['tags' => 99],
            ],
        ]);

        $merged = $org->paginationSettings();

        $this->assertSame(99, (int) ($merged['tags'] ?? 0));
        $this->assertSame(
            (int) config('pagination.timesheets'),
            (int) ($merged['timesheets'] ?? 0),
            'untouched keys should keep config default',
        );
    }

    public function test_setting_returns_default_for_unknown_key(): void
    {
        $this->assertSame('fallback', setting('non_existent.key', 'fallback'));
    }
}
