<?php
/*
 * Created on   : Sat Aug 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCloseStaleSessionsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Learning\LearningTimeService;
use Illuminate\Console\Command;

/**
 * Schließt liegengebliebene Lernsitzungen (Feature 149, MVP-749).
 *
 * Browser zu, Gerät aus, Netz weg — dann kommt kein Stopp mehr. Ohne
 * diesen Kehraus liefe die Sitzung weiter und würde beim nächsten Öffnen
 * als riesige Spanne gebucht. Beendet wird beim **letzten Lebenszeichen**,
 * nicht jetzt.
 */
class LearningCloseStaleSessionsCommand extends Command {
    protected $signature = 'learning:close-stale-sessions';

    protected $description = 'Beendet Lernsitzungen ohne Lebenszeichen beim letzten Puls.';

    public function handle(LearningTimeService $time): int {
        $closed = $time->closeStaleSessions();

        $this->info(sprintf('%d Lernsitzung(en) geschlossen.', $closed));

        return self::SUCCESS;
    }
}
