<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierCredentialType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Supplier;

use App\Models\Concerns\{Auditable, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nachweistyp (Feature 117, MVP-606).
 *
 * Kein `BelongsToOrganization`: Katalogzeilen haben `organization_id = NULL`
 * und gelten installationsweit — ein globaler Scope würde sie ausblenden
 * (dasselbe Muster wie {@see \App\Models\AssetCompliance\AssetComplianceProfile}).
 * Die Org-Filterung übernehmen die Abfragen ausdrücklich.
 *
 * @property string $blocking_mode
 */
class SupplierCredentialType extends Model {
    use Auditable;
    use HasSqid;

    public const MODE_WARN = 'warn';

    public const MODE_BLOCK = 'block';

    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'default_validity_months',
        'warn_days_before',
        'blocking_mode',
        'is_required_default',
        'description',
        'frame_version',
        'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'default_validity_months' => 'integer',
        'warn_days_before' => 'integer',
        'is_required_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** @return HasMany<SupplierCredential, $this> */
    public function credentials(): HasMany {
        return $this->hasMany(SupplierCredential::class);
    }

    public function blocks(): bool {
        return $this->blocking_mode === self::MODE_BLOCK;
    }
}
