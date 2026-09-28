<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpSiteCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Help\HelpSiteExporter;
use Illuminate\Console\Command;

/** MVP-971: statische Doku-Website aus den Hilfethemen ohne Zielgruppe. */
class HelpSiteCommand extends Command {
    protected $signature = 'help:site
        {--output= : Zielordner (Standard: storage/app/help-site)}
        {--locale=* : Nur diese Sprachen (Standard: alle)}';

    protected $description = 'Erzeugt die öffentliche Doku-Website als statische Seiten aus resources/help.';

    public function handle(HelpSiteExporter $exporter): int {
        $output = (string) ($this->option('output') ?: storage_path('app/help-site'));
        /** @var list<string> $locales */
        $locales = array_values(array_filter((array) $this->option('locale'), 'is_string'));

        $result = $exporter->export(rtrim($output, '/'), $locales);
        $this->info(sprintf('%d Sprachen, %d Seiten, %d Bilder nach %s geschrieben.', $result['locales'], $result['pages'], $result['media'], $output));

        return self::SUCCESS;
    }
}
