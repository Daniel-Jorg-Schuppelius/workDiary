<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetComplianceRequirement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\AssetCompliance;

use App\Models\Concerns\HasSqid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Messbare Anforderung eines Prüfprofils (MVP-283): Grenzwerte je Merkmal.
 * Katalog-Kind (hängt am Profil, org NULL möglich) — Mandantengrenze läuft
 * transitiv über das Profil (Allow-List im TenantTraitCoverageTest).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $asset_compliance_profile_id
 * @property string $label
 * @property numeric-string|null $limit_min
 * @property numeric-string|null $limit_max
 * @property bool $is_mandatory
 */
class AssetComplianceRequirement extends Model {
    use HasSqid;

    protected $fillable = [
        'organization_id', 'asset_compliance_profile_id', 'code', 'label',
        'unit', 'limit_min', 'limit_max', 'is_mandatory',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'limit_min' => 'decimal:4',
        'limit_max' => 'decimal:4',
        'is_mandatory' => 'boolean',
    ];

    /** @return BelongsTo<AssetComplianceProfile, $this> */
    public function profile(): BelongsTo {
        return $this->belongsTo(AssetComplianceProfile::class, 'asset_compliance_profile_id');
    }
}
