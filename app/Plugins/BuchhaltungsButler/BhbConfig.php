<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BhbConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\BuchhaltungsButler;

use App\Plugins\Support\PluginSettingsResolver;

/**
 * Effektive BuchhaltungsButler-Konfiguration je Organisation (Muster
 * SevDesk-/EasybillConfig): plugin_settings vor
 * config('plugins.buchhaltungsbutler.*'). Leere Strings zählen als „nicht
 * gesetzt" (leere encrypted-Strings ⇒ null).
 */
class BhbConfig {
    /**
     * @return array{api_client: ?string, api_secret: ?string, api_key: ?string, base_url: string, push_enabled: bool, enabled: bool}
     */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(BuchhaltungsButlerPlugin::ID, $organizationId);

        return [
            'api_client' => $r->string('api_client'),
            'api_secret' => $r->string('api_secret'),
            'api_key' => $r->string('api_key'),
            'base_url' => rtrim($r->string('base_url') ?? (string) config('plugins.buchhaltungsbutler.base_url'), '/'),
            'push_enabled' => $r->bool('push_enabled', true),
            'enabled' => $r->enabled(),
        ];
    }
}
