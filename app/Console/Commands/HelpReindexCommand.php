<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpReindexCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Services\Help\HelpTopicReindexer;
use Illuminate\Console\Command;

class HelpReindexCommand extends Command {
    protected $signature = 'help:reindex';

    protected $description = 'Liest alle Hilfe-Topics aus resources/help/{locale}/ ein und synchronisiert sie in help_topics.';

    public function handle(HelpTopicReindexer $reindexer): int {
        $result = $reindexer->reindex();

        $this->info(sprintf(
            '%d Hilfe-Topics aktualisiert, %d entfernt.',
            $result['upserted'],
            $result['deleted']
        ));

        return self::SUCCESS;
    }
}
