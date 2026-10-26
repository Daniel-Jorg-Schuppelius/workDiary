<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleProposeCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Reselling;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Platform\Organization;
use App\Services\Reselling\Register\LinkProposer;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Vorschlagslauf des Reselling-Registers im Zeitplan (Feature 185, MVP-1406):
 * je Organisation die offenen Perioden gegen den Belegspiegel, sichere
 * Treffer automatisch bestätigt. Stündlich nach dem Spiegel-Sync; ein schon
 * laufender Lauf (Sperre je Organisation) wird übersprungen, nicht gewartet.
 */
class ResaleProposeCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'resale:propose ' . self::ORGANIZATION_OPTION;

    protected $description = 'Rechnungsbezüge des Reselling-Registers vorschlagen und sichere Treffer bestätigen (Feature 185)';

    public function handle(LinkProposer $proposer): int {
        $failures = $this->forEachOrganization(function (Organization $org) use ($proposer): void {
            try {
                $result = $proposer->propose($org);
            } catch (RuntimeException $e) {
                $this->warn(sprintf('Organisation #%d (%s): %s', $org->id, $org->name, $e->getMessage()));

                return;
            }
            $this->info(sprintf(
                'Organisation #%d (%s): %d Perioden, %d berechnet, %d teilweise, %d Bezüge, %d automatisch bestätigt, %d Positionen ohne Abo.',
                $org->id,
                $org->name,
                $result['periods'],
                $result['linked'],
                $result['partial'],
                $result['links'],
                $result['auto_confirmed'],
                $result['lines_without_subscription'],
            ));
        });

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
