<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SchedulerDocCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Scheduling;

use App\Scheduling\SchedulerRegisterDocument;
use CommonToolkit\Helper\FileSystem\{File, Folder};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Lang;

/** Schreibt das Scheduler-Register ins Architektur-Repo (Vollscan 2026-11-02, gap-03; Muster `modules:doc`). */
class SchedulerDocCommand extends Command {
    protected $signature = 'scheduler:doc {--path= : Zieldatei (Standard: ../WorkDiary-Architecture/konventionen/scheduler-register.md)}';

    protected $description = 'Erzeugt ../WorkDiary-Architecture/konventionen/scheduler-register.md aus der Scheduler-Registry.';

    public function handle(): int {
        $option = $this->option('path');
        $path = is_string($option) && $option !== '' ? $option : base_path(SchedulerRegisterDocument::RELATIVE_PATH);
        if (! Folder::exists(dirname($path))) {
            $this->warn('Zielordner nicht vorhanden — Scheduler-Register nicht geschrieben: ' . dirname($path));

            return self::SUCCESS;
        }
        /** @var array<string, array<string, mixed>> $jobs */
        $jobs = (array) config('scheduler.jobs', []);
        // Deutsche Job-Namen unabhängig von der Anwendungssprache — das Wiki ist deutsch.
        $labels = Lang::get('scheduler.job', [], 'de');
        $document = SchedulerRegisterDocument::fromConfig($jobs, is_array($labels) ? $labels : []);
        $chronik = File::exists($path) ? SchedulerRegisterDocument::chronikPart(File::read($path)) : '';
        File::write($path, $document->render() . $chronik);
        $this->info('Scheduler-Register geschrieben: ' . $path);

        return self::SUCCESS;
    }
}
