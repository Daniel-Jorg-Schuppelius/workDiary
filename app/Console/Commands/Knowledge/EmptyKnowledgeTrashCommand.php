<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EmptyKnowledgeTrashCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Knowledge;

use App\Models\Knowledge\KnowledgePage;
use App\Modules\Attributes\RunsWhenModuleInactive;
use App\Services\Knowledge\Pages\KnowledgePageService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Papierkorb der Wissensseiten leeren (Feature 191, MVP-1664): Seiten, die
 * länger als die Frist im Papierkorb liegen, werden endgültig gelöscht — mit
 * Fassungen, Bestätigungen, Bildern und Verweisen.
 */
#[RunsWhenModuleInactive('Löschung: der Papierkorb der Wissensseiten hat eine Frist, die auch bei abgeschaltetem Modul gilt.')]
class EmptyKnowledgeTrashCommand extends Command {
    protected $signature = 'knowledge-pages:empty-trash
        {--days= : Frist in Tagen (Vorgabe: KnowledgePage::TRASH_DAYS)}
        {--dry-run : Nur zählen, nichts löschen}';

    protected $description = 'Wissensseiten nach Ablauf der Frist endgültig aus dem Papierkorb löschen';

    public function handle(KnowledgePageService $pages): int {
        $days = $this->option('days') !== null ? (int) $this->option('days') : KnowledgePage::TRASH_DAYS;
        if ($days < 1) {
            $this->error('--days muss mindestens 1 sein.');

            return self::FAILURE;
        }
        $cutoff = CarbonImmutable::now()->subDays($days);
        if ((bool) $this->option('dry-run')) {
            $count = KnowledgePage::query()->withoutGlobalScopes()->onlyTrashed()->where('deleted_at', '<', $cutoff)->count();
            $this->info(sprintf('%d Wissensseiten älter als %d Tage im Papierkorb würden gelöscht (--dry-run).', $count, $days));

            return self::SUCCESS;
        }
        $purged = $pages->purgeTrashedBefore($cutoff);
        $this->info(sprintf('%d Wissensseiten endgültig gelöscht (Papierkorb älter als %d Tage).', $purged, $days));

        return self::SUCCESS;
    }
}
