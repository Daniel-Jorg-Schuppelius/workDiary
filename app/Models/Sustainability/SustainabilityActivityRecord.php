<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityActivityRecord.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Sustainability;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Aktivitätsdatensatz (Feature 071, MVP-227): Verbrauch/Menge je Zeitraum
 * mit Datenqualität — Schätzwerte sind IMMER gekennzeichnet und
 * erscheinen nie als Messwerte (Greenwashing-Schutz).
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $subject_label
 * @property string $activity_code
 * @property string $amount
 * @property string $unit
 * @property \Illuminate\Support\Carbon $period_start
 * @property \Illuminate\Support\Carbon $period_end
 * @property string $data_quality
 * @property string|null $source_note
 */
class SustainabilityActivityRecord extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const ACTIVITY_CODES = ['electricity_kwh', 'heat_kwh', 'gas_kwh', 'diesel_l', 'petrol_l', 'km_car', 'km_truck', 'waste_kg', 'water_m3', 'material_kg', 'service_eur'];

    public const QUALITIES = ['measured', 'calculated', 'estimated'];

    protected $fillable = [
        'organization_id', 'subject_type', 'subject_id', 'subject_label',
        'activity_code', 'amount', 'unit', 'period_start', 'period_end',
        'data_quality', 'source_note', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'amount' => 'decimal:3',
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo {
        return $this->morphTo();
    }
}
