<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SchedulerRegisterDocument.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Scheduling;

/**
 * Scheduler-Register als Markdown für `WorkDiary-Architecture/konventionen/scheduler-register.md`
 * (Vollscan 2026-11-02, gap-03): je Registry-Job Schlüssel, Befehl, Kadenz samt
 * Minutenversatz, Plugin-Bindung, Vorprüfung, Kritikalität, Laufzeit und
 * Hintergrundlauf. Ohne Container und ohne Datenbank gebaut (keine Overrides,
 * kein Betriebsfenster), damit `SchedulerDocParityTest` die Seite aus
 * `config/scheduler.php` und `lang/de/scheduler.php` nachrechnen kann; den
 * Chronik-Block am Seitenende pflegt das Wiki (`scripts/wiki-backlinks.php`).
 */
final class SchedulerRegisterDocument {
    public const string RELATIVE_PATH = '../WorkDiary-Architecture/konventionen/scheduler-register.md';

    private const string CHRONIK_START = '<!-- chronik:start -->';

    private const array WEEKDAYS = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

    private const array CRITICALITY = [
        'core' => 'Kernbetrieb',
        'integration' => 'Integration',
        'housekeeping' => 'Aufräumen',
    ];

    /**
     * @param  list<JobDefinition>  $definitions
     * @param  array<string, string>  $labels  Job-Key → deutscher Name (`scheduler.job.*`)
     */
    public function __construct(private readonly array $definitions, private readonly array $labels) {}

    /**
     * @param  array<string, array<string, mixed>>  $jobs  `config('scheduler.jobs')`
     * @param  array<string, mixed>  $jobLabels  `scheduler.job` der deutschen Sprachdatei (geschachtelt)
     */
    public static function fromConfig(array $jobs, array $jobLabels): self {
        $definitions = [];
        foreach ($jobs as $key => $data) {
            $definitions[] = JobDefinition::fromArray((string) $key, $data);
        }

        return new self($definitions, self::flattenLabels($jobLabels));
    }

    /** Generatorteil einer Registerseite — alles vor dem Chronik-Block, auf eine Endzeile normiert. */
    public static function generatedPart(string $page): string {
        $pos = strpos($page, self::CHRONIK_START);

        return rtrim($pos === false ? $page : substr($page, 0, $pos)) . "\n";
    }

    /** Chronik-Block einer bestehenden Seite samt trennender Leerzeile; leer ohne Block. */
    public static function chronikPart(string $page): string {
        $pos = strpos($page, self::CHRONIK_START);

        return $pos === false ? '' : "\n" . substr($page, $pos);
    }

    public function render(): string {
        $definitions = $this->definitions;
        usort($definitions, static fn (JobDefinition $a, JobDefinition $b): int => strcmp($a->key, $b->key));

        $byCriticality = ['core' => 0, 'integration' => 0, 'housekeeping' => 0];
        $plugin = $guarded = $background = 0;
        foreach ($definitions as $definition) {
            $byCriticality[$definition->criticality->value]++;
            $plugin += $definition->plugin === null ? 0 : 1;
            $guarded += $definition->guard === null ? 0 : 1;
            $background += $definition->runInBackground ? 1 : 0;
        }

        $lines = [
            '# Scheduler-Register',
            '',
            '> Erzeugt von `php artisan scheduler:doc` aus `config/scheduler.php` und',
            '> den deutschen Job-Namen (Vollscan 2026-11-02, gap-03) — nicht von Hand',
            '> bearbeiten; `SchedulerDocParityTest` meldet eine veraltete Fassung.',
            '',
            sprintf(
                '%d Jobs: %d Kernbetrieb, %d Integration, %d Aufräumen; %d plugin-gebunden, %d mit Vorprüfung, %d im Hintergrund.',
                count($definitions),
                $byCriticality['core'],
                $byCriticality['integration'],
                $byCriticality['housekeeping'],
                $plugin,
                $guarded,
                $background,
            ),
            '',
            'Die Kadenz ist der Standardplan samt Minutenversatz (`JobOffset`, aus dem',
            'Job-Key abgeleitet, damit Adminseite, Wächter und Registrar dieselbe',
            'Fälligkeit sehen). Betreiber planen über System → Geplante Aufgaben um oder',
            'pausieren; ein Betriebsfenster verschiebt Tageszeit-Jobs an den Fensterbeginn',
            '([server-cron-queue.md](../betrieb/server-cron-queue.md)). Plugin-gebundene',
            'Jobs werden ohne irgendwo aktives Plugin nicht angemeldet',
            '(`scheduler.skip_inactive_plugins`), eine Vorprüfung (`JobGuard`) lässt den',
            'Job nur laufen, wenn es etwas zu tun gibt; ein übersprungener Slot gilt dem',
            'Wächter als erfüllt. Hintergrund = eigener Prozess, der den minütlichen',
            'Tick nicht verzögert. Laufzeit = erwartete Minuten; der Wächter schließt',
            'einen Lauf nach dem Sechsfachen als fehlgeschlagen.',
            '',
            '## Jobs',
            '',
            '| Schlüssel | Aufgabe | Befehl | Kadenz | Plugin | Vorprüfung | Kritikalität | Laufzeit | Hintergrund |',
            '| --- | --- | --- | --- | --- | --- | --- | --- | --- |',
        ];
        foreach ($definitions as $definition) {
            $lines[] = sprintf(
                '| `%s` | %s | `%s` | %s | %s | %s | %s | %d min | %s |',
                $definition->key,
                $this->cell($this->labels[$definition->key] ?? $definition->key),
                $this->cell($definition->command),
                $this->cadence($definition),
                $definition->plugin === null ? '—' : '`' . $definition->plugin . '`',
                $definition->guard === null ? '—' : '`' . $this->shortName($definition->guard) . '`',
                self::CRITICALITY[$definition->criticality->value],
                $definition->expectedRuntimeMinutes,
                $definition->runInBackground ? 'ja' : '—',
            );
        }

        array_push($lines, '', '## Belegung der Stunde', '', 'Takt-Jobs je Startminute (Stunden-, Halbstunden-, Viertelstunden- und Fünf-Minuten-Takt); Minuten- und Tagesjobs stehen nicht hier.', '', '| Minute | Jobs |', '| --- | --- |');
        $perMinute = [];
        foreach ($definitions as $definition) {
            $cadence = $this->staggered($definition);
            $interval = $cadence->type->intervalMinutes();
            if ($interval <= 1) {
                continue;
            }
            foreach ($this->minutes($cadence) as $minute) {
                $perMinute[$minute][] = $definition->key;
            }
        }
        ksort($perMinute);
        foreach ($perMinute as $minute => $keys) {
            $lines[] = sprintf('| %02d | %s |', $minute, implode(', ', array_map(static fn (string $key): string => '`' . $key . '`', $keys)));
        }

        return implode("\n", $lines) . "\n";
    }

    /** Standardkadenz mit Versatz, wie der Registrar sie ohne Override und Betriebsfenster anmeldet. */
    private function staggered(JobDefinition $definition): Cadence {
        $cadence = $definition->defaultCadence;
        if (! $definition->stagger) {
            return $cadence;
        }
        $offset = JobOffset::minutes($definition->key, $cadence->type);

        return $offset === 0 ? $cadence : $cadence->withOffset($offset);
    }

    /**
     * Startminuten eines Takt-Jobs innerhalb der Stunde; leer für Minuten-, Tages- und Cron-Kadenzen.
     *
     * @return list<int>
     */
    private function minutes(Cadence $cadence): array {
        $interval = $cadence->type->intervalMinutes();
        if ($interval <= 1) {
            return [];
        }
        $minutes = [];
        for ($minute = $cadence->offset; $minute < 60; $minute += $interval) {
            $minutes[] = $minute;
        }

        return $minutes;
    }

    private function cadence(JobDefinition $definition): string {
        $cadence = $this->staggered($definition);
        $minutes = implode(', ', $this->minutes($cadence));
        $text = match ($cadence->type) {
            CadenceType::EveryMinute => 'jede Minute',
            CadenceType::EveryFiveMinutes => 'alle 5 Minuten' . ($cadence->offset === 0 ? '' : ' ab Minute ' . $cadence->offset),
            CadenceType::EveryFifteenMinutes => 'alle 15 Minuten (Minute ' . $minutes . ')',
            CadenceType::EveryThirtyMinutes => 'alle 30 Minuten (Minute ' . $minutes . ')',
            CadenceType::Hourly => 'stündlich (Minute ' . $cadence->offset . ')',
            CadenceType::DailyAt => 'täglich ' . $cadence->time,
            CadenceType::WeeklyOn => 'wöchentlich ' . self::WEEKDAYS[((int) $cadence->day) % 7] . ' ' . $cadence->time,
            CadenceType::MonthlyOn => 'monatlich am ' . $cadence->day . '. um ' . $cadence->time,
            CadenceType::Cron => 'Cron',
        };
        $notes = [];
        if ($definition->cadenceSettingKey !== null) {
            $notes[] = 'Uhrzeit aus Einstellung `' . $definition->cadenceSettingKey . '`';
        }
        if (! $definition->stagger && $cadence->type->intervalMinutes() > 1) {
            $notes[] = 'ohne Versatz';
        }
        if (! $definition->runsInMaintenance) {
            $notes[] = 'pausiert im Wartungsfenster';
        }
        if ($definition->perOrganization) {
            $notes[] = 'je Organisation';
        }

        return $text . ' `' . $cadence->cronExpression() . '`' . ($notes === [] ? '' : ' — ' . implode(', ', $notes));
    }

    /**
     * `scheduler.job` ist nach Punkten geschachtelt (`['lexoffice' => ['sync_vouchers' => …]]`).
     *
     * @param  array<string, mixed>  $labels
     * @return array<string, string>
     */
    private static function flattenLabels(array $labels, string $prefix = ''): array {
        $flat = [];
        foreach ($labels as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $flat += self::flattenLabels($value, $path);
            } elseif (is_string($value)) {
                $flat[$path] = $value;
            }
        }

        return $flat;
    }

    private function shortName(string $class): string {
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }

    private function cell(string $text): string {
        return str_replace('|', '\|', $text);
    }
}
