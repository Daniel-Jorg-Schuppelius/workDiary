<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsSoftwareInstallation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Isms;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\Isms\IsmsSoftwareInstallationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Installation eines Softwareprodukts (Feature 044, MVP 1): WO läuft
 * welche Version? asset_ref ist im MVP bewusst Freitext (Server/Gerät/
 * Dienst, analog isms_risks.asset_ref) — kein FK auf assets.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $isms_software_product_id
 * @property string|null $installed_version
 * @property string|null $asset_ref
 * @property string|null $location
 * @property string|null $notes
 */
class IsmsSoftwareInstallation extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<IsmsSoftwareInstallationFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'isms_software_product_id',
        'installed_version',
        'asset_ref',
        'location',
        'notes',
    ];

    /** @return BelongsTo<IsmsSoftwareProduct, $this> */
    public function product(): BelongsTo {
        return $this->belongsTo(IsmsSoftwareProduct::class, 'isms_software_product_id');
    }
}
