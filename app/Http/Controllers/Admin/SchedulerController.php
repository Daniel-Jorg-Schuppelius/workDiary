<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SchedulerController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Scheduling\JobRunStatus;
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\{RequiresPlatformOperator, ResolvesCurrentOrganization};
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, ScheduledJobOverride, ScheduledJobState, User};
use App\Scheduling\{Cadence, CadenceType, JobCriticality, JobDefinition, JobRegistry, SchedulerOverrideService, SchedulerRegistrar};
use App\Support\SortableQuery;
use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\{Artisan, Cache, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Scheduler-Adminseite (Feature 067, MVP-176/177): Registry-Jobs mit
 * Effektivplan + Herkunft, letztem Lauf, Fehlerzähler und nächster
 * Fälligkeit; Aktionen pausieren/fortsetzen/umplanen/zurücksetzen/
 * Testlauf — ausschließlich für allowlistete Registry-Jobs.
 *
 * @phpstan-type JobRow array{definition: JobDefinition, enabled: bool, cadence: Cadence, expression: string, source: string, state: ScheduledJobState|null, next_due_at: CarbonImmutable|null, shifted_from: Cadence|null}
 * @phpstan-type JobFilters array{q: string, criticality: JobCriticality|null, status: string|null, source: string|null, paused: bool}
 */
class SchedulerController extends Controller {
    use RequiresPlatformOperator;

    use ResolvesCurrentOrganization;

    private const TEST_RUN_COOLDOWN_MINUTES = 5;

    private const SORTS = ['job', 'last_run', 'next_due', 'failures'];

    /** Filterwert für Jobs ohne Laufnachweis (zugleich Übersetzungs-Key unter scheduler.state). */
    private const STATUS_NEVER = 'never_ran';

    /** Planquelle „vom Betriebsfenster verschoben“ — quer zu default/setting/override. */
    private const SOURCE_SHIFTED = 'shifted';

    private const SOURCES = ['default', 'setting', 'override', self::SOURCE_SHIFTED];

    public function __construct(
        private readonly JobRegistry $registry,
        private readonly SchedulerRegistrar $registrar,
        private readonly SchedulerOverrideService $overrides,
    ) {}

    public function index(Request $request): View {
        Gate::authorize(Permission::PlatformSchedulerManage->value);
        $this->assertPlatformOperator();

        [$sort, $dir] = SortableQuery::resolve($request, self::SORTS, 'job', 'asc');
        $status = $request->string('status')->toString();
        $source = $request->string('source')->toString();
        $filters = [
            'q' => trim($request->string('q')->toString()),
            'criticality' => JobCriticality::tryFrom($request->string('criticality')->toString()),
            'status' => in_array($status, [...JobRunStatus::values(), self::STATUS_NEVER], true) ? $status : null,
            'source' => in_array($source, self::SOURCES, true) ? $source : null,
            'paused' => $request->boolean('paused'),
        ];

        $overrideMap = ScheduledJobOverride::systemMap();
        $states = ScheduledJobState::query()->get()->keyBy('job_key');
        $now = CarbonImmutable::now((string) config('app.schedule_timezone', config('app.timezone')));

        $jobs = collect($this->registry->all())->map(function ($definition) use ($overrideMap, $states, $now) {
            $override = $overrideMap[$definition->key] ?? null;
            $enabled = $override['enabled'] ?? true;
            $configured = $this->registrar->configuredCadence($definition);
            $cadence = $this->registrar->resolvedCadence($definition);
            $expression = $cadence->cronExpression();
            $nextDue = null;
            if ($enabled) {
                try {
                    $nextDue = CarbonImmutable::instance(
                        (new CronExpression($expression))->getNextRunDate($now->toDateTime()),
                    );
                } catch (\Throwable) {
                    $nextDue = null;
                }
            }

            return [
                'definition' => $definition,
                'enabled' => $enabled,
                'cadence' => $cadence,
                'expression' => $expression,
                'source' => ($override['cadence'] ?? null) !== null ? 'override' : ($definition->cadenceSettingKey !== null ? 'setting' : 'default'),
                'state' => $states->get($definition->key),
                'next_due_at' => $nextDue,
                // Vom Betriebsfenster verschoben: eingestellte Zeit zur Erklärung.
                'shifted_from' => $configured->cronExpression() !== $expression ? $configured : null,
            ];
        })->filter(fn(array $job): bool => $this->matchesFilters($job, $filters));

        return view('admin.scheduler.index', [
            'jobs' => $this->sortJobs($jobs, $sort, $dir),
            'filters' => $filters,
            'statusOptions' => JobRunStatus::options() + [self::STATUS_NEVER => __('scheduler.state.' . self::STATUS_NEVER)],
            'sourceOptions' => collect(self::SOURCES)
                ->mapWithKeys(static fn(string $s): array => [$s => __($s === self::SOURCE_SHIFTED ? 'scheduler.filter.shifted' : 'scheduler.source.' . $s)]),
            'sort' => $sort,
            'dir' => $dir,
            'operatingWindow' => $this->registrar->operatingWindow(),
        ]);
    }

    /**
     * @param JobRow $job
     * @param JobFilters $filters
     */
    private function matchesFilters(array $job, array $filters): bool {
        $definition = $job['definition'];
        $state = $job['state'];

        if ($filters['q'] !== '') {
            $haystack = implode(' ', [$definition->label(), $definition->key, $definition->command]);
            if (mb_stripos($haystack, $filters['q']) === false) {
                return false;
            }
        }
        if ($filters['criticality'] !== null && $definition->criticality !== $filters['criticality']) {
            return false;
        }
        if ($filters['status'] !== null && ($state?->last_status->value ?? self::STATUS_NEVER) !== $filters['status']) {
            return false;
        }
        if ($filters['source'] !== null
            && ! ($filters['source'] === self::SOURCE_SHIFTED ? $job['shifted_from'] !== null : $job['source'] === $filters['source'])) {
            return false;
        }

        return ! $filters['paused'] || ! $job['enabled'];
    }

    /**
     * Jobs ohne Wert (nie gelaufen, pausiert ohne Fälligkeit) stehen in
     * beiden Richtungen hinten — sonst verdeckten sie beim Absteigend-
     * Sortieren nach letztem Lauf die jüngsten Läufe.
     *
     * @param Collection<string, JobRow> $jobs
     * @return Collection<int, JobRow>
     */
    private function sortJobs(Collection $jobs, string $sort, string $dir): Collection {
        /** @param JobRow $job */
        $value = static fn(array $job): int|string|null => match ($sort) {
            'last_run' => $job['state']?->last_started_at?->getTimestamp(),
            'next_due' => $job['next_due_at']?->getTimestamp(),
            'failures' => $job['state']->consecutive_failures ?? 0,
            default => Str::lower(Str::ascii($job['definition']->label())),
        };

        return $jobs->sort(static function (array $a, array $b) use ($value, $dir): int {
            [$x, $y] = [$value($a), $value($b)];
            $cmp = match (true) {
                $x === null && $y === null => 0,
                $x === null => 1,
                $y === null => -1,
                default => $dir === 'asc' ? $x <=> $y : $y <=> $x,
            };

            return $cmp !== 0 ? $cmp : strcmp($a['definition']->key, $b['definition']->key);
        })->values();
    }

    public function edit(string $job): View {
        Gate::authorize(Permission::PlatformSchedulerManage->value);
        $this->assertPlatformOperator();
        $definition = $this->definitionOr404($job);

        return view('admin.scheduler._form_dialog', [
            'definition' => $definition,
            // Eingestellte, nicht die ins Betriebsfenster verschobene Zeit — sonst
            // schriebe jedes Speichern die Verschiebung als Override fest.
            'cadence' => $this->registrar->configuredCadence($definition),
        ]);
    }

    public function update(Request $request, string $job): RedirectResponse {
        Gate::authorize(Permission::PlatformSchedulerManage->value);
        $this->assertPlatformOperator();
        $definition = $this->definitionOr404($job);

        $allowed = array_map(static fn(CadenceType $t): string => $t->value, $definition->allowedCadences);
        $validated = $request->validate([
            'cadence_type' => ['required', Rule::in($allowed)],
            'time' => ['nullable', 'date_format:H:i', 'required_if:cadence_type,dailyAt,weeklyOn,monthlyOn'],
            'day' => ['nullable', 'integer', 'required_if:cadence_type,weeklyOn,monthlyOn', 'min:0', 'max:31'],
            'expression' => ['nullable', 'string', 'max:100', 'required_if:cadence_type,cron'],
        ]);

        $type = CadenceType::from($validated['cadence_type']);
        if ($type === CadenceType::WeeklyOn) {
            $request->validate(['day' => ['integer', 'min:0', 'max:6']]);
        }

        $this->overrides->reschedule($job, new Cadence(
            type: $type,
            time: $validated['time'] ?? null,
            day: isset($validated['day']) ? (int) $validated['day'] : null,
            expression: $validated['expression'] ?? null,
        ), $request->user()?->id);

        return redirect()->toList('admin.scheduler.index')
            ->with('status', __('scheduler.flash.rescheduled', ['job' => $definition->label()]));
    }

    public function pause(Request $request, string $job): RedirectResponse {
        Gate::authorize(Permission::PlatformSchedulerManage->value);
        $this->assertPlatformOperator();
        $definition = $this->definitionOr404($job);
        $this->overrides->pause($job, $request->user()?->id);

        return redirect()->toList('admin.scheduler.index')
            ->with('status', __('scheduler.flash.paused', ['job' => $definition->label()]));
    }

    public function resume(Request $request, string $job): RedirectResponse {
        Gate::authorize(Permission::PlatformSchedulerManage->value);
        $this->assertPlatformOperator();
        $definition = $this->definitionOr404($job);
        $this->overrides->resume($job, $request->user()?->id);

        return redirect()->toList('admin.scheduler.index')
            ->with('status', __('scheduler.flash.resumed', ['job' => $definition->label()]));
    }

    public function reset(Request $request, string $job): RedirectResponse {
        Gate::authorize(Permission::PlatformSchedulerManage->value);
        $this->assertPlatformOperator();
        $definition = $this->definitionOr404($job);
        $this->overrides->reset($job);

        return redirect()->toList('admin.scheduler.index')
            ->with('status', __('scheduler.flash.reset', ['job' => $definition->label()]));
    }

    public function testRun(Request $request, string $job): RedirectResponse {
        Gate::authorize(Permission::PlatformSchedulerManage->value);
        $this->assertPlatformOperator();
        $definition = $this->definitionOr404($job);

        $cooldownKey = 'scheduler.testrun.' . $definition->key;
        if (Cache::get($cooldownKey) !== null) {
            return redirect()->toList('admin.scheduler.index')
                ->with('error', __('scheduler.flash.test_run_cooldown', ['minutes' => self::TEST_RUN_COOLDOWN_MINUTES]));
        }
        Cache::put($cooldownKey, true, now()->addMinutes(self::TEST_RUN_COOLDOWN_MINUTES));

        [$command, $parameters] = $this->splitCommand($definition->command);
        Artisan::queue($command, $parameters);

        $this->writeTestRunAudit($request->user(), $definition->key);

        return redirect()->toList('admin.scheduler.index')
            ->with('status', __('scheduler.flash.test_run_queued', ['job' => $definition->label()]));
    }

    /**
     * Registry-Definition oder 404: nicht registrierte Job-Keys sind keine
     * erreichbare Ressource, daher NotFound statt ungefangener Registry-
     * Exception (die sonst als 500 durchschlägt).
     */
    private function definitionOr404(string $job): JobDefinition {
        abort_unless($this->registry->has($job), 404);

        return $this->registry->definition($job);
    }

    /**
     * Zerlegt den Registry-Kommandostring in Name + Optionsarray für
     * Artisan::queue (nur --flag bzw. --key=value, wie in der Registry
     * deklariert — keine freien Nutzerkommandos).
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function splitCommand(string $command): array {
        $tokens = preg_split('/\s+/', trim($command)) ?: [];
        $name = (string) array_shift($tokens);
        $parameters = [];
        foreach ($tokens as $token) {
            if (str_starts_with($token, '--')) {
                [$key, $value] = array_pad(explode('=', $token, 2), 2, null);
                $parameters[$key] = $value ?? true;
            }
        }

        return [$name, $parameters];
    }

    private function writeTestRunAudit(?User $user, string $jobKey): void {
        if ($user === null) {
            return;
        }

        AuditLog::query()->create([
            'organization_id' => $this->currentOrganization()->id,
            'user_id' => $user->id,
            'event' => 'scheduler.testRun',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'changes' => ['job' => $jobKey],
        ]);
    }
}
