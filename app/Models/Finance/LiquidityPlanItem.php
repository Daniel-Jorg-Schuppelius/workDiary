<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LiquidityPlanItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Finance;

use App\Enums\Finance\LiquidityPlanRecurrence;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Manuelle Planposition der Liquiditätsvorschau (MVP-984), z. B. Steuer-
 * vorauszahlung, Kreditrate oder Einlage — einmalig oder monatlich.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $label
 * @property string $direction
 * @property numeric-string $planned_amount
 * @property string $currency
 * @property Carbon $starts_on
 * @property LiquidityPlanRecurrence $recurrence
 * @property Carbon|null $ends_on
 * @property string|null $note
 * @property int|null $created_by
 */
class LiquidityPlanItem extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'label', 'direction', 'planned_amount', 'currency', 'starts_on', 'recurrence', 'ends_on', 'note', 'created_by'];

    /** @var array<string, string> */
    protected $casts = [
        'planned_amount' => 'decimal:2',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'recurrence' => LiquidityPlanRecurrence::class,
    ];
}
