<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OAuthConnectionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Integration;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zustand einer OAuth-Verbindung, die über
 * {@see \App\Plugins\Support\ConnectionOAuthController} verbunden und getrennt
 * wird (zuerst die OneNote-Übernahme, MVP-815). Auto-Disable bei Fehlern läuft
 * getrennt über {@see \App\Models\Concerns\HasConnectionHealth}.
 */
enum OAuthConnectionStatus: string implements HasLabel {
    use HasOptions;

    case Active = 'active';
    case Disconnected = 'disconnected';

    public function label(): string {
        return (string) __('integration.oauth_connection_status.' . $this->value);
    }
}
