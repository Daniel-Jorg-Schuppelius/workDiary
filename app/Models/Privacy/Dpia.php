<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Dpia.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Privacy;

use App\Enums\Privacy\DpiaOutcome;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Datenschutz-Folgenabschaetzung (DSFA, Art. 35) zu einer risikoreichen
 * Verarbeitungstaetigkeit: Notwendigkeit, Risiken, Abhilfen, Restrisiko, Ergebnis.
 *
 * @property int $id
 * @property int $organization_id
 */
class Dpia extends Model {
    use BelongsToOrganization;

    protected $table = 'privacy_dpias';

    protected $fillable = [
        'organization_id',
        'activity_id',
        'necessity',
        'risks',
        'mitigations',
        'residual_risk',
        'outcome',
        'assessed_by',
        'assessed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'outcome' => DpiaOutcome::class,
        'assessed_at' => 'datetime',
    ];

    /** @return BelongsTo<ProcessingActivity, $this> */
    public function activity(): BelongsTo {
        return $this->belongsTo(ProcessingActivity::class, 'activity_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<DpiaStep, $this> */
    public function steps(): \Illuminate\Database\Eloquent\Relations\HasMany {
        return $this->hasMany(DpiaStep::class, 'dpia_id')->orderBy('position');
    }
}
