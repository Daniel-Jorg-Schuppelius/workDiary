<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SelfHostedSettingsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Plugins\Support;

use App\Plugins\Support\{PluginSettingsResolver, SelfHostedConfig, SelfHostedSettings};
use App\Plugins\Support\ShopOrders\ShopOrderSettings;
use Tests\TestCase;

/** Gemeinsame Config- und Schema-Regeln der selbst gehosteten Quellen (Konsolidierung kon-05). */
class SelfHostedSettingsTest extends TestCase {
    /** Organisation 0 löst nur Config auf — kein Datenbankzugriff. */
    private const int ORG = 0;

    public function test_base_url_is_trimmed_without_trailing_slash_or_null(): void {
        config(['plugins.kon-probe.base_url' => '  https://tools.example.test/sub/  ']);
        $this->assertSame('https://tools.example.test/sub', SelfHostedConfig::baseUrl(PluginSettingsResolver::for('kon-probe', self::ORG)));

        config(['plugins.kon-probe.base_url' => '   ']);
        $this->assertNull(SelfHostedConfig::baseUrl(PluginSettingsResolver::for('kon-probe', self::ORG)));

        config(['plugins.kon-probe.base_url' => null]);
        $this->assertNull(SelfHostedConfig::baseUrl(PluginSettingsResolver::for('kon-probe', self::ORG)));
    }

    public function test_page_size_and_max_pages_are_clamped(): void {
        // Ohne Betreiber-Konfiguration gelten die Vorgaben des Plugins.
        $this->assertNull(config('plugins.kon-unset.page_size'));
        $this->assertSame(50, SelfHostedConfig::pageSize('kon-unset', 50, cap: 100));
        $this->assertSame(200, SelfHostedConfig::maxPages('kon-unset', 200));

        config(['plugins.kon-probe.page_size' => 5000, 'plugins.kon-probe.max_pages' => 0, 'plugins.kon-probe.max_id_pages' => '7']);
        $this->assertSame(100, SelfHostedConfig::pageSize('kon-probe', 50, cap: 100));
        $this->assertSame(1, SelfHostedConfig::maxPages('kon-probe', 200));
        $this->assertSame(7, SelfHostedConfig::maxPages('kon-probe', 400, 'max_id_pages'));

        config(['plugins.kon-probe.page_size' => -3]);
        $this->assertSame(1, SelfHostedConfig::pageSize('kon-probe', 50, cap: 100));
    }

    public function test_field_emits_required_default_and_help_only_when_set(): void {
        $this->assertSame(
            ['key' => 'api_token', 'label' => 'kon-probe::kon-probe.settings.api_token', 'type' => 'password', 'required' => true, 'help' => 'kon-probe::kon-probe.settings.api_token_help'],
            SelfHostedSettings::field('kon-probe', 'api_token', 'password'),
        );
        $this->assertSame(
            ['key' => 'client_secret', 'label' => 'kon-probe::kon-probe.settings.client_secret', 'type' => 'password', 'required' => true],
            SelfHostedSettings::field('kon-probe', 'client_secret', 'password', help: false),
        );
        $this->assertSame(
            ['key' => 'window_days', 'label' => 'kon-probe::kon-probe.settings.window_days', 'type' => 'number', 'default' => 60, 'help' => 'kon-probe::kon-probe.settings.window_days_help'],
            SelfHostedSettings::field('kon-probe', 'window_days', 'number', required: false, default: 60),
        );
        $this->assertSame(
            ['key' => 'username', 'label' => 'kon-probe::kon-probe.settings.username', 'type' => 'text', 'help' => 'kon-probe::kon-probe.settings.username_help'],
            SelfHostedSettings::field('kon-probe', 'username', required: false),
        );
    }

    public function test_schema_frames_the_access_fields_with_base_url_and_private_network(): void {
        $schema = SelfHostedSettings::schema('kon-probe', [SelfHostedSettings::field('kon-probe', 'api_key', 'password')], [
            SelfHostedSettings::field('kon-probe', 'default_billable', 'boolean', required: false, default: true),
        ]);

        $this->assertSame(['base_url', 'api_key', 'allow_private_network', 'default_billable'], array_column($schema, 'key'));
        $this->assertSame(['type' => 'text', 'required' => true], array_intersect_key($schema[0], ['type' => 1, 'required' => 1]));
        $this->assertSame(['type' => 'boolean', 'default' => false], array_intersect_key($schema[2], ['type' => 1, 'default' => 1]));
        $this->assertArrayNotHasKey('required', $schema[2]);
        $this->assertSame(true, $schema[3]['default']);
    }

    public function test_shop_order_fields_keep_their_shared_core_texts(): void {
        $fields = ShopOrderSettings::fields();

        $this->assertSame(['initial_window_days', 'include_cancelled', 'allow_private_network'], array_column($fields, 'key'));
        $this->assertSame(30, $fields[0]['default']);
        $this->assertSame(__('integration.shop_orders.settings.allow_private_network'), $fields[2]['label']);
    }
}
