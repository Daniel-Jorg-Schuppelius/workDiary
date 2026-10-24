<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FireflyPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Firefly;

use App\Models\Platform\Organization;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{PaymentSyncer, PluginCapability};
use App\Plugins\Firefly\Api\FireflyClientFactory;
use App\Plugins\Firefly\Models\FireflyConnection;
use App\Plugins\Firefly\Services\FireflySyncService;
use App\Plugins\Support\ApiHealthProbe;

/**
 * Firefly III (Feature 176, Phase 158): Kontobewegungen als Abgleichquelle.
 * Eingänge und Ausgänge werden gespiegelt und über Belegnummern Rechnungen bzw.
 * Eingangsrechnungen zugeordnet; gebucht wird in WorkDiary nichts.
 */
class FireflyPlugin extends AbstractPlugin implements PaymentSyncer {
    public const ID = 'firefly';

    public const SERVICE_PROVIDER = FireflyServiceProvider::class;

    public function name(): string {
        return 'Firefly III';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return (string) __('firefly::firefly.plugin.description');
    }

    public function settingsSchema(): array {
        return [
            ['key' => 'base_url', 'label' => __('firefly::firefly.settings.base_url'), 'type' => 'text', 'required' => true, 'help' => __('firefly::firefly.settings.base_url_help')],
            ['key' => 'access_token', 'label' => __('firefly::firefly.settings.access_token'), 'type' => 'password', 'required' => true, 'help' => __('firefly::firefly.settings.access_token_help')],
            ['key' => 'window_days', 'label' => __('firefly::firefly.settings.window_days'), 'type' => 'number', 'default' => 60, 'help' => __('firefly::firefly.settings.window_days_help')],
            ['key' => 'allow_private_network', 'label' => __('firefly::firefly.settings.allow_private_network'), 'type' => 'boolean', 'default' => false, 'help' => __('firefly::firefly.settings.allow_private_network_help')],
        ];
    }

    public function capabilities(): array {
        return [PluginCapability::PaymentSync];
    }

    /** @return array<string, int|string> */
    public function syncPayments(Organization $organization): array {
        return app(FireflySyncService::class)->run($organization);
    }

    public function adminPanel(): ?array {
        return ['route' => 'admin.firefly.index', 'label' => 'Firefly III', 'icon' => 'account_balance'];
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }
        if (! FireflyConfig::isConfigured((int) $organization->id)) {
            return PluginHealth::degraded((string) __('firefly::firefly.health.not_configured'), 'not_configured');
        }
        $connection = FireflyConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof FireflyConnection || ! $connection->isReady()) {
            return PluginHealth::degraded((string) __('firefly::firefly.health.not_connected'), 'not_connected');
        }

        return ApiHealthProbe::probe($this->name(), fn () => app(FireflyClientFactory::class)->for((int) $organization->id)->probe());
    }
}
