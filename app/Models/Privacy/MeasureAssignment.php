<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeasureAssignment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Privacy;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zuordnung einer TOM zu einer Verarbeitungstaetigkeit oder einem Vertrag (AVV).
 *
 * @property int $organization_id
 */
class MeasureAssignment extends Model {
    use BelongsToOrganization;

    protected $table = 'privacy_measure_assignments';

    protected $fillable = [
        'organization_id',
        'measure_id',
        'activity_id',
        'agreement_id',
    ];

    /** @return BelongsTo<TechnicalMeasure, $this> */
    public function measure(): BelongsTo {
        return $this->belongsTo(TechnicalMeasure::class, 'measure_id');
    }

    /** @return BelongsTo<ProcessingActivity, $this> */
    public function activity(): BelongsTo {
        return $this->belongsTo(ProcessingActivity::class, 'activity_id');
    }
}
