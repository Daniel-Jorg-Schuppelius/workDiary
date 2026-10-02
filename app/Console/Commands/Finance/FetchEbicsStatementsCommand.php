<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FetchEbicsStatementsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Finance;

use App\Enums\Finance\EbicsConnectionStatus;
use App\Models\Finance\EbicsConnection;
use App\Models\Platform\Organization;
use App\Services\Finance\Ebics\EbicsStatementService;
use App\Support\OrganizationContext;
use Illuminate\Console\Command;
use Throwable;

/** Täglicher Abruf der Tagesauszüge per EBICS (MVP-124) für alle freigeschalteten Zugänge. */
class FetchEbicsStatementsCommand extends Command {
    protected $signature = 'finance:ebics-statements';

    protected $description = 'Ruft die Tagesauszüge (camt.053) per EBICS ab und übernimmt sie in den Bankimport.';

    public function handle(EbicsStatementService $statements): int {
        // TENANT-BYPASS: Lauf über alle Organisationen; je Zugang wird sein Kontext gebunden.
        $connections = EbicsConnection::query()->withoutGlobalScopes()
            ->where('status', EbicsConnectionStatus::Active->value)
            ->get();

        foreach ($connections as $connection) {
            $organization = Organization::query()->withoutGlobalScopes()->find($connection->organization_id);
            if (! $organization instanceof Organization) {
                continue;
            }
            OrganizationContext::run($organization, function () use ($connection, $statements): void {
                try {
                    $counts = $statements->fetch($connection);
                    $this->line(sprintf('Zugang %d: %d Auszüge, %d übersprungen.', $connection->id, $counts['statements'], $counts['skipped']));
                } catch (Throwable $e) {
                    // Der Fehler steht schon im Journal des Zugangs; der Lauf geht weiter.
                    report($e);
                }
            });
        }

        return self::SUCCESS;
    }
}
