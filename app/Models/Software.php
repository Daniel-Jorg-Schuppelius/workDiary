<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Software.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Software\{SoftwareKind, SoftwareLicenseType};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\SoftwareFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $vendor
 * @property SoftwareKind $kind
 * @property SoftwareLicenseType $license_type
 * @property string|null $default_version
 * @property string|null $notes
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Software extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<SoftwareFactory> */
    use HasFactory;

    use HasSqid;

    protected $table = 'software';

    protected $fillable = [
        'organization_id',
        'name',
        'vendor',
        'kind',
        'license_type',
        'default_version',
        'notes',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'kind' => SoftwareKind::class,
        'license_type' => SoftwareLicenseType::class,
        'is_active' => 'bool',
    ];

    /** @return HasMany<SoftwareInstallation, $this> */
    public function installations(): HasMany {
        return $this->hasMany(SoftwareInstallation::class);
    }

    public function displayName(): string {
        return $this->vendor ? "{$this->vendor} — {$this->name}" : $this->name;
    }
}
