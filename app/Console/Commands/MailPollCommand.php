<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailPollCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Mail\EmailConnection;
use App\Models\Platform\Organization;
use App\Services\Mail\MailboxPoller;
use Illuminate\Console\Command;

/**
 * Ruft die verbundenen Eingangspostfächer je Organisation ab (Feature 056,
 * MVP-117) und stellt neue Mails als Vorschläge in die Integrations-Inbox.
 * Abgerufen wird nach Stand in Stapeln ({@see MailboxPoller}, MVP-1139);
 * verarbeitete Mails werden nur markiert/verschoben, nie gelöscht. Läuft im
 * Scheduler und manuell aus der Admin-UI.
 */
class MailPollCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'mail:poll ' . self::ORGANIZATION_OPTION;

    protected $description = 'Ruft IMAP-Eingangspostfächer ab und stellt neue Mails in die Integrations-Inbox.';

    public function handle(MailboxPoller $poller): int {
        foreach ($this->organizationsToProcess() as $org) {
            // Fehler je Postfach fängt der Poller — Org-Fehler sollen weiter durchschlagen.
            $this->withOrganizationContext($org, function (Organization $org) use ($poller): void {
                $connections = EmailConnection::query()->where('organization_id', $org->id)->get();
                foreach ($connections as $connection) {
                    if (! $connection->isActive()) {
                        continue;
                    }

                    $result = $poller->poll($org, $connection);
                    match ($result['status']) {
                        'ok' => $this->info(sprintf('Organisation #%d / %s: %d Mails verarbeitet, %d neu eingereiht.', $org->id, $connection->name, $result['processed'], $result['created'])),
                        'locked' => $this->line(sprintf('Organisation #%d / %s: Abruf läuft bereits.', $org->id, $connection->name)),
                        'failed' => $this->error(sprintf('Organisation #%d / %s: Abbruch — %s', $org->id, $connection->name, (string) $result['error'])),
                    };
                }
            });
        }

        return self::SUCCESS;
    }
}
