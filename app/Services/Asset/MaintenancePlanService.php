<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaintenancePlanService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Asset;

use App\Enums\Asset\{MaintenanceDueAction, MaintenanceIntervalKind};
use App\Exceptions\AssetValidationException;
use App\Models\Asset\{Asset, MaintenancePlan};
use App\Models\Platform\User;
use App\Support\MorphMap;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MaintenancePlanService {
    use \App\Services\Concerns\ParsesMixedDate;

    /** @param array<string, mixed> $payload */
    public function create(Asset $asset, User $actor, array $payload): MaintenancePlan {
        $kind = $this->parseKind((string) ($payload['interval_kind'] ?? MaintenanceIntervalKind::Months->value));
        $value = max(1, (int) ($payload['interval_value'] ?? 0));

        $plan = new MaintenancePlan([
            'organization_id' => $asset->organization_id,
            'subject_type' => MorphMap::alias(Asset::class),
            'subject_id' => $asset->id,
            'asset_id' => $asset->id,
            'code' => (string) ($payload['code'] ?? $this->generateCode($asset)),
            'label' => (string) ($payload['label'] ?? __('Wartung')),
            'interval_kind' => $kind->value,
            'interval_value' => $value,
            'tolerance_days' => max(0, (int) ($payload['tolerance_days'] ?? 0)),
            'last_run_at' => $payload['last_run_at'] ?? null,
            'next_due_on' => $this->parseDate($payload['next_due_on'] ?? null)
                ?? $this->computeNextDue(null, $kind, $value),
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'notes' => $payload['notes'] ?? null,
            'sla_contract_id' => isset($payload['sla_contract_id']) ? (int) $payload['sla_contract_id'] : null,
            'is_contractual' => (bool) ($payload['is_contractual'] ?? false),
            'due_action' => $this->parseDueAction($payload['due_action'] ?? null),
        ]);

        DB::transaction(function () use ($plan, $asset): void {
            $plan->save();
            $this->refreshAssetNextMaintenance($asset);
        });

        $plan->audit('maintenance_plan.created', ['code' => $plan->code]);

        return $plan->refresh();
    }

    /** @param array<string, mixed> $payload */
    public function update(MaintenancePlan $plan, User $actor, array $payload): MaintenancePlan {
        if (array_key_exists('interval_kind', $payload)) {
            $plan->interval_kind = $this->parseKind((string) $payload['interval_kind']);
        }
        if (array_key_exists('interval_value', $payload)) {
            $plan->interval_value = max(1, (int) $payload['interval_value']);
        }
        if (array_key_exists('tolerance_days', $payload)) {
            $plan->tolerance_days = max(0, (int) $payload['tolerance_days']);
        }
        foreach (['code', 'label', 'notes'] as $field) {
            if (array_key_exists($field, $payload)) {
                $plan->{$field} = $payload[$field];
            }
        }
        if (array_key_exists('next_due_on', $payload)) {
            $plan->next_due_on = $this->parseDate($payload['next_due_on']);
        }
        if (array_key_exists('is_active', $payload)) {
            $plan->is_active = (bool) $payload['is_active'];
        }
        if (array_key_exists('sla_contract_id', $payload)) {
            $plan->sla_contract_id = $payload['sla_contract_id'] !== null ? (int) $payload['sla_contract_id'] : null;
        }
        if (array_key_exists('is_contractual', $payload)) {
            $plan->is_contractual = (bool) $payload['is_contractual'];
        }
        if (array_key_exists('due_action', $payload)) {
            $plan->due_action = $this->parseDueAction($payload['due_action']);
        }

        DB::transaction(function () use ($plan): void {
            $plan->save();
            $asset = $plan->asset()->firstOrFail();
            $this->refreshAssetNextMaintenance($asset);
        });

        return $plan->refresh();
    }

    public function markCompleted(MaintenancePlan $plan, User $actor, ?Carbon $completedAt = null): MaintenancePlan {
        $when = $completedAt ?? Carbon::now();
        $plan->last_run_at = $when;
        $plan->next_due_on = $this->computeNextDue($when, $plan->interval_kind, $plan->interval_value);

        DB::transaction(function () use ($plan): void {
            $plan->save();
            $asset = $plan->asset()->firstOrFail();
            $this->refreshAssetNextMaintenance($asset);
        });

        $plan->audit('maintenance_plan.completed', [
            'completed_at' => $when->toIso8601String(),
            'next_due_on' => $plan->next_due_on?->toDateString(),
        ]);

        return $plan->refresh();
    }

    public function pause(MaintenancePlan $plan, User $actor): MaintenancePlan {
        if (! $plan->is_active) {
            return $plan;
        }
        $plan->is_active = false;

        DB::transaction(function () use ($plan): void {
            $plan->save();
            $asset = $plan->asset()->firstOrFail();
            $this->refreshAssetNextMaintenance($asset);
        });

        $plan->audit('maintenance_plan.paused', []);

        return $plan->refresh();
    }

    public function resume(MaintenancePlan $plan, User $actor): MaintenancePlan {
        if ($plan->is_active) {
            return $plan;
        }
        $plan->is_active = true;

        DB::transaction(function () use ($plan): void {
            $plan->save();
            $asset = $plan->asset()->firstOrFail();
            $this->refreshAssetNextMaintenance($asset);
        });

        $plan->audit('maintenance_plan.resumed', []);

        return $plan->refresh();
    }

    /**
     * Recomputes the asset's `next_maintenance_on` to the earliest active plan due date.
     */
    public function refreshAssetNextMaintenance(Asset $asset): void {
        /** @var string|null $earliest */
        $earliest = MaintenancePlan::query()
            ->where('asset_id', $asset->id)
            ->where('is_active', true)
            ->whereNotNull('next_due_on')
            ->min('next_due_on');

        $asset->next_maintenance_on = $earliest !== null ? Carbon::parse($earliest) : null;
        $asset->saveQuietly();
        app(AssetHealthService::class)->sync($asset);
    }

    private function computeNextDue(?Carbon $from, MaintenanceIntervalKind $kind, int $value): ?Carbon {
        $base = $from?->copy() ?? Carbon::now();

        return match ($kind) {
            MaintenanceIntervalKind::Days => $base->addDays($value)->startOfDay(),
            MaintenanceIntervalKind::Weeks => $base->addWeeks($value)->startOfDay(),
            MaintenanceIntervalKind::Months => $base->addMonths($value)->startOfDay(),
            MaintenanceIntervalKind::OperatingHours, MaintenanceIntervalKind::Kilometers => null,
        };
    }

    private function parseKind(string $value): MaintenanceIntervalKind {
        $kind = MaintenanceIntervalKind::tryFrom($value);
        if ($kind === null) {
            throw new AssetValidationException('Ungültiges Wartungsintervall: ' . $value);
        }

        return $kind;
    }

    private function parseDueAction(mixed $value): MaintenanceDueAction {
        if ($value instanceof MaintenanceDueAction) {
            return $value;
        }

        return MaintenanceDueAction::tryFrom((string) $value) ?? MaintenanceDueAction::None;
    }

    private function generateCode(Asset $asset): string {
        $base = 'MP-' . str_pad((string) $asset->id, 5, '0', STR_PAD_LEFT);
        $suffix = 1;
        do {
            $candidate = $base . '-' . $suffix;
            $exists = MaintenancePlan::query()
                ->where('asset_id', $asset->id)
                ->where('code', $candidate)
                ->exists();
            $suffix++;
        } while ($exists);

        return $candidate;
    }
}
