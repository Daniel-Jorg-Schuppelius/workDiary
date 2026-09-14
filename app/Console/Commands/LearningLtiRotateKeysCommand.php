<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningLtiRotateKeysCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Learning\LearningLtiKeyService;
use Illuminate\Console\Command;

/** LTI-Signaturschlüssel tauschen, sobald er fällig ist (Feature 149). */
class LearningLtiRotateKeysCommand extends Command {
    protected $signature = 'learning:lti-rotate-keys {--force : Sofort tauschen, auch wenn der Schlüssel noch nicht fällig ist}';

    protected $description = 'Tauscht den LTI-Signaturschlüssel nach Frist und entfernt abgelaufene zurückgezogene Schlüssel.';

    public function handle(LearningLtiKeyService $keys): int {
        $days = max(1, (int) config('learning.lti.key_rotation_days', 90));

        if ((bool) $this->option('force') || $keys->isDue($days)) {
            $this->info('Neuer Schlüssel aktiv: ' . $keys->rotate()->kid);
        } else {
            $this->line('Schlüssel noch nicht fällig.');
        }

        $pruned = $keys->prune();

        if ($pruned > 0) {
            $this->line($pruned . ' abgelaufene Schlüssel entfernt.');
        }

        return self::SUCCESS;
    }
}
