<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceNinjaClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\InvoiceNinja\Api;

use App\Plugins\InvoiceNinja\InvoiceNinjaConfig;
use App\Plugins\Support\PluginHttpFactory;
use RuntimeException;

/** Baut den Invoice-Ninja-Client aus den Plugin-Einstellungen einer Organisation. */
class InvoiceNinjaClientFactory {
    public function isConfigured(int $organizationId): bool {
        return InvoiceNinjaConfig::isConfigured($organizationId);
    }

    public function for(int $organizationId): InvoiceNinjaClient {
        $config = InvoiceNinjaConfig::resolve($organizationId);
        if ($config['api_token'] === null) {
            throw new RuntimeException('Invoice Ninja ist für diese Organisation nicht eingerichtet (API-Token fehlt).');
        }

        return new InvoiceNinjaClient(app(PluginHttpFactory::class), $config['base_url'], $config['api_token'], $config['page_size']);
    }

    public function baseUrl(int $organizationId): string {
        return InvoiceNinjaConfig::resolve($organizationId)['base_url'];
    }
}
