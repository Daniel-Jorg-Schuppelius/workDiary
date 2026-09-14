<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningLtiPruneNoncesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Learning\LearningLtiNonceStore;
use Illuminate\Console\Command;

/** Abgelaufene LTI-Nonces entfernen (Feature 149) — ihre Token sind ohnehin ungültig. */
class LearningLtiPruneNoncesCommand extends Command {
    protected $signature = 'learning:lti-prune-nonces';

    protected $description = 'Entfernt abgelaufene LTI-Nonces.';

    public function handle(LearningLtiNonceStore $nonces): int {
        $this->line($nonces->prune() . ' abgelaufene Nonces entfernt.');

        return self::SUCCESS;
    }
}
