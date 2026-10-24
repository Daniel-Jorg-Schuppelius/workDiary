<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AkauntingClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Akaunting\Api;

use App\Plugins\Akaunting\AkauntingConfig;
use App\Plugins\Support\PluginHttpFactory;
use RuntimeException;

/** Baut den Akaunting-Client aus den Plugin-Einstellungen einer Organisation. */
class AkauntingClientFactory {
    public function isConfigured(int $organizationId): bool {
        return AkauntingConfig::isConfigured($organizationId);
    }

    public function for(int $organizationId): AkauntingClient {
        $config = AkauntingConfig::resolve($organizationId);
        if ($config['base_url'] === null || $config['email'] === null || $config['password'] === null) {
            throw new RuntimeException('Akaunting ist für diese Organisation nicht eingerichtet (Adresse oder Zugang fehlen).');
        }

        return new AkauntingClient(app(PluginHttpFactory::class), $config['base_url'], $config['email'], $config['password'], $config['company_id'], $config['page_size']);
    }

    /** @return array{base_url: ?string, company_id: int} Adresse und Firma für Links, ohne Zugang. */
    public function location(int $organizationId): array {
        $config = AkauntingConfig::resolve($organizationId);

        return ['base_url' => $config['base_url'], 'company_id' => $config['company_id']];
    }
}
