<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeasureReview.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Privacy;

use App\Enums\Privacy\ReviewResult;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dokumentierte Wirksamkeitspruefung einer TOM (Ergebnis, Abweichung,
 * Folgemassnahme, Fälligkeit).
 *
 * @property int $organization_id
 */
class MeasureReview extends Model {
    use BelongsToOrganization;

    protected $table = 'privacy_measure_reviews';

    protected $fillable = [
        'organization_id',
        'measure_id',
        'reviewed_at',
        'result',
        'deviation',
        'follow_up',
        'due_at',
        'reviewer_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'result' => ReviewResult::class,
        'reviewed_at' => 'datetime',
        'due_at' => 'date',
    ];

    /** @return BelongsTo<TechnicalMeasure, $this> */
    public function measure(): BelongsTo {
        return $this->belongsTo(TechnicalMeasure::class, 'measure_id');
    }
}
