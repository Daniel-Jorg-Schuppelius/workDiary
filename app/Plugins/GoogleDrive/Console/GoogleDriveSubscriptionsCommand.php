<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleDriveSubscriptionsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\GoogleDrive\Console;

use App\Plugins\GoogleDrive\Services\GoogleDriveSubscriptionService;
use Illuminate\Console\Command;

/**
 * Google-Drive-Push-Kanäle sicherstellen (Feature 080; Audit 2026-08, W4.4).
 * Google-Kanäle laufen maximal ~24 Stunden und lassen sich nicht verlängern —
 * der Lauf muss deshalb MEHRMALS täglich neu anlegen, nicht nur einmal wie bei
 * Graph. Fehler zählen auf den Verbindungs-Health.
 */
class GoogleDriveSubscriptionsCommand extends Command {
    protected $signature = 'google-drive:subscriptions
        {--organization= : ID einer einzelnen Organisation, sonst alle}';

    protected $description = 'Stellt die Google-Drive-Push-Kanäle des Dokumenteingangs sicher (Laufzeit ~24 h, kein Verlängern).';

    public function handle(GoogleDriveSubscriptionService $subscriptions): int {
        $orgOption = $this->option('organization');
        $result = $subscriptions->ensureAll(is_numeric($orgOption) ? (int) $orgOption : null);

        $this->info(sprintf('Push-Kanäle sichergestellt: %d, fehlgeschlagen: %d', $result['ensured'], $result['failed']));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
