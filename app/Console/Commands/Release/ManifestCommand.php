<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManifestCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Release;

use App\Services\Release\ReleaseManifestService;
use CommonToolkit\Helper\Data\JsonHelper;
use CommonToolkit\Helper\FileSystem\{File, Folder};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Erzeugt das signierte/integritätsgesicherte Release-Manifest (Feature 022):
 * release.json mit App-/Build-Version, Laufzeitversionen, Modulen + Plugins
 * und SHA-256-Prüfsummen relevanter Artefakte (SBOM, composer.lock,
 * package-lock.json). Signiert mit Ed25519 (Lizenz-Private-Key), falls
 * vorhanden — sonst unsigniert (Prüfsummen-Integrität bleibt). Siehe
 * ../WorkDiary-Architecture/release-prozess.md.
 */
class ManifestCommand extends Command {
    protected $signature = 'release:manifest
        {--output= : Datei-Pfad statt storage/app/release/release.json (Verzeichnis wird angelegt)}
        {--print : Manifest nach stdout ausgeben statt in eine Datei zu schreiben}';

    protected $description = 'Erzeugt das Release-Manifest (release.json) mit Versionen, Prüfsummen und optionaler Ed25519-Signatur.';

    public function handle(ReleaseManifestService $service): int {
        $manifest = $service->build();
        $json = JsonHelper::encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $signed = ($manifest['signature']['signed'] ?? false) === true;
        $signNote = $signed
            ? 'signiert (Ed25519)'
            : 'unsigniert (kein Private Key — nur Prüfsummen-Integrität)';

        if ((bool) $this->option('print')) {
            $this->line($json);
            $this->info('Manifest ' . $signNote . '.');

            return self::SUCCESS;
        }

        $output = $this->option('output');
        if (is_string($output) && $output !== '') {
            try {
                Folder::create(dirname($output), 0775, true);
                File::write($output, $json);
            } catch (\Throwable $e) {
                $this->error('Release-Manifest konnte nicht geschrieben werden: ' . $e->getMessage());

                return self::FAILURE;
            }
            $this->info('Release-Manifest geschrieben: ' . $output);
            $this->info('Manifest ' . $signNote . '.');

            return self::SUCCESS;
        }

        Storage::disk('local')->put(ReleaseManifestService::STORAGE_PATH, $json);
        $this->info('Release-Manifest geschrieben: ' . Storage::disk('local')->path(ReleaseManifestService::STORAGE_PATH));
        $this->info('Manifest ' . $signNote . '.');

        return self::SUCCESS;
    }
}
