<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningImportLearnDashCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Platform\Organization;
use App\Services\Learning\LearnDashImportService;
use App\Support\Sqid;
use CommonToolkit\Helper\Data\JsonHelper;
use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * LearnDash-Export in eine Organisation übernehmen (Feature 149, MVP-792).
 * `--dry-run` rechnet den Bericht, schreibt aber nichts.
 */
class LearningImportLearnDashCommand extends Command {
    protected $signature = 'learning:import-learndash {zip : Pfad zum LearnDash-Export-ZIP} {--org= : Organisation (ID oder Sqid)} {--dry-run : Nur Bericht, keine Änderung}';

    protected $description = 'Importiert Kurse, Prüfungen, Fragen und Abschlüsse aus einem LearnDash-Export als Entwürfe.';

    public function handle(LearnDashImportService $importer): int {
        $zip = (string) $this->argument('zip');
        if (! File::isFile($zip)) {
            $this->error('Datei nicht gefunden: ' . $zip);

            return self::FAILURE;
        }

        $orgOption = (string) $this->option('org');
        $organization = $orgOption !== ''
            ? Organization::query()->withoutGlobalScopes()->find(Sqid::decodeOrNumeric(Organization::class, $orgOption))
            : Organization::query()->withoutGlobalScopes()->orderBy('id')->first();
        if ($organization === null) {
            $this->error('Organisation nicht gefunden.');

            return self::FAILURE;
        }
        app()->instance('currentOrganization', $organization);

        try {
            $report = $importer->import($organization, $zip, null, (bool) $this->option('dry-run'));
        } catch (ValidationException $e) {
            $this->error(implode(' ', array_map(static fn (array $m): string => implode(' ', $m), $e->errors())));

            return self::FAILURE;
        }

        $this->line(JsonHelper::encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
