<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemDay.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Expense\PerDiemDayKind;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\PerDiemDayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int $per_diem_trip_id
 * @property Carbon $date
 * @property PerDiemDayKind $kind
 * @property string $country
 * @property int|null $per_diem_rate_id
 * @property string $base_amount
 * @property string $deduction_breakfast
 * @property string $deduction_lunch
 * @property string $deduction_dinner
 * @property string $deductions_total
 * @property string $amount
 * @property bool $meal_breakfast
 * @property bool $meal_lunch
 * @property bool $meal_dinner
 * @property string $currency
 * @property string|null $notes
 */
class PerDiemDay extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<PerDiemDayFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'per_diem_trip_id',
        'date',
        'kind',
        'country',
        'per_diem_rate_id',
        'base_amount',
        'deduction_breakfast',
        'deduction_lunch',
        'deduction_dinner',
        'deductions_total',
        'amount',
        'meal_breakfast',
        'meal_lunch',
        'meal_dinner',
        'currency',
        'notes',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'date' => 'date',
        'kind' => PerDiemDayKind::class,
        'base_amount' => 'decimal:2',
        'deduction_breakfast' => 'decimal:2',
        'deduction_lunch' => 'decimal:2',
        'deduction_dinner' => 'decimal:2',
        'deductions_total' => 'decimal:2',
        'amount' => 'decimal:2',
        'meal_breakfast' => 'boolean',
        'meal_lunch' => 'boolean',
        'meal_dinner' => 'boolean',
    ];

    /** @return BelongsTo<PerDiemTrip, $this> */
    public function trip(): BelongsTo {
        return $this->belongsTo(PerDiemTrip::class, 'per_diem_trip_id');
    }

    /** @return BelongsTo<PerDiemRate, $this> */
    public function rate(): BelongsTo {
        return $this->belongsTo(PerDiemRate::class, 'per_diem_rate_id');
    }
}
