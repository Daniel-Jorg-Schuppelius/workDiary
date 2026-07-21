<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WatchCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Integrity;

use App\Enums\Security\IntegrityCheckStatus;
use App\Services\Release\CodeIntegrityService;
use Illuminate\Console\Command;

/**
 * Realtime-Integritätswächter (Feature 097, MVP-453): überwacht den
 * dateiweisen Scan-Scope aus {@see CodeIntegrityService::watchableDirectories()}
 * per ext-inotify und löst nach einem Debounce-Fenster genau EINEN
 * `integrity:verify`-Lauf aus — der Wächter bewertet NICHT selbst
 * ({@see CodeIntegrityService::runVerification()} bleibt die einzige
 * Bewertungslogik). Ohne ext-inotify bricht der Befehl mit klarem Hinweis ab;
 * der periodische Scan läuft unverändert weiter.
 *
 * Bewusste Grenzen: vendor/ wird NICHT realtime überwacht (der ausgelöste
 * Verify prüft es dennoch; reine vendor-Drift deckt der periodische Lauf ab).
 * OS-Werkzeuge (AIDE/auditd, read-only Mounts) bleiben die erste Empfehlung —
 * dieser Wächter ist für Umgebungen, in denen der Betreiber nur die App
 * kontrolliert.
 */
class WatchCommand extends Command {
    protected $signature = 'integrity:watch
        {--debounce= : Sammelfenster in Sekunden (Default: config integrity.watch.debounce_seconds)}
        {--once : Nach dem ersten ausgelösten Verify beenden (Diagnose/Tests)}';

    protected $description = 'Überwacht den Quelltext per inotify und löst bei Änderungen integrity:verify aus (Feature 095/097).';

    /**
     * Watch-Deskriptor → absoluter Verzeichnispfad.
     *
     * @var array<int, string>
     */
    private array $watches = [];

    /** inotify-Event-Maske für integritätsrelevante Änderungen. */
    private const MASK = \IN_CLOSE_WRITE | \IN_CREATE | \IN_DELETE | \IN_MOVED_FROM | \IN_MOVED_TO | \IN_MOVE_SELF | \IN_DELETE_SELF;

    public function handle(CodeIntegrityService $service): int {
        if (! $this->hasInotify()) {
            $this->error('Die PHP-Erweiterung ext-inotify ist nicht geladen — der Wächter kann nicht starten.');
            $this->line(sprintf('Installation (Ubuntu/Debian): sudo apt install php%d.%d-inotify', PHP_MAJOR_VERSION, PHP_MINOR_VERSION));
            $this->line('Der periodische Scan (integrity:verify) läuft ohne die Erweiterung unverändert weiter.');

            return self::FAILURE;
        }

        if ($service->load() === null) {
            $this->warn('Keine Baseline vorhanden — zuerst `release:manifest` (Herausgeber) oder `integrity:freeze` (lokal) ausführen.');
            $this->line('Der Wächter startet trotzdem; ausgelöste Verify-Läufe melden dann "keine Baseline".');
        }

        $debounce = max(1, (int) ($this->option('debounce') ?? config('integrity.watch.debounce_seconds', 30)));
        $base = $service->basePath();
        $rootNames = $service->rootWatchNames();

        $fd = inotify_init();
        stream_set_blocking($fd, false);

        foreach ($service->watchableDirectories() as $dir) {
            $this->addWatch($fd, $dir);
        }
        $this->info(sprintf(
            'integrity:watch aktiv — %d Verzeichnisse, Debounce %d s. (Strg+C oder SIGTERM zum Beenden)',
            count($this->watches),
            $debounce,
        ));

        $running = true;
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            $stop = function () use (&$running): void { $running = false; };
            pcntl_signal(SIGTERM, $stop);
            pcntl_signal(SIGINT, $stop);
        }

        $dirty = false;
        $lastEvent = 0.0;

        while ($running) {
            $read = [$fd];
            $write = $except = [];
            // 1-s-Timeout: Debounce-Ablauf und Signale regelmäßig prüfen, auch
            // wenn keine Events anliegen.
            $ready = @stream_select($read, $write, $except, 1);
            if ($ready === false) {
                continue; // vom Signal unterbrochen (EINTR)
            }

            if ($ready > 0) {
                $events = inotify_read($fd);
                if ($events !== false) {
                    foreach ($events as $event) {
                        if ($this->isRelevant($event, $base, $rootNames)) {
                            $dirty = true;
                            $lastEvent = microtime(true);
                        }
                        $this->maintainWatches($fd, $event);
                    }
                }
            }

            if ($dirty && (microtime(true) - $lastEvent) >= $debounce) {
                $dirty = false;
                $this->triggerVerify($service);
                if ((bool) $this->option('once')) {
                    $running = false;
                }
            }
        }

        foreach (array_keys($this->watches) as $wd) {
            @inotify_rm_watch($fd, $wd);
        }
        fclose($fd);
        $this->info('integrity:watch beendet.');

        return self::SUCCESS;
    }

    /** Ext-inotify-Präsenz — als Seam für den „nicht geladen"-Test überschreibbar. */
    protected function hasInotify(): bool {
        return extension_loaded('inotify');
    }

    /** @param  resource  $fd */
    private function addWatch($fd, string $dir): void {
        $wd = @inotify_add_watch($fd, $dir, self::MASK);
        if ($wd !== false) {
            $this->watches[$wd] = $dir;
        }
    }

    /**
     * Entscheidet, ob ein Event einen Verify rechtfertigt. Am Scan-Wurzel-
     * Verzeichnis nur die konfigurierten root_files/Scope-Namen (filtert
     * .env/.git & Co. heraus); in Scope-Unterverzeichnissen jede Änderung.
     *
     * @param  array{wd: int, mask: int, cookie: int, name: string}  $event
     * @param  list<string>  $rootNames
     */
    private function isRelevant(array $event, string $base, array $rootNames): bool {
        // Q_OVERFLOW: der Kernel-Puffer lief über, Events gingen verloren —
        // sicherheitshalber einen Verify erzwingen.
        if (($event['mask'] & \IN_Q_OVERFLOW) !== 0) {
            return true;
        }
        $dir = $this->watches[$event['wd']] ?? null;
        if ($dir === null) {
            return false;
        }
        if ($dir === $base) {
            return in_array($event['name'], $rootNames, true);
        }

        return true;
    }

    /**
     * Hält die Watch-Menge aktuell: neue Verzeichnisse (auch verschobene)
     * bekommen einen Watch (kaskadiert automatisch in die Tiefe); verwaiste
     * Watches (IN_IGNORED bei gelöschtem/verschobenem Verzeichnis) werden
     * aus der Map entfernt.
     *
     * @param  resource  $fd
     * @param  array{wd: int, mask: int, cookie: int, name: string}  $event
     */
    private function maintainWatches($fd, array $event): void {
        if (($event['mask'] & \IN_IGNORED) !== 0) {
            unset($this->watches[$event['wd']]);

            return;
        }

        $isNewDir = ($event['mask'] & \IN_ISDIR) !== 0 && ($event['mask'] & (\IN_CREATE | \IN_MOVED_TO)) !== 0;
        if (! $isNewDir) {
            return;
        }
        $parent = $this->watches[$event['wd']] ?? null;
        if ($parent === null) {
            return;
        }
        $newDir = $parent . DIRECTORY_SEPARATOR . $event['name'];
        if (is_dir($newDir) && ! is_link($newDir) && ! app(CodeIntegrityService::class)->isExcludedPath($newDir)) {
            $this->addWatch($fd, $newDir);
        }
    }

    private function triggerVerify(CodeIntegrityService $service): void {
        $check = $service->runVerification('watch');
        $time = now()->format('H:i:s');

        if ($check->status === IntegrityCheckStatus::Ok) {
            $this->info(sprintf('[%s] Verify: OK (%d Dateien geprüft).', $time, $check->files_checked));
        } elseif ($check->status === IntegrityCheckStatus::Deviation) {
            $this->warn(sprintf(
                '[%s] Verify: ABWEICHUNG — %d neu, %d geändert, %d gelöscht, %d Paket(e).',
                $time,
                $check->added_count,
                $check->modified_count,
                $check->deleted_count,
                $check->packages_changed_count,
            ));
        } else {
            $this->warn(sprintf('[%s] Verify: %s.', $time, $check->status->label()));
        }
    }
}
