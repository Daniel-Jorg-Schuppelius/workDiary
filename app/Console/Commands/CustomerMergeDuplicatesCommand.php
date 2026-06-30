<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerMergeDuplicatesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\{CustomerDuplicateFinder, CustomerMergeService};
use Illuminate\Console\Command;

/**
 * Listet Kunden-Dubletten und führt eindeutige Treffer (gleiche USt-IdNr. oder
 * Lexoffice-Kontaktnummer) auf Wunsch automatisch zusammen. Standard ist ein
 * Dry-Run; erst `--apply` schreibt. Unsichere Kandidaten (likely/fuzzy) bleiben
 * stets der manuellen Inbox vorbehalten.
 */
class CustomerMergeDuplicatesCommand extends Command {
    protected $signature = 'customer:merge-duplicates
        {--organization= : ID einer einzelnen Organisation, sonst alle}
        {--confidence=exact : Welche Stufe automatisch gemergt wird (exact|likely|fuzzy)}
        {--apply : Tatsächlich zusammenführen (sonst nur Vorschau)}';

    protected $description = 'Findet doppelte Kunden und führt eindeutige Treffer (USt-IdNr./Lexoffice-Nr.) zusammen. Ohne --apply nur Vorschau.';

    public function handle(CustomerDuplicateFinder $finder, CustomerMergeService $merger): int {
        $orgId = $this->option('organization');
        $confidence = (string) $this->option('confidence');
        $apply = (bool) $this->option('apply');

        $query = Organization::query();
        if ($orgId !== null && $orgId !== '') {
            $query->whereKey((int) $orgId);
        }

        $organizations = $query->get();
        if ($organizations->isEmpty()) {
            $this->warn('Keine Organisationen gefunden.');

            return self::SUCCESS;
        }

        $merged = 0;
        foreach ($organizations as $org) {
            $candidates = $finder->candidates($org, $confidence);
            if ($candidates->isEmpty()) {
                continue;
            }

            $this->line("Organisation #{$org->id} ({$org->name}): {$candidates->count()} Kandidat(en) [{$confidence}]");
            foreach ($candidates as $pair) {
                /** @var \App\Models\Customer $source */
                $source = $pair['source'];
                /** @var \App\Models\Customer $target */
                $target = $pair['target'];
                $reasons = implode(', ', $pair['reasons']);

                $arrow = $apply ? '→ zusammengeführt' : '→ würde zusammenführen';
                $this->line(sprintf(
                    '  #%d %s  %s  #%d %s  (%s)',
                    $source->id,
                    $source->name,
                    $arrow,
                    $target->id,
                    $target->name,
                    $reasons,
                ));

                if ($apply) {
                    $merger->merge($source, $target);
                    $merged++;
                }
            }
        }

        if (! $apply) {
            $this->info('Dry-Run — nichts geändert. Mit --apply ausführen.');
        } else {
            $this->info("{$merged} Kunde(n) zusammengeführt.");
        }

        return self::SUCCESS;
    }
}
