<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevOnlineSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\DatevOnline\Console;

use App\Models\Platform\Organization;
use App\Plugins\DatevOnline\Enums\DatevConnectionStatus;
use App\Plugins\DatevOnline\Models\DatevOnlineConnection;
use App\Plugins\DatevOnline\Services\{DatevDocumentUploader, DatevExtfTransferService};
use App\Support\OrganizationContext;
use Illuminate\Console\Command;
use Throwable;

/** Nächtlicher Lauf (MVP-122): Belegbilder hochladen, offene EXTF-Importjobs abfragen. */
class DatevOnlineSyncCommand extends Command {
    protected $signature = 'datev-online:sync';

    protected $description = 'Überträgt Belegbilder an DATEV Unternehmen online und fragt offene EXTF-Importe ab.';

    public function handle(DatevDocumentUploader $uploader, DatevExtfTransferService $extf): int {
        // TENANT-BYPASS: Lauf über alle Organisationen; je Verbindung wird ihr Kontext gebunden.
        $connections = DatevOnlineConnection::query()->withoutGlobalScopes()
            ->where('status', DatevConnectionStatus::Active->value)
            ->whereNull('disabled_at')
            ->whereNotNull('datev_client_number')
            ->get();

        foreach ($connections as $connection) {
            $organization = Organization::query()->withoutGlobalScopes()->find($connection->organization_id);
            if (! $organization instanceof Organization) {
                continue;
            }
            OrganizationContext::run($organization, function () use ($connection, $uploader, $extf): void {
                try {
                    $counts = $uploader->uploadPending($connection);
                    $jobs = $extf->refresh($connection);
                    $connection->recordConnectionSuccess();
                    $this->line(sprintf('Organisation %d: %d Belege, %d fehlgeschlagen, %d Importe abgeschlossen.', $connection->organization_id, $counts['transferred'], $counts['failed'], $jobs));
                } catch (Throwable $e) {
                    $connection->recordConnectionFailure(class_basename($e));
                    report($e);
                }
            });
        }

        return self::SUCCESS;
    }
}
