<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QueueConsumerRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate: **jede benannte Warteschlange braucht einen Verbraucher.**
 *
 * Der Anlass ist ein echter Befund (Feature 150, 2026-08-30): das
 * Video-Transcoding lief auf `onQueue('media')`, aber jeder dokumentierte
 * Worker-Aufruf — compose.yml, scripts/cron.sh, README — startete
 * `queue:work` ohne `--queue`. Damit arbeitet Laravel ausschließlich die
 * Warteschlange `default` ab: die Jobs lagen in der Tabelle und wurden nie
 * angefasst. Nichts schlug fehl, nichts wurde geloggt — die Videos blieben
 * einfach für immer „wird verarbeitet".
 *
 * Ein solcher Fehler ist von außen unsichtbar und von innen leise. Deshalb
 * prüft dieses Gate die Betriebsartefakte statt des Anwendungscodes.
 */
class QueueConsumerRuleTest extends TestCase {
    use ScansSourceTree;

    /** Wird von jedem `queue:work` ohne `--queue` bedient. */
    private const ALWAYS_CONSUMED = 'default';

    /** Betriebsartefakte, die einen Worker starten. */
    private const WORKER_FILES = [
        'compose.yml',
        'scripts/cron.sh',
        'README.md',
    ];

    public function test_jede_benannte_queue_hat_einen_worker(): void {
        $queues = $this->declaredQueues();

        $this->assertNotSame([], $queues, 'Kein onQueue()-Aufruf gefunden — Scanner prüfen.');

        $missing = [];

        foreach ($queues as $queue => $sources) {
            foreach (self::WORKER_FILES as $file) {
                $content = (string) file_get_contents($this->repoRoot() . '/' . $file);

                if (! str_contains($content, '--queue=' . $queue)) {
                    $missing[] = sprintf('%s (aus %s) fehlt in %s', $queue, implode(', ', $sources), $file);
                }
            }
        }

        $this->assertSame([], $missing, "Warteschlangen ohne Verbraucher — die Jobs bleiben liegen:\n"
            . implode("\n", $missing)
            . "\n\nEntweder einen Worker mit --queue=… ergänzen oder den Job auf die Standard-Warteschlange legen.");
    }

    /**
     * Ein eigener Job-Timeout über `retry_after` der Verbindung stellt den
     * Job ein zweites Mal zu, während der erste noch rechnet.
     */
    public function test_medien_verbindung_haelt_laenger_als_der_laengste_job(): void {
        $config = (string) file_get_contents($this->repoRoot() . '/config/queue.php');

        $this->assertMatchesRegularExpression(
            "/'media' => \[/",
            $config,
            'Die Medien-Jobs laufen bis zu einer Stunde; dafür braucht es eine eigene Verbindung.'
        );

        preg_match("/'media' => \[.*?'retry_after' => \(int\) env\('MEDIA_QUEUE_RETRY_AFTER', (\d+)\)/s", $config, $m);
        $this->assertNotEmpty($m, "config/queue.php: 'media' braucht ein retry_after.");

        $service = (string) file_get_contents($this->repoRoot() . '/app/Services/Media/VideoTranscodingService.php');
        preg_match('/MAX_DURATION_SECONDS = (\d+)/', $service, $d);
        $this->assertNotEmpty($d);

        $this->assertGreaterThan(
            (int) $d[1],
            (int) $m[1],
            'retry_after muss über der maximalen Videolänge liegen, sonst laufen zwei ffmpeg-Prozesse in dieselbe Datei.'
        );
    }

    /**
     * @return array<string, list<string>> Queue-Name → Dateien, die darauf legen
     */
    private function declaredQueues(): array {
        $out = [];

        foreach ($this->phpFiles('app') as $file) {
            $source = $this->stripComments((string) file_get_contents($file));

            if (preg_match_all("/onQueue\(\s*'([a-z0-9_-]+)'/i", $source, $found) === 0) {
                continue;
            }

            foreach ($found[1] as $queue) {
                if ($queue === self::ALWAYS_CONSUMED) {
                    continue;
                }

                $out[$queue][] = $this->relativePath($file);
            }
        }

        foreach ($out as $queue => $files) {
            $out[$queue] = array_values(array_unique($files));
        }

        ksort($out);

        return $out;
    }
}
