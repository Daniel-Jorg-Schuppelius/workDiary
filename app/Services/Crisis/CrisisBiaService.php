<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisBiaService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Crisis;

use App\Enums\Crisis\{CrisisContinuityImpactStatus, CrisisProcessCriticality};
use App\Enums\Isms\RiskStatus;
use App\Enums\Privacy\ProcessingActivityStatus;
use App\Models\Crisis\{CrisisBusinessProcess, CrisisCase, CrisisContinuityImpact};
use App\Models\Isms\IsmsRisk;
use App\Models\Platform\{Organization, User};
use App\Models\Privacy\ProcessingActivity;
use App\Models\Procedure\ProcedureTemplate;
use Illuminate\Database\Eloquent\Model;

/**
 * BIA-Register (MVP-943): Vorschläge aus Verzeichnis der Verarbeitungs-
 * tätigkeiten, ISMS-Risiken und aktiven Prozedurvorlagen; übernommen wird nur,
 * was jemand auswählt. Im Krisenfall werden Einträge als Auswirkung mit ihren
 * Wiederanlaufzielen übernommen.
 */
final class CrisisBiaService {
    /** @return list<array{key: string, label: string, kind: string}> noch nicht übernommene Quellen */
    public function candidates(Organization $organization): array {
        $taken = CrisisBusinessProcess::query()->whereNotNull('source_type')->get(['source_type', 'source_id'])
            ->map(static fn (CrisisBusinessProcess $p): string => $p->source_type . ':' . $p->source_id)->flip();
        $out = [];
        $add = static function (Model $model, string $label, string $kind) use (&$out, $taken): void {
            $key = $model->getMorphClass() . ':' . $model->getKey();
            if (! $taken->has($key)) {
                $out[] = ['key' => $key, 'label' => $label, 'kind' => $kind];
            }
        };
        foreach (ProcessingActivity::query()->where('organization_id', $organization->id)->where('status', '!=', ProcessingActivityStatus::Archived->value)->orderBy('name')->get() as $activity) {
            $add($activity, (string) $activity->name, 'processing_activity');
        }
        foreach (IsmsRisk::query()->where('organization_id', $organization->id)->where('status', '!=', RiskStatus::Closed->value)->orderBy('title')->get() as $risk) {
            $add($risk, (string) $risk->title, 'isms_risk');
        }
        foreach (ProcedureTemplate::query()->where('organization_id', $organization->id)->where('active', true)->orderBy('name')->get() as $template) {
            $add($template, (string) $template->name, 'procedure_template');
        }

        return $out;
    }

    /** @param list<string> $keys Auswahl im Format „alias:id“ */
    public function import(Organization $organization, array $keys, User $actor): int {
        $wanted = array_flip($keys);
        $count = 0;
        foreach ($this->candidates($organization) as $candidate) {
            if (! isset($wanted[$candidate['key']])) {
                continue;
            }
            [$alias, $id] = explode(':', $candidate['key'], 2);
            CrisisBusinessProcess::query()->create([
                'organization_id' => $organization->id,
                'name' => mb_substr($candidate['label'], 0, 200),
                'criticality' => CrisisProcessCriticality::Medium,
                'source_type' => $alias,
                'source_id' => (int) $id,
                'created_by' => $actor->id,
            ]);
            $count++;
        }

        return $count;
    }

    public function adopt(CrisisCase $case, CrisisBusinessProcess $process): CrisisContinuityImpact {
        return $case->continuityImpacts()->create([
            'organization_id' => $case->organization_id,
            'crisis_business_process_id' => $process->id,
            'process_name' => $process->name,
            'rto_hours' => $process->rto_hours,
            'rpo_hours' => $process->rpo_hours,
            'status' => CrisisContinuityImpactStatus::Down,
        ]);
    }
}
