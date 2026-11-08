<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceNinjaPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\InvoiceNinja;

use App\Plugins\AbstractPlugin;
use App\Plugins\InvoiceNinja\Api\InvoiceNinjaClientFactory;
use App\Plugins\Support\{ChecksConnectionHealth, SelfHostedSettings};

/**
 * Invoice Ninja (Feature 176, Phase 158): Rechnungen, Gutschriften und
 * Ausgaben erscheinen nur lesend im Belegfluss, mit Link zum Original. Wie
 * easybill ohne Plugin-Capability — der Kern kennt den Abruf über die
 * {@see \App\Services\Finance\Accounting\Vouchers\VoucherPullerRegistry}.
 */
class InvoiceNinjaPlugin extends AbstractPlugin {
    use ChecksConnectionHealth;

    public const string ID = 'invoiceninja';

    public const string SERVICE_PROVIDER = InvoiceNinjaServiceProvider::class;

    public function name(): string {
        return 'Invoice Ninja';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return (string) __('invoiceninja::invoiceninja.plugin.description');
    }

    public function settingsSchema(): array {
        // Gehostet ist die Vorgabe, deshalb keine Pflicht-Basis-URL wie im Standardschema.
        return [
            SelfHostedSettings::field(self::ID, 'base_url', required: false, default: InvoiceNinjaConfig::HOSTED_URL),
            SelfHostedSettings::field(self::ID, 'api_token', 'password'),
            SelfHostedSettings::field(self::ID, 'pull_vouchers', 'boolean', required: false, default: false),
            SelfHostedSettings::field(self::ID, 'allow_private_network', 'boolean', required: false, default: false),
        ];
    }

    public function capabilities(): array {
        return [];
    }

    protected function healthConfigured(int $organizationId): bool {
        return InvoiceNinjaConfig::isConfigured($organizationId);
    }

    protected function healthProbe(int $organizationId): void {
        app(InvoiceNinjaClientFactory::class)->for($organizationId)->probe();
    }
}
