<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EnsureModuleActive.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs\Middleware;

use App\Enums\Modules\ModuleKind;
use App\Jobs\Attributes\BelongsToModule;
use App\Jobs\Contracts\ModuleJob;
use App\Models\Platform\Organization;
use App\Modules\{Manifest, ModuleRegistry};
use App\Services\Licensing\ModuleStatusResolver;
use Closure;
use Illuminate\Support\Facades\Log;
use ReflectionClass;

/**
 * Modul-Gate der Queue (MVP-1518, Konzept „Modul-Konfiguration“ §5): ein
 * eingereihter Job eines Fachmoduls prüft vor seiner Wirkung, ob das Modul für
 * die Organisation noch aktiv ist, und endet sonst protokolliert ohne Wirkung
 * und ohne Wiederholung. Hängt als Bus-Pipe vor jedem Job (sync wie Worker).
 *
 * Plattform- und Kernmodule bleiben ausgenommen, auch Kernmodule mit
 * Lizenzcode (Dokumente): ihre Jobs bedienen andere Module mit. Plugin-Jobs
 * prüft ihr Plugin-Schalter; die Plugin-Zuordnung im Manifest ist Katalog.
 */
final class EnsureModuleActive {
    public function __construct(
        private readonly ModuleRegistry $modules,
        private readonly ModuleStatusResolver $status,
    ) {}

    public function handle(object $command, Closure $next): mixed {
        if (! $command instanceof ModuleJob) {
            return $next($command);
        }
        $manifest = $this->manifestFor($command::class);
        $license = $manifest !== null && $manifest->kind() === ModuleKind::Feature ? $manifest->licenseCode() : null;
        $organizationId = $license !== null ? $command->organizationId() : null;
        $organization = $organizationId !== null ? Organization::query()->withoutGlobalScopes()->find($organizationId) : null;
        if ($license === null || ! $organization instanceof Organization || $this->status->isActiveFor($organization, $license)) {
            return $next($command);
        }

        Log::info('queue: Job ohne Wirkung beendet, Modul für die Organisation nicht aktiv', [
            'job' => $command::class,
            'module' => $manifest->code(),
            'organization_id' => $organizationId,
        ]);

        return null;
    }

    /**
     * Manifest eines Jobs: {@see BelongsToModule}, sonst der Domänenordner
     * unter `App\Jobs`; Plugin-Jobs und Jobs ohne Ordner haben keins.
     *
     * @param  class-string  $jobClass
     */
    public function manifestFor(string $jobClass): ?Manifest {
        $attribute = (new ReflectionClass($jobClass))->getAttributes(BelongsToModule::class)[0] ?? null;
        if ($attribute !== null) {
            return $this->modules->byCode($attribute->newInstance()->module);
        }
        if (preg_match('/^App\\\\Jobs\\\\(\w+)\\\\\w+$/', $jobClass, $m) !== 1) {
            return null;
        }

        return $this->modules->byFolder($m[1]);
    }
}
