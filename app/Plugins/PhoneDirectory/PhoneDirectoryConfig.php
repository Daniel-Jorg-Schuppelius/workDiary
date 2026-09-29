<?php
/*
 * Created on   : Mon Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PhoneDirectoryConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\PhoneDirectory;

use App\Plugins\Support\PluginSettingsResolver;

/**
 * Effektive Telefonauskunft-Konfiguration: plugin_settings der gebundenen
 * Organisation vor `config('plugins.phonedirectory.*')`.
 *
 * @phpstan-type PhoneDirectoryEndpoint array{url: string, token: string}
 * @phpstan-type PhoneDirectorySettings array{enabled: bool, endpoints: list<array{url: string, token: string}>}
 */
class PhoneDirectoryConfig {
    /** @return array{enabled: bool, endpoints: list<array{url: string, token: string}>} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(PhoneDirectoryPlugin::ID, $organizationId);

        return [
            'enabled' => $r->enabled(),
            'endpoints' => self::parseEndpoints((string) $r->string('endpoints', '')),
        ];
    }

    /**
     * Eine Auskunft je Zeile: `url` oder `url|token`; Leerzeilen und mit `#`
     * beginnende Kommentarzeilen werden übersprungen.
     *
     * @return list<array{url: string, token: string}>
     */
    public static function parseEndpoints(string $raw): array {
        $out = [];
        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $raw)) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            [$url, $token] = array_pad(explode('|', $line, 2), 2, '');
            $url = trim($url);
            if ($url === '') {
                continue;
            }
            $out[] = ['url' => $url, 'token' => trim($token)];
        }

        return $out;
    }
}
