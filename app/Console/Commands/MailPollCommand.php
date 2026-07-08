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

use App\Models\{EmailConnection, Organization};
use App\Services\Mail\{MailIntakeService, MailboxGateway};
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Ruft die verbundenen Eingangspostfächer je Organisation ab (Feature 056,
 * MVP-117) und stellt neue Mails als Vorschläge in die Integrations-Inbox.
 * Idempotent über die Message-ID; verarbeitete Mails werden nur markiert/
 * verschoben, nie gelöscht. Läuft im Scheduler und manuell aus der Admin-UI.
 */
class MailPollCommand extends Command {
    protected $signature = 'mail:poll
        {--organization= : ID einer einzelnen Organisation, sonst alle}';

    protected $description = 'Ruft IMAP-Eingangspostfächer ab und stellt neue Mails in die Integrations-Inbox.';

    public function handle(MailboxGateway $gateway, MailIntakeService $intake): int {
        $orgId = $this->option('organization');
        $query = Organization::query();
        if ($orgId !== null && $orgId !== '') {
            $query->whereKey((int) $orgId);
        }

        foreach ($query->get() as $org) {
            app()->instance('currentOrganization', $org);

            $connections = EmailConnection::query()->where('organization_id', $org->id)->get();
            foreach ($connections as $connection) {
                if (! $connection->isActive()) {
                    continue;
                }

                try {
                    $created = 0;
                    foreach ($gateway->fetch($connection) as $message) {
                        $result = $intake->intake($org, $connection, $message);
                        if ($result === 'created') {
                            $created++;
                        }
                        $gateway->markProcessed($connection, $message);
                    }
                    $connection->forceFill(['last_polled_at' => Carbon::now()])->save();
                    $this->info(sprintf('Organisation #%d / %s: %d neue Mails eingereiht.', $org->id, $connection->name, $created));
                } catch (Throwable $e) {
                    $this->error(sprintf('Organisation #%d / %s: Abbruch — %s', $org->id, $connection->name, class_basename($e)));
                }
            }
        }

        return self::SUCCESS;
    }
}
