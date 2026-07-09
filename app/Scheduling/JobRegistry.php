<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Scheduling;

use App\Support\Setting;
use InvalidArgumentException;

/**
 * Allowlist-Registry aller planbaren Jobs (Feature 067, MVP-175).
 * Definitionen kommen als reine Arrays aus config/scheduler.php.
 *
 * effectiveCadence() liefert den Default-Plan inkl. Setting-gesteuerter
 * Uhrzeit (cadence_setting_key); DB-Overrides (MVP-176) werden vom
 * SchedulerRegistrar darübergelegt.
 */
class JobRegistry {
    /** @var array<string, JobDefinition>|null */
    private ?array $definitions = null;

    /** @return array<string, JobDefinition> */
    public function all(): array {
        if ($this->definitions === null) {
            $this->definitions = [];
            /** @var array<string, array<string, mixed>> $raw */
            $raw = (array) config('scheduler.jobs', []);
            foreach ($raw as $key => $data) {
                $this->definitions[$key] = JobDefinition::fromArray($key, $data);
            }
        }

        return $this->definitions;
    }

    public function has(string $key): bool {
        return array_key_exists($key, $this->all());
    }

    public function definition(string $key): JobDefinition {
        $definition = $this->all()[$key] ?? null;
        if ($definition === null) {
            throw new InvalidArgumentException("Scheduler-Job [{$key}] ist nicht registriert.");
        }

        return $definition;
    }

    /**
     * Job-Key zu einem formatierten Schedule-Event-Kommando
     * ("'php' 'artisan' toggl:import") — für den Laufzeit-Recorder.
     */
    public function keyForEventCommand(?string $eventCommand): ?string {
        if (!is_string($eventCommand) || $eventCommand === '') {
            return null;
        }
        $needle = "'artisan' ";
        $pos = strrpos($eventCommand, $needle);
        $name = $pos === false ? $eventCommand : trim(substr($eventCommand, $pos + strlen($needle)));

        foreach ($this->all() as $key => $definition) {
            if ($definition->command === $name) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Default-Kadenz inkl. Setting-gesteuerter Uhrzeit: Jobs mit
     * cadence_setting_key (z. B. archive.run → archive.schedule_at)
     * lesen ihre dailyAt-Zeit weiterhin aus dem Setting-Resolver
     * (env-Default, system_settings-Override).
     */
    public function effectiveCadence(JobDefinition $definition): Cadence {
        if ($definition->cadenceSettingKey === null) {
            return $definition->defaultCadence;
        }

        $time = Setting::get($definition->cadenceSettingKey, $definition->defaultCadence->time);
        if (!is_string($time) || preg_match('/^\d{1,2}:\d{2}$/', $time) !== 1) {
            return $definition->defaultCadence;
        }

        return new Cadence(CadenceType::DailyAt, time: $time);
    }
}
