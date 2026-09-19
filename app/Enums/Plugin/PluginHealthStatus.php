<?php
/*
 * Created on   : Sat Sep 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginHealthStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Plugin;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Stufe eines Plugin-Health-Checks, wie sie {@see \App\Plugins\PluginHealth}
 * liefert und PluginState.last_health_status speichert.
 */
enum PluginHealthStatus: string implements HasLabel {
    use HasOptions;

    case Ok = 'ok';
    case Degraded = 'degraded';
    case Failing = 'failing';

    public function label(): string {
        return match ($this) {
            self::Ok => __('Zustand ok'),
            self::Degraded => __('Zustand eingeschränkt'),
            self::Failing => __('Zustand fehlerhaft'),
        };
    }

    public function tone(): string {
        return match ($this) {
            self::Ok => 'success',
            self::Degraded => 'warning',
            self::Failing => 'error',
        };
    }
}
