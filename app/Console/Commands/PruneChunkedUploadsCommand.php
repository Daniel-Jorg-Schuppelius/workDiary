<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PruneChunkedUploadsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Attachments\ChunkedUploadStore;
use Illuminate\Console\Command;

/** Teile abgebrochener Uploads entfernen (MVP-1265), nach einem Tag ohne Abschluss. */
class PruneChunkedUploadsCommand extends Command {
    protected $signature = 'uploads:prune-chunks';

    protected $description = 'Entfernt liegengebliebene Teil-Uploads.';

    public function handle(ChunkedUploadStore $uploads): int {
        $this->line($uploads->prune() . ' liegengebliebene Uploads entfernt.');

        return self::SUCCESS;
    }
}
