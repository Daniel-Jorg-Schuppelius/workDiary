<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SelectsOrganization.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Search\Concerns;

use Illuminate\Console\Command;

/**
 * Option `--organization` der Index-Befehle. Ohne Organisation laufen sie
 * bewusst über alle Mandanten (Konsolen-Vertrag).
 *
 * @mixin Command
 */
trait SelectsOrganization {
    /** @return int|false|null  false = ungültige Eingabe */
    private function organizationOption(): int|false|null {
        $value = $this->option('organization');
        if ($value === null || $value === '') {
            return null;
        }
        if (! ctype_digit((string) $value)) {
            $this->error('--organization erwartet eine numerische ID.');

            return false;
        }

        return (int) $value;
    }
}
