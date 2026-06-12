<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SbomGenerateCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Isms;

use App\Services\Isms\SbomGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Release-SBOM erzeugen (Feature 044, Ebene 2): CycloneDX-1.5-JSON aus
 * composer.lock, package-lock.json, Laufzeitumgebung, Modulen und Plugins
 * — siehe docs/release-prozess.md. Standard-Ablage:
 * storage/app/sbom/workdiary-{version}-{Ymd-His}.cdx.json plus stabiler
 * Alias workdiary-latest.cdx.json; der SHA-256 der Datei wird ausgegeben
 * (Release-Notes/Prüfsumme).
 */
class SbomGenerateCommand extends Command {
    protected $signature = 'sbom:generate
        {--output= : Datei-Pfad statt storage/app/sbom (Verzeichnis wird angelegt)}
        {--print : SBOM nach stdout ausgeben statt in eine Datei zu schreiben}';

    protected $description = 'Erzeugt die Release-SBOM (CycloneDX 1.5 JSON) aus composer.lock, package-lock.json, Modulen und Plugins.';

    public function handle(SbomGenerator $generator): int {
        $json = $generator->toJson();
        $hash = hash('sha256', $json);

        if ((bool) $this->option('print')) {
            $this->line($json);
            $this->info('SHA-256: ' . $hash);

            return self::SUCCESS;
        }

        $output = $this->option('output');
        if (is_string($output) && $output !== '') {
            $dir = dirname($output);
            if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
                $this->error('Verzeichnis konnte nicht angelegt werden: ' . $dir);

                return self::FAILURE;
            }
            file_put_contents($output, $json);
            $this->info('SBOM geschrieben: ' . $output);
            $this->info('SHA-256: ' . $hash);

            return self::SUCCESS;
        }

        $name = $generator->fileName();
        Storage::disk('local')->put('sbom/' . $name, $json);
        // Stabiler Alias für Admin-Download/Monitoring.
        Storage::disk('local')->put('sbom/' . SbomGenerator::latestAlias(), $json);

        $this->info('SBOM geschrieben: ' . Storage::disk('local')->path('sbom/' . $name));
        $this->info('Alias aktualisiert: ' . Storage::disk('local')->path('sbom/' . SbomGenerator::latestAlias()));
        $this->info('SHA-256: ' . $hash);

        return self::SUCCESS;
    }
}
