<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NewsFeedRefreshCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\UI\SidebarNewsFeedService;
use Illuminate\Console\Command;
use Throwable;

/** Aktualisiert den rein lesenden Neuigkeiten-Cache der Hilfe-Rail. */
class NewsFeedRefreshCommand extends Command {
    protected $signature = 'news-feed:refresh';

    protected $description = 'Aktualisiert den RSS-/Atom-Feed der eingeklappten Hilfe-Sidebar';

    public function handle(SidebarNewsFeedService $feed): int {
        if (! $feed->isEnabled()) {
            $this->info('Neuigkeiten-Feed ist deaktiviert oder nicht konfiguriert.');

            return self::SUCCESS;
        }

        try {
            $count = $feed->refresh();
            $this->info("Neuigkeiten-Feed aktualisiert: {$count} Beitrag/Beiträge.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Neuigkeiten-Feed konnte nicht aktualisiert werden: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
