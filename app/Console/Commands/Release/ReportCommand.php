<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReportCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Release;

use App\Services\Release\ReleaseReportService;
use CommonToolkit\Helper\FileSystem\{File, Folder};
use Illuminate\Console\Command;

/** Sicherheits- und Datenschutzbericht je Release (MVP-946). */
class ReportCommand extends Command {
    protected $signature = 'release:report
        {--release= : Version statt config(app.version)}
        {--output= : Datei-Pfad statt storage/app/release/report-<version>.md}';

    protected $description = 'Erzeugt den Sicherheits- und Datenschutzbericht des Releases (Changelog, Datenschutz-Einträge, SBOM, Advisories, Integrität).';

    public function handle(ReleaseReportService $reports): int {
        $version = $this->option('release');
        $report = $reports->collect(is_string($version) && $version !== '' ? $version : null);
        $path = (string) ($this->option('output') ?: storage_path('app/release/report-' . preg_replace('/[^A-Za-z0-9._-]/', '_', $report['version']) . '.md'));
        Folder::create(dirname($path), 0o755, true);
        File::write($path, $reports->markdown($report));
        $this->info(sprintf('Bericht %s geschrieben: %d Einträge, %d datenschutzrelevant, %d offene Advisories.', $path, count($report['entries']), count($report['privacy']), count($report['advisories'])));

        return self::SUCCESS;
    }
}
