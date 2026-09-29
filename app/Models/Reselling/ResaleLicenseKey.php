<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleLicenseKey.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Reselling;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Schlüssel einer Einzellizenz (MVP-1024). Der Wert liegt verschlüsselt;
 * Dubletten findet der geschlüsselte Abdruck ({@see \App\Support\Crypto\BlindIndex}).
 * Beide Felder sind versteckt — damit fehlen sie in Serialisierung und Audit.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $unit_id
 * @property int $product_id
 * @property string $role
 * @property string $value
 * @property string $fingerprint
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read ResaleLicenseUnit $unit
 */
class ResaleLicenseKey extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'unit_id',
        'product_id',
        'role',
        'value',
        'fingerprint',
        'created_by',
        'updated_by',
    ];

    protected $hidden = ['value', 'fingerprint'];

    /** @var array<string, string> */
    protected $casts = [
        'value' => 'encrypted',
    ];

    /** @return BelongsTo<ResaleLicenseUnit, $this> */
    public function unit(): BelongsTo {
        return $this->belongsTo(ResaleLicenseUnit::class, 'unit_id');
    }
}
