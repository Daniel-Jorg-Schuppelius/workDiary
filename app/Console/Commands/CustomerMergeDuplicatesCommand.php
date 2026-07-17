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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Listet Kunden-Dubletten und führt eindeutige Treffer (gleiche USt-IdNr. oder
 * Lexoffice-Kontaktnummer) auf Wunsch automatisch zusammen. Standard ist ein
 * Dry-Run; erst `--apply` schreibt. Unsichere Kandidaten (likely/fuzzy) bleiben
 * stets der manuellen Inbox vorbehalten.
 *
 * @extends MergeDuplicatesCommand<\App\Models\Customer>
 */
class CustomerMergeDuplicatesCommand extends MergeDuplicatesCommand {
    protected $signature = 'customer:merge-duplicates ' . self::ORGANIZATION_OPTION . '
        {--confidence=exact : Welche Stufe automatisch gemergt wird (exact|likely|fuzzy)}
        {--apply : Tatsächlich zusammenführen (sonst nur Vorschau)}';

    protected $description = 'Findet doppelte Kunden und führt eindeutige Treffer (USt-IdNr./Lexoffice-Nr.) zusammen. Ohne --apply nur Vorschau.';

    protected function candidates(Organization $organization, string $confidence): Collection {
        return app(CustomerDuplicateFinder::class)->candidates($organization, $confidence);
    }

    protected function mergePair(Model $source, Model $target): void {
        app(CustomerMergeService::class)->merge($source, $target);
    }

    protected function appliedSummary(int $merged): string {
        return "{$merged} Kunde(n) zusammengeführt.";
    }
}
