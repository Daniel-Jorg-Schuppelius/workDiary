<?php
/*
 * Created on   : Tue Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectMergeDuplicatesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\{ProjectDuplicateFinder, ProjectMergeService};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Listet Projekt-Dubletten und führt Treffer auf Wunsch automatisch zusammen
 * (z. B. mehrfach angelegte „Wartung"-Projekte nach dem Toggl-Import). Standard
 * ist ein Dry-Run; erst `--apply` schreibt. Default-Stufe ist „likely" (gleicher
 * Kunde + identischer Name); unscharfe Treffer (fuzzy) bleiben dem manuellen
 * Abgleich vorbehalten, sofern nicht ausdrücklich gewählt.
 *
 * @extends MergeDuplicatesCommand<\App\Models\Project>
 */
class ProjectMergeDuplicatesCommand extends MergeDuplicatesCommand {
    protected $signature = 'project:merge-duplicates ' . self::ORGANIZATION_OPTION . '
        {--confidence=likely : Welche Stufe automatisch gemergt wird (exact|likely|fuzzy)}
        {--apply : Tatsächlich zusammenführen (sonst nur Vorschau)}';

    protected $description = 'Findet doppelte Projekte (gleicher Kunde + Name) und führt sie zusammen. Ohne --apply nur Vorschau.';

    protected function candidates(Organization $organization, string $confidence): Collection {
        return app(ProjectDuplicateFinder::class)->candidates($organization, $confidence);
    }

    protected function mergePair(Model $source, Model $target): void {
        app(ProjectMergeService::class)->merge($source, $target);
    }

    protected function appliedSummary(int $merged): string {
        return "{$merged} Projekt(e) zusammengeführt.";
    }
}
