<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TechnicalMeasureVersion.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Privacy;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unveraenderlicher Snapshot einer TOM zum Freigabezeitpunkt. Diese Version wird
 * auch in den TOM-Snapshot freigegebener VVT-/Vertragsversionen uebernommen.
 *
 * @property array<string, mixed> $payload
 */
class TechnicalMeasureVersion extends Model {
    use BelongsToOrganization;

    // Audit 2026-08 (W3.3): Formulare/URLs tragen Sqids, nie rohe IDs.
    use HasSqid;

    protected $table = 'privacy_technical_measure_versions';

    protected $fillable = [
        'organization_id',
        'measure_id',
        'version_no',
        'payload',
        'note',
        'created_by',
        'approved_by',
        'approved_at',
        'valid_from',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'payload' => 'array',
        'approved_at' => 'datetime',
        'valid_from' => 'date',
    ];

    /** @return BelongsTo<TechnicalMeasure, $this> */
    public function measure(): BelongsTo {
        return $this->belongsTo(TechnicalMeasure::class, 'measure_id');
    }
}
