<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EarlyWarningService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Reporting;

use App\Models\Platform\Organization;
use App\Modules\ModuleRegistry;
use App\Services\Reporting\Contracts\EarlyWarningSource;
use App\Services\Reporting\Dto\EarlyWarning;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\{Cache, Log};
use Throwable;

/**
 * Sammelt die Frühwarnungen aller Module einer Organisation (MVP-889). Eine
 * fehlerhafte Quelle fällt einzeln aus, statt die übrigen zu verdecken.
 */
final class EarlyWarningService {
    public function __construct(private readonly ModuleRegistry $modules) {}

    /** @return list<EarlyWarning> */
    public function collect(Organization $organization): array {
        return OrganizationContext::run($organization, function () use ($organization): array {
            $all = [];
            foreach ($this->modules->extensions(EarlyWarningSource::class) as $class) {
                try {
                    /** @var EarlyWarningSource $source */
                    $source = app($class);
                    array_push($all, ...$source->warnings($organization));
                } catch (Throwable $e) {
                    Log::warning('early warning source failed', ['source' => $class, 'organization_id' => $organization->id, 'error' => $e->getMessage()]);
                }
            }

            return $all;
        });
    }

    /**
     * Für die Dashboard-Kachel: eine Stunde je Organisation zwischengespeichert.
     *
     * @return list<array{kind: string, title: string, detail: string, recommendation: string, url: ?string, params: array<string, scalar>}>
     */
    public function cached(Organization $organization): array {
        return Cache::remember('early-warnings:' . $organization->id, 3600, fn (): array => array_map(
            static fn (EarlyWarning $w): array => ['kind' => $w->kind, 'title' => $w->title, 'detail' => $w->detail, 'recommendation' => $w->recommendation, 'url' => $w->url, 'params' => $w->params],
            $this->collect($organization),
        ));
    }
}
