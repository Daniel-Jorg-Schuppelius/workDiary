<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SoftwareInstallation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Database\Factories\SoftwareInstallationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $asset_id
 * @property int $software_id
 * @property string|null $version
 * @property string|null $license_key
 * @property int|null $seats
 * @property Carbon|null $installed_on
 * @property Carbon|null $expires_on
 * @property bool $is_operating_system
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SoftwareInstallation extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<SoftwareInstallationFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'asset_id',
        'software_id',
        'version',
        'license_key',
        'seats',
        'installed_on',
        'expires_on',
        'is_operating_system',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'installed_on' => 'date',
        'expires_on' => 'date',
        'is_operating_system' => 'bool',
        'license_key' => 'encrypted',
        'seats' => 'int',
    ];

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<Software, $this> */
    public function software(): BelongsTo {
        return $this->belongsTo(Software::class);
    }
}
