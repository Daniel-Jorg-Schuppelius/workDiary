<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoicesPruneLegacyPageImagesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Invoicing;

use App\Services\Invoicing\Recognition\PageImages;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Rechnungseingang (Feature 208, M5): Die Seitenbilder des Zonen-Editors
 * liegen je Organisation unter `recognition/<organisation>/<sha256>`. Die
 * Ordner der alten Ablage `recognition/<sha256>` räumt nichts mehr — weder das
 * Verwerfen eines Belegs noch die Löschung der Organisation. Einmalig nach dem
 * Update ausführen (zuerst mit `--dry-run`); die Bilder entstehen bei Bedarf
 * neu, Belege und Vorlagen bleiben unberührt.
 */
class IncomingInvoicesPruneLegacyPageImagesCommand extends Command {
    protected $signature = 'incoming-invoices:prune-legacy-page-images {--dry-run : Nur auflisten, nichts löschen}';

    protected $description = 'Entfernt Seitenbilder der Rechnungserkennung aus der alten Ablage ohne Organisation.';

    public function handle(): int {
        $dryRun = (bool) $this->option('dry-run');
        $directories = PageImages::legacyDirectories();
        $disk = Storage::disk('local');
        foreach ($directories as $directory) {
            if ($dryRun) {
                $this->line($directory);
            } else {
                $disk->deleteDirectory($directory);
            }
        }

        $this->info($dryRun
            ? '[dry-run] ' . count($directories) . ' Ordner der alten Ablage gefunden, nichts gelöscht.'
            : count($directories) . ' Ordner der alten Ablage entfernt.');

        return self::SUCCESS;
    }
}
