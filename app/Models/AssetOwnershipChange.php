<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetOwnershipChange.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Asset\AssetOwnership;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eine unveränderliche Zeile der Eigentümerwechsel-Historie eines Assets
 * (Feature 027 → Rang 49). Wird ausschließlich über den
 * {@see \App\Services\Asset\AssetLifecycleService} geschrieben; es gibt bewusst
 * keinen Update-/Bearbeitungsweg (append-only Nachweis).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $asset_id
 * @property AssetOwnership|null $from_ownership
 * @property AssetOwnership $to_ownership
 * @property int|null $from_customer_id
 * @property int|null $to_customer_id
 * @property string|null $note
 * @property int|null $changed_by_user_id
 * @property Carbon $changed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AssetOwnershipChange extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'asset_id',
        'from_ownership',
        'to_ownership',
        'from_customer_id',
        'to_customer_id',
        'note',
        'changed_by_user_id',
        'changed_at',
    ];

    protected $casts = [
        'from_ownership' => AssetOwnership::class,
        'to_ownership' => AssetOwnership::class,
        'changed_at' => 'datetime',
    ];

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function toCustomer(): BelongsTo {
        return $this->belongsTo(Customer::class, 'to_customer_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function fromCustomer(): BelongsTo {
        return $this->belongsTo(Customer::class, 'from_customer_id');
    }
}
