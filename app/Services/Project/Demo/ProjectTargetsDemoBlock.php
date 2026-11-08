<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTargetsDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Project\Demo;

use App\Enums\Project\ShortfallStatus;
use App\Models\Platform\Organization;
use App\Models\Project\{Project, ProjectTimeShortfall, ProjectTimeTarget};
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use App\Support\Tz;
use Carbon\CarbonImmutable;

/**
 * Demo-Block (Feature 194): Projektvorgabe 8 h Mo–Fr am Hauptprojekt, eine
 * Ausnahme je Person und ein offener Befund von gestern, damit Zeitreiter,
 * Arbeitsvorrat, Leitungssicht und Plan/Ist etwas zu zeigen haben.
 */
final class ProjectTargetsDemoBlock implements DemoBlock {
    public function supports(DemoSeedContext $context): bool {
        return $context->projects->isNotEmpty() && $context->users->isNotEmpty();
    }

    public function seed(DemoSeedContext $context): array {
        /** @var Project $project */
        $project = $context->projects->first();
        $members = $context->users->take(3);
        $project->members()->syncWithoutDetaching($members->pluck('id')->all());
        $created = 0;
        foreach ([1, 2, 3, 4, 5] as $weekday) {
            ProjectTimeTarget::query()->firstOrCreate(
                ['project_id' => $project->id, 'user_id' => null, 'weekday' => $weekday, 'specific_date' => null],
                ['organization_id' => $context->organization->id, 'target_minutes' => 480, 'created_by' => $context->actor->id, 'updated_by' => $context->actor->id],
            );
            $created++;
        }
        $first = $members->first();
        if ($first === null) {
            return ['project_time_targets' => $created];
        }
        ProjectTimeTarget::query()->firstOrCreate(
            ['project_id' => $project->id, 'user_id' => $first->id, 'weekday' => 5, 'specific_date' => null],
            ['organization_id' => $context->organization->id, 'target_minutes' => 240, 'created_by' => $context->actor->id, 'updated_by' => $context->actor->id],
        );
        $yesterday = CarbonImmutable::now(Tz::ofOrganization($context->organization))->subDay();
        if ($yesterday->isWeekday()) {
            // Soll der ersten Person an diesem Tag: freitags gilt ihre eigene Zeile oben.
            $target = $yesterday->dayOfWeekIso === 5 ? 240 : 480;
            ProjectTimeShortfall::query()->firstOrCreate(
                ['project_id' => $project->id, 'user_id' => $first->id, 'date' => $yesterday->startOfDay()],
                [
                    'organization_id' => $context->organization->id,
                    'target_minutes' => $target, 'booked_minutes' => $target - 90, 'shortfall_minutes' => 90, 'tolerance_minutes' => 15,
                    'status' => ShortfallStatus::Open,
                ],
            );
        }

        return ['project_time_targets' => $created + 1];
    }

    public function purge(Organization $organization): void {
        ProjectTimeShortfall::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
        ProjectTimeTarget::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
    }
}
