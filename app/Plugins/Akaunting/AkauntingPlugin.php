<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AkauntingPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Akaunting;

use App\Plugins\AbstractPlugin;
use App\Plugins\Akaunting\Api\AkauntingClientFactory;
use App\Plugins\Support\{ChecksConnectionHealth, SelfHostedSettings};

/**
 * Akaunting (Feature 176, Phase 158): Rechnungen und Lieferantenrechnungen
 * erscheinen nur lesend im Belegfluss, mit Link zum Original. Wie easybill
 * ohne Plugin-Capability — der Kern kennt den Abruf über die
 * {@see \App\Services\Finance\Accounting\Vouchers\VoucherPullerRegistry}.
 */
class AkauntingPlugin extends AbstractPlugin {
    use ChecksConnectionHealth;

    public const ID = 'akaunting';

    public const SERVICE_PROVIDER = AkauntingServiceProvider::class;

    public function name(): string {
        return 'Akaunting';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return (string) __('akaunting::akaunting.plugin.description');
    }

    public function settingsSchema(): array {
        return SelfHostedSettings::schema(self::ID, [
            SelfHostedSettings::field(self::ID, 'email'),
            SelfHostedSettings::field(self::ID, 'password', 'password', help: false),
            SelfHostedSettings::field(self::ID, 'company_id', 'number', required: false, default: 1),
            SelfHostedSettings::field(self::ID, 'pull_vouchers', 'boolean', required: false, default: false),
        ]);
    }

    public function capabilities(): array {
        return [];
    }

    protected function healthConfigured(int $organizationId): bool {
        return AkauntingConfig::isConfigured($organizationId);
    }

    protected function healthProbe(int $organizationId): void {
        app(AkauntingClientFactory::class)->for($organizationId)->probe();
    }
}
