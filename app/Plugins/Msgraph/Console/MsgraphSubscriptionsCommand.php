<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphSubscriptionsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Console;

use App\Plugins\Msgraph\Services\MsgraphSubscriptionService;
use Illuminate\Console\Command;

/**
 * Graph-Change-Notification-Subscriptions des Dokumenteingangs sicherstellen
 * (MS365-Plan §8): fehlende anlegen, ablaufende erneuern (driveItem < 30 Tage).
 * Läuft täglich über den Scheduler; Fehler zählen auf den Verbindungs-Health.
 */
class MsgraphSubscriptionsCommand extends Command {
    protected $signature = 'msgraph:subscriptions
        {--organization= : ID einer einzelnen Organisation, sonst alle}';

    protected $description = 'Stellt Graph-Change-Notification-Subscriptions des Microsoft-Dokumenteingangs sicher (anlegen/erneuern).';

    public function handle(MsgraphSubscriptionService $subscriptions): int {
        $orgOption = $this->option('organization');
        $result = $subscriptions->ensureAll(is_numeric($orgOption) ? (int) $orgOption : null);

        $this->info(sprintf('Subscriptions sichergestellt: %d, fehlgeschlagen: %d', $result['ensured'], $result['failed']));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
