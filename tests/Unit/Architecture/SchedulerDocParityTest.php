<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SchedulerDocParityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „Doku-Scheduler ⇒ Registry-Eintrag" (Vollscan 2026-08-23,
 * J1/J2): Die Betriebsdoku des Hinweisgebersystems versprach drei Läufe
 * (stündlich/täglich/5-min), registriert war keiner — HinSchG-Fristen und die
 * Quarantäne-Freigabe liefen nie automatisch.
 *
 * Regel: Jede Artisan-Signatur, die ein `*-betrieb.md` des Architecture-Repos
 * in seiner Scheduler-Tabelle führt, hat einen Eintrag in config/scheduler.php.
 * Zusätzlich gilt die Plugin-Zusicherung aus Feature 060 (Polling = verlässliche
 * Quelle) für zammad/github/gitlab:sync. Fehlt das Schwester-Repo (CI, frischer
 * Klon), prüft der Test nur die feste Liste.
 */
class SchedulerDocParityTest extends TestCase {
    use ScansSourceTree;

    /** @var list<string> Signaturen, die laut Feature-Doku geplant sein müssen */
    private const DOCUMENTED_COMMANDS = [
        'whistleblowing:deadlines',
        'whistleblowing:retention-review',
        'whistleblowing:scan',
        'zammad:sync',
        'github:sync',
        'gitlab:sync',
    ];

    public function test_documented_scheduler_commands_are_registered(): void {
        $registry = require $this->repoRoot() . '/config/scheduler.php';
        $registered = [];
        foreach ($registry['jobs'] as $job) {
            $registered[] = strtok((string) $job['command'], ' ');
        }

        $expected = self::DOCUMENTED_COMMANDS;
        foreach ($this->operationsDocs() as $doc) {
            $content = (string) file_get_contents($doc);
            // Tabellenzeilen `| \`cmd:sub\` | Takt | …` innerhalb eines Scheduler-/Cron-Abschnitts.
            if (preg_match_all('/^\|\s*`([a-z][a-z0-9-]*:[a-z][a-z0-9:-]*)`\s*\|\s*([^|]+)\|/m', $content, $rows, PREG_SET_ORDER) === 0) {
                continue;
            }
            foreach ($rows as $row) {
                if (preg_match('/stündlich|täglich|minütlich|wöchentlich|\d+-min|hourly|daily/i', $row[2]) === 1) {
                    $expected[] = $row[1];
                }
            }
        }

        $missing = array_values(array_diff(array_unique($expected), $registered));

        $this->assertSame([], $missing, "In der Betriebs-/Feature-Doku als Scheduler-Lauf geführt, aber nicht in config/scheduler.php registriert:\n"
            . implode("\n", $missing) . "\n\nRegistry-Eintrag ergänzen (Plugin-Jobs mit 'plugin' => '<id>') und SchedulerRegistrationTest nachziehen.");
    }

    /** @return list<string> */
    private function operationsDocs(): array {
        $architecture = realpath($this->repoRoot() . '/../WorkDiary-Architecture');
        if ($architecture === false) {
            return [];
        }

        $docs = glob($architecture . '/*-betrieb.md') ?: [];
        sort($docs);

        return $docs;
    }
}
