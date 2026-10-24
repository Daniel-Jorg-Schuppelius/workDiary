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

use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\InvoiceNinja\Api\InvoiceNinjaClientFactory;
use App\Plugins\Support\ApiHealthProbe;

/**
 * Invoice Ninja (Feature 176, Phase 158): Rechnungen, Gutschriften und
 * Ausgaben erscheinen nur lesend im Belegfluss, mit Link zum Original. Wie
 * easybill ohne Plugin-Capability — der Kern kennt den Abruf über die
 * {@see \App\Services\Finance\Accounting\Vouchers\VoucherPullerRegistry}.
 */
class InvoiceNinjaPlugin extends AbstractPlugin {
    public const ID = 'invoiceninja';

    public const SERVICE_PROVIDER = InvoiceNinjaServiceProvider::class;

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
        return [
            ['key' => 'base_url', 'label' => __('invoiceninja::invoiceninja.settings.base_url'), 'type' => 'text', 'default' => InvoiceNinjaConfig::HOSTED_URL, 'help' => __('invoiceninja::invoiceninja.settings.base_url_help')],
            ['key' => 'api_token', 'label' => __('invoiceninja::invoiceninja.settings.api_token'), 'type' => 'password', 'required' => true, 'help' => __('invoiceninja::invoiceninja.settings.api_token_help')],
            ['key' => 'pull_vouchers', 'label' => __('invoiceninja::invoiceninja.settings.pull_vouchers'), 'type' => 'boolean', 'default' => false, 'help' => __('invoiceninja::invoiceninja.settings.pull_vouchers_help')],
            ['key' => 'allow_private_network', 'label' => __('invoiceninja::invoiceninja.settings.allow_private_network'), 'type' => 'boolean', 'default' => false, 'help' => __('invoiceninja::invoiceninja.settings.allow_private_network_help')],
        ];
    }

    public function capabilities(): array {
        return [];
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }
        if (! InvoiceNinjaConfig::isConfigured((int) $organization->id)) {
            return PluginHealth::degraded((string) __('invoiceninja::invoiceninja.health.not_configured'), 'not_configured');
        }

        return ApiHealthProbe::probe($this->name(), fn () => app(InvoiceNinjaClientFactory::class)->for((int) $organization->id)->probe());
    }
}
