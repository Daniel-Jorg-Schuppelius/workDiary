<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PruneResaleImportsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Reselling;

use App\Models\Reselling\ResaleImport;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Aufbewahrung der abgelegten Import-Dateien (Feature 152, Review
 * 2026-09-10 A8): Anbieter-Exporte tragen Endkunden-PII und werden nach dem
 * Import nicht mehr gelesen. Nach der Frist verschwindet die Datei, der
 * Import-Datensatz mit Zählern und Befunden bleibt als Historie.
 */
class PruneResaleImportsCommand extends Command {
    protected $signature = 'resale:prune-imports
        {--days=90 : Dateien älter als N Tage löschen}
        {--dry-run : Nur zählen, nichts löschen}';

    protected $description = 'Abgelegte Import-Dateien des Reselling-Registers nach der Aufbewahrungsfrist löschen';

    public function handle(): int {
        $days = (int) $this->option('days');
        if ($days < 1) {
            $this->error('--days muss mindestens 1 sein.');

            return self::FAILURE;
        }
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = CarbonImmutable::now()->subDays($days); // created_at liegt in UTC

        $query = ResaleImport::query()->withoutGlobalScopes()
            ->whereNotNull('file_path')
            ->where('created_at', '<', $cutoff->format('Y-m-d H:i:s'))
            ->orderBy('id');
        $candidates = (clone $query)->count();
        if ($dryRun) {
            $this->info(sprintf('%d Import-Dateien älter als %d Tage (Stichtag %s) würden gelöscht (--dry-run).', $candidates, $days, $cutoff->toDateString()));

            return self::SUCCESS;
        }

        $deleted = 0;
        $missing = 0;
        $query->chunkById(100, function ($imports) use (&$deleted, &$missing): void {
            foreach ($imports as $import) {
                if ($import->deleteFile()) {
                    $deleted++;
                } else {
                    $missing++; // Pfad ohne Datei: nur bereinigt
                }
            }
        });
        $this->info(sprintf('%d Import-Dateien gelöscht, %d Pfade ohne Datei bereinigt (älter als %d Tage, Stichtag %s).', $deleted, $missing, $days, $cutoff->toDateString()));

        return self::SUCCESS;
    }
}
