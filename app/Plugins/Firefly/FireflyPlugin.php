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
use App\Plugins\AbstractPlugin;
use App\Plugins\Contracts\{PaymentSyncer, PluginCapability};
use App\Plugins\Firefly\Api\FireflyClientFactory;
use App\Plugins\Firefly\Models\FireflyConnection;
use App\Plugins\Firefly\Services\FireflySyncService;
use App\Plugins\Support\{ChecksConnectionHealth, SelfHostedSettings};

/**
 * Firefly III (Feature 176, Phase 158): Kontobewegungen als Abgleichquelle.
 * Eingänge und Ausgänge werden gespiegelt und über Belegnummern Rechnungen bzw.
 * Eingangsrechnungen zugeordnet; gebucht wird in WorkDiary nichts.
 */
class FireflyPlugin extends AbstractPlugin implements PaymentSyncer {
    use ChecksConnectionHealth;

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
        return SelfHostedSettings::schema(self::ID, [
            SelfHostedSettings::field(self::ID, 'access_token', 'password'),
            SelfHostedSettings::field(self::ID, 'window_days', 'number', required: false, default: 60),
        ]);
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

    protected function healthConfigured(int $organizationId): bool {
        return FireflyConfig::isConfigured($organizationId);
    }

    protected function healthConnectionModel(): string {
        return FireflyConnection::class;
    }

    protected function healthProbe(int $organizationId): void {
        app(FireflyClientFactory::class)->for($organizationId)->probe();
    }
}
