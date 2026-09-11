<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleDraftLocalCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Reselling;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Organization;
use App\Services\Reselling\Register\ResaleLocalDraftRun;
use Illuminate\Console\Command;

/**
 * Serienrechnung bei lokaler Rechnungshoheit (Feature 152): je Organisation
 * mit aktivem Schalter `resale.auto_local_drafts` ein Lauf von
 * {@see ResaleLocalDraftRun}. Täglich im Zeitplan nach `resale:sync-periods`;
 * idempotent. `--force` übergeht den Schalter für einen einmaligen Lauf,
 * `--dry-run` zählt nur.
 */
class ResaleDraftLocalCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'resale:draft-local ' . self::ORGANIZATION_OPTION
        . ' {--dry-run : Nur zählen, nichts schreiben}'
        . ' {--force : Auch Organisationen ohne aktiven Serienlauf (einmaliger Lauf)}';

    protected $description = 'Serienrechnung: lokale Rechnungsentwürfe aus fälligen Reselling-Perioden je Rechnungsempfänger (Feature 152)';

    public function handle(ResaleLocalDraftRun $run): int {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $failures = $this->forEachOrganization(function (Organization $org) use ($run, $dryRun, $force): void {
            if (! $force && ! $run->enabledFor($org)) {
                $this->line(sprintf('Organisation #%d (%s): Serienlauf nicht aktiv — übersprungen.', $org->id, $org->name));

                return;
            }

            $result = $run->run($org, null, $dryRun);
            $this->info(sprintf(
                'Organisation #%d (%s): %d Empfänger, %d Entwürfe%s, %d übersprungen, %d Fehler (Vorlauf %d Tage).',
                $org->id,
                $org->name,
                $result['recipients'],
                $result['drafts'],
                $dryRun ? ' (Probelauf, nichts geschrieben)' : '',
                $result['skipped'],
                count($result['errors']),
                $run->leadDaysFor($org),
            ));
            foreach ($result['errors'] as $error) {
                $this->warn('  ' . $error);
            }
        });

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
