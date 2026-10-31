<?php
/*
 * Created on   : Sat Oct 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenceAssignments.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Models\Customer\{Customer, ForeignCustomer};
use App\Models\Reselling\ResaleSubscription;
use Illuminate\Support\Facades\DB;

/**
 * Abtretung anlegen (Feature 185): Kind-Abo mit eigenem Halter, Menge und
 * Laufzeit; Produkt, Einkaufspreis und Rhythmus vom Vertrag. Der Vertrag plant
 * danach mit dem Rest. Abtretungs- und Übernahme-Dialog nutzen denselben Weg
 * (Phase 166, MVP-1451).
 */
final class LicenceAssignments {
    public function __construct(private readonly PeriodPlanner $planner = new PeriodPlanner) {}

    /**
     * @param  array{customer: Customer, foreign: ForeignCustomer|null}  $holder
     * @param  string|null  $salePrice  Verkaufspreis je Lizenz, sonst der des Vertrags
     */
    public function create(ResaleSubscription $contract, array $holder, int $quantity, string $startsOn, ?string $endsOn, ?string $salePrice, ?string $note, ?int $userId): ResaleSubscription {
        return DB::transaction(function () use ($contract, $holder, $quantity, $startsOn, $endsOn, $salePrice, $note, $userId): ResaleSubscription {
            $contract->load('assignments');
            $sequence = $contract->nextAssignmentSuffix();
            $assignment = ResaleSubscription::query()->create([
                'organization_id' => $contract->organization_id,
                'parent_id' => $contract->id,
                'kind' => $contract->kind,
                'provider' => $contract->provider,
                'external_id' => $contract->external_id !== null ? $contract->external_id . '#' . $sequence : null,
                'label' => $contract->label,
                'company_name' => $holder['foreign'] !== null ? $holder['foreign']->name : $holder['customer']->name,
                'customer_id' => $holder['foreign'] === null ? $holder['customer']->id : null,
                'foreign_customer_id' => $holder['foreign']?->id,
                'is_own_holding' => false,
                'article_ref' => $contract->article_ref,
                'quantity' => $quantity,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'term_months' => $contract->term_months,
                'interval' => $contract->interval,
                'renewal' => $contract->renewal,
                'purchase_unit_price' => $contract->purchase_unit_price?->getAmount(),
                'sale_unit_price' => $salePrice ?? $contract->sale_unit_price?->getAmount(),
                'currency' => $contract->currency,
                'status' => $contract->status,
                'notes' => $note,
                'created_by_user_id' => $userId,
            ]);
            $this->planner->sync($assignment);
            $contract->unsetRelation('assignments');
            $this->planner->sync($contract);

            return $assignment;
        });
    }
}
