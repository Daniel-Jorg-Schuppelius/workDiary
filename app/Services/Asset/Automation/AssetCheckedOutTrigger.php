<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetCheckedOutTrigger.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Asset\Automation;

use App\Automation\Triggers\RuleTrigger;
use App\Models\Asset\AssetAssignment;
use Illuminate\Database\Eloquent\Model;

/** Gerät an eine Person oder ein Team ausgegeben (MVP-1273), etwa für die Geräteeinweisung. */
final class AssetCheckedOutTrigger implements RuleTrigger {
    public const KEY = 'asset.checkedOut';

    public function key(): string {
        return self::KEY;
    }

    public function label(): string {
        return (string) __('automation.trigger.asset_checked_out');
    }

    /** @return array<string, mixed> */
    public function exampleConditions(): array {
        return ['all' => [['field' => 'category_code', 'op' => 'in', 'value' => ['HAB', 'STAPLER']]]];
    }

    /** @return array<string, mixed> */
    public function context(Model $subject): array {
        if (! $subject instanceof AssetAssignment) {
            return $subject->toArray();
        }
        $asset = $subject->asset;

        return [
            'asset_id' => (int) $subject->asset_id,
            'asset_no' => $asset?->asset_no,
            'asset_class' => $asset?->asset_class?->value,
            'category_code' => $asset?->category_code,
            'manufacturer' => $asset?->manufacturer,
            'model' => $asset?->model,
            'assigned_to_user_id' => $subject->assigned_to_user_id,
            'assigned_to_team_id' => $subject->assigned_to_team_id,
        ];
    }
}
