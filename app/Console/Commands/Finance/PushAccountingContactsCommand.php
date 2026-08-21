<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PushAccountingContactsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Finance;

use App\Models\Organization;
use App\Services\Finance\Accounting\ContactPushService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Kontakt-Push in die Buchhaltung (Feature 122, MVP-611).
 *
 * Ein Lauf statt eines Observers: Ein Observer am Kunden würde jeden
 * Tippfehler sofort zu einem API-Aufruf machen. Führt die Buchhaltung die
 * Stammdaten, läuft gar nichts — das ist keine Störung, sondern die
 * Festlegung.
 */
class PushAccountingContactsCommand extends Command {
    protected $signature = 'accounting:push-contacts
        {plugin : Plugin-ID des Buchhaltungssystems (sevdesk, easybill, orgamax, lexoffice)}
        {--organization= : ID einer einzelnen Organisation, sonst alle}';

    protected $description = 'Überträgt Kunden als Kontakte in das führende Buchhaltungssystem (Feature 122).';

    public function handle(ContactPushService $contacts): int {
        if (! $contacts->pushAllowed()) {
            $this->warn((string) __('accounting.error.accounting_leads'));

            return self::SUCCESS;
        }

        $pluginId = (string) $this->argument('plugin');
        $orgOption = $this->option('organization');
        $totals = ['pushed' => 0, 'skipped' => 0, 'failed' => 0];

        $organizations = Organization::query()
            ->when(is_numeric($orgOption), fn ($q) => $q->whereKey((int) $orgOption))
            ->get();

        foreach ($organizations as $organization) {
            try {
                $result = $contacts->pushAll($organization, $pluginId);
                foreach ($totals as $key => $value) {
                    $totals[$key] = $value + $result[$key];
                }
            } catch (Throwable) {
                $totals['failed']++;
            }
        }

        $this->info(sprintf(
            'Kontakt-Push (%s): %d übertragen, %d übersprungen, %d Fehler',
            $pluginId,
            $totals['pushed'],
            $totals['skipped'],
            $totals['failed'],
        ));

        return $totals['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
