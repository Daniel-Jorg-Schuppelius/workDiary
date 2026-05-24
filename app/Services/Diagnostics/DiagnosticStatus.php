<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiagnosticStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Diagnostics;

enum DiagnosticStatus: string {
    case Ok = 'ok';
    case Warn = 'warn';
    case Critical = 'critical';
    case Unknown = 'unknown';

    public function severity(): int {
        return match ($this) {
            self::Ok => 0,
            self::Unknown => 1,
            self::Warn => 2,
            self::Critical => 3,
        };
    }

    public static function worst(self ...$statuses): self {
        $worst = self::Ok;
        foreach ($statuses as $status) {
            if ($status->severity() > $worst->severity()) {
                $worst = $status;
            }
        }

        return $worst;
    }
}
