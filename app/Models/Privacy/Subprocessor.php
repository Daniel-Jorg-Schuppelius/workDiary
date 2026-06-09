<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Subprocessor.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Privacy;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unterauftragsverarbeiter unter einem AVV (Art. 28 Abs. 4). `approved` steuert
 * die Aenderungsuebersicht (neue Unterauftragsverarbeiter zur Freigabe).
 *
 * @property int $id
 * @property int $organization_id
 */
class Subprocessor extends Model {
    use BelongsToOrganization;

    protected $table = 'privacy_subprocessors';

    protected $fillable = [
        'organization_id',
        'agreement_id',
        'name',
        'purpose',
        'location',
        'third_country',
        'approved',
        'added_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'third_country' => 'boolean',
        'approved' => 'boolean',
        'added_at' => 'datetime',
    ];

    /** @return BelongsTo<ProcessingAgreement, $this> */
    public function agreement(): BelongsTo {
        return $this->belongsTo(ProcessingAgreement::class, 'agreement_id');
    }
}
