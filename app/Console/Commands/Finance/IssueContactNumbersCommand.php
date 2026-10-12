<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IssueContactNumbersCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Finance;

use App\Console\Concerns\IteratesOrganizations;
use App\Enums\Numbering\ContactNumberOutcome;
use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use App\Models\Supplier\Supplier;
use App\Services\Finance\Accounting\ContactPushService;
use App\Services\Numbering\{NumberAuthority, NumberSequenceService};
use Illuminate\Console\Command;
use Throwable;

/**
 * Nachlauf zur Nummernvergabe durch ein Buchhaltungsprogramm (MVP-1131):
 * überträgt Kunden und Lieferanten, die noch eine Entwurfsnummer tragen —
 * aus Importen, API, Offline-Sync oder einem gescheiterten Sofort-Push.
 */
class IssueContactNumbersCommand extends Command {
    use IteratesOrganizations;

    /** Obergrenze je Organisation und Lauf; Lexoffice erlaubt zwei Anfragen je Sekunde. */
    private const BATCH = 200;

    protected $signature = 'accounting:issue-contact-numbers ' . self::ORGANIZATION_OPTION;

    protected $description = 'Holt Kunden- und Lieferantennummern des vergebenden Buchhaltungsprogramms für Kontakte mit Entwurfsnummer (MVP-1131).';

    public function handle(ContactPushService $contacts): int {
        $totals = ['adopted' => 0, 'taken' => 0, 'pending' => 0, 'failed' => 0];

        $this->forEachOrganization(function (Organization $organization) use ($contacts, &$totals): void {
            foreach ([Customer::class, Supplier::class] as $class) {
                $parties = $class::query()
                    ->withoutCollective()
                    ->whereNull('archived_at')
                    ->where('number_source', '!=', NumberAuthority::SOURCE_LOCAL)
                    ->where('number', 'like', NumberSequenceService::DRAFT_PREFIX . '%')
                    ->orderBy('id')
                    ->limit(self::BATCH)
                    ->get();

                foreach ($parties as $party) {
                    try {
                        $outcome = $contacts->issueNumber($party);
                    } catch (Throwable $e) {
                        $totals['failed']++;
                        $this->warn(sprintf('Organisation #%d, %s #%d: %s', $organization->id, class_basename($party), $party->id, $e->getMessage()));

                        continue;
                    }
                    match ($outcome) {
                        ContactNumberOutcome::Adopted, ContactNumberOutcome::Unchanged => $totals['adopted']++,
                        ContactNumberOutcome::Taken => $totals['taken']++,
                        default => $totals['pending']++,
                    };
                }
            }
        });

        $this->info(sprintf(
            'Nummern übernommen: %d, schon vergeben (Inbox): %d, offen: %d, Fehler: %d',
            $totals['adopted'],
            $totals['taken'],
            $totals['pending'],
            $totals['failed'],
        ));

        return $totals['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
