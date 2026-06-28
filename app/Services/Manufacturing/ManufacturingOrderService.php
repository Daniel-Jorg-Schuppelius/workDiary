<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManufacturingOrderService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\Manufacturing\ManufacturingOrderStatus;
use App\Enums\Numbering\NumberScope;
use App\Enums\Procedure\ProcedureRunStatus;
use App\Models\{Article, ArticleVariant, ManufacturingOrder, Organization, ProcedureMaterialRequirement, ProcedureRun, ProcedureTemplateVersion};
use App\Services\Numbering\NumberSequenceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Fertigungs-/Montageaufträge (Feature 047, MVP-062). Beim Freigeben friert der
 * Auftrag die Arbeitsplan-Version, die Variante und die aus der Stückliste
 * reproduzierbar berechnete Sollmengen-Liste als unveränderliche Snapshots ein.
 */
class ManufacturingOrderService {
    public function __construct(
        private readonly MaterialDemandCalculator $calculator = new MaterialDemandCalculator(),
        private readonly NumberSequenceService $numbers = new NumberSequenceService(),
        private readonly BomResolver $bomResolver = new BomResolver(),
        private readonly ParameterResolver $parameters = new ParameterResolver(),
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createDraft(Organization $organization, Article $article, ?ArticleVariant $variant, string $targetQty, string $unit, array $attributes = []): ManufacturingOrder {
        return ManufacturingOrder::query()->create(array_merge([
            'organization_id' => $organization->id,
            'number' => $this->numbers->next($organization, NumberScope::ManufacturingOrder),
            'article_id' => $article->id,
            'article_variant_id' => $variant?->id,
            'target_qty' => $targetQty,
            'unit' => $unit,
            'status' => ManufacturingOrderStatus::Draft->value,
        ], $attributes));
    }

    /**
     * Freigabe: Arbeitsplan-Version + Stückliste auflösen, Sollmengen berechnen
     * und alles als Snapshot einfrieren.
     */
    public function release(ManufacturingOrder $order, ?ProcedureTemplateVersion $version = null): ManufacturingOrder {
        if (! $order->status->canTransitionTo(ManufacturingOrderStatus::Released)) {
            throw new RuntimeException('Auftrag kann aus dem aktuellen Status nicht freigegeben werden.');
        }

        $version ??= $order->article->defaultProcedureVersion;
        if ($version === null) {
            throw new RuntimeException('Keine Arbeitsplan-Version für die Freigabe vorhanden.');
        }

        // Basis-Stückliste + Varianten-Overrides der konkreten Variante auflösen.
        $bom = $this->bomResolver->resolve($version, $order->variant);
        $lines = $this->calculator->calculate($bom, (string) $order->target_qty);

        // Auftragsparameter gegen die Version validieren und einfrieren (MVP-061).
        // Wirft vor der Transaktion bei Pflichtverletzung/ungültigem Wert.
        $parameterSnapshot = $this->parameters->snapshot($version, $order->parameters ?? []);

        return DB::transaction(function () use ($order, $version, $lines, $parameterSnapshot): ManufacturingOrder {
            $snapshot = [];
            foreach ($lines as $line) {
                /** @var ProcedureMaterialRequirement $req */
                $req = $line['requirement'];
                $article = $req->article;
                $name = $article instanceof Article ? $article->name : $req->position_code;

                $order->materials()->create([
                    'article_id' => $req->article_id,
                    'article_variant_id' => $req->article_variant_id,
                    'name_snapshot' => $name,
                    'target_qty' => $line['demand'],
                    'unit_snapshot' => $req->unit,
                    'calc_reason' => $req->quantity_kind->value,
                    'rounding' => $req->rounding,
                    'is_tool' => $req->is_tool,
                ]);

                $snapshot[] = [
                    'position_code' => $req->position_code,
                    'article_id' => $req->article_id,
                    'name' => $name,
                    'demand' => $line['demand'],
                    'unit' => $req->unit,
                    'kind' => $req->quantity_kind->value,
                    'is_tool' => $req->is_tool,
                ];
            }

            $order->forceFill([
                'status' => ManufacturingOrderStatus::Released,
                'procedure_template_version_id' => $version->id,
                'bom_snapshot' => $snapshot,
                'variant_snapshot' => $this->variantSnapshot($order->variant),
                'parameter_snapshot' => $parameterSnapshot,
                'released_at' => Carbon::now(),
            ])->save();

            return $order;
        });
    }

    /** Statusübergang gemäß Statusmaschine. */
    public function transition(ManufacturingOrder $order, ManufacturingOrderStatus $target): ManufacturingOrder {
        if (! $order->status->canTransitionTo($target)) {
            throw new RuntimeException(sprintf('Unzulässiger Statusübergang %s → %s.', $order->status->value, $target->value));
        }

        $order->status = $target;
        if ($target === ManufacturingOrderStatus::Completed) {
            $order->completed_at = Carbon::now();
        }
        $order->save();

        return $order;
    }

    /**
     * Startet die Ausführung (MVP-063): legt einen {@see ProcedureRun} auf der
     * eingefrorenen Arbeitsplan-Version an, verknüpft ihn und setzt den Auftrag
     * auf „In Arbeit". Die mobile Schritt-für-Schritt-Ausführung nutzt den
     * vorhandenen Prozedur-Kern.
     */
    public function startExecution(ManufacturingOrder $order, ?int $assignedUserId = null): ManufacturingOrder {
        if (! $order->status->canTransitionTo(ManufacturingOrderStatus::InProgress)) {
            throw new RuntimeException('Auftrag kann aus dem aktuellen Status nicht gestartet werden.');
        }
        if ($order->procedure_template_version_id === null) {
            throw new RuntimeException('Auftrag ohne eingefrorene Arbeitsplan-Version.');
        }

        return DB::transaction(function () use ($order, $assignedUserId): ManufacturingOrder {
            /** @var ProcedureRun $run */
            $run = ProcedureRun::query()->create([
                'organization_id' => $order->organization_id,
                'procedure_template_version_id' => $order->procedure_template_version_id,
                'subject_type' => $order->getMorphClass(),
                'subject_id' => $order->getKey(),
                'status' => ProcedureRunStatus::InProgress->value,
                'assigned_user_id' => $assignedUserId,
                'started_at' => Carbon::now(),
                'created_by_user_id' => $assignedUserId,
            ]);

            $order->forceFill([
                'status' => ManufacturingOrderStatus::InProgress,
                'procedure_run_id' => $run->id,
            ])->save();

            return $order;
        });
    }

    /** @return array<string, mixed>|null */
    private function variantSnapshot(?ArticleVariant $variant): ?array {
        if ($variant === null) {
            return null;
        }

        return [
            'sku' => $variant->sku,
            'name' => $variant->name,
            'option_signature' => $variant->option_signature,
        ];
    }
}
