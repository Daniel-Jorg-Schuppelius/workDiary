<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VerifyCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Release;

use App\Services\Release\{ReleaseManifestService, ReleaseVerifier};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Verifiziert ein Release-Manifest (Feature 022): prüft die hinterlegten
 * SHA-256-Prüfsummen gegen die aktuellen Artefakte und — falls signiert —
 * die Ed25519-Signatur gegen den (versiegelten/konfigurierten) Public Key.
 * Exit-Code 0 = gültig, 1 = manipuliert/abweichend. Geeignet für
 * Update-Skripte und Monitoring. Siehe ../WorkDiary-Architecture/release-prozess.md.
 */
class VerifyCommand extends Command {
    protected $signature = 'release:verify
        {path? : Pfad zu release.json (Default: storage/app/release/release.json)}';

    protected $description = 'Verifiziert ein Release-Manifest (Prüfsummen + Ed25519-Signatur).';

    public function handle(ReleaseVerifier $verifier): int {
        $json = $this->loadManifestJson();
        if ($json === null) {
            return self::FAILURE;
        }

        $manifest = json_decode($json, true);
        if (! is_array($manifest)) {
            $this->error('Manifest ist kein gültiges JSON-Objekt.');

            return self::FAILURE;
        }

        $result = $verifier->verify($manifest);

        $this->line(sprintf('Geprüfte Artefakte: %d', $result->checkedArtifacts));
        $this->line(sprintf(
            'Signatur: %s',
            ! $result->signed ? 'keine' : ($result->signatureValid === true ? 'gültig' : 'UNGÜLTIG'),
        ));

        if ($result->issues !== []) {
            $this->error(sprintf('%d Problem(e) gefunden:', count($result->issues)));
            foreach ($result->issues as $issue) {
                $this->line('  • ' . $issue);
            }
        }

        if ($result->valid) {
            $this->info('Release-Manifest gültig.');

            return self::SUCCESS;
        }

        $this->error('Release-Manifest UNGÜLTIG — Integrität verletzt.');

        return self::FAILURE;
    }

    private function loadManifestJson(): ?string {
        $path = $this->argument('path');
        if (is_string($path) && $path !== '') {
            if (! is_file($path) || ! is_readable($path)) {
                $this->error('Manifest nicht gefunden oder nicht lesbar: ' . $path);

                return null;
            }
            $contents = file_get_contents($path);

            return $contents === false ? null : $contents;
        }

        if (! Storage::disk('local')->exists(ReleaseManifestService::STORAGE_PATH)) {
            $this->error('Kein Manifest gefunden — zuerst `php artisan release:manifest` ausführen.');

            return null;
        }

        return (string) Storage::disk('local')->get(ReleaseManifestService::STORAGE_PATH);
    }
}
