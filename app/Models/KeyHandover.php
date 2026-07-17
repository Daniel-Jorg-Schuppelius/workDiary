<?php
/*
 * Created on   : Thu May 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KeyHandover.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\KeyHandover\KeyHandoverDirection;
use App\Models\Concerns\{Auditable, BelongsToOrganization, Searchable};
use Database\Factories\KeyHandoverFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $asset_id
 * @property int|null $customer_id
 * @property KeyHandoverDirection $direction
 * @property string $person_name
 * @property string|null $person_reference
 * @property int|null $handed_by_user_id
 * @property int|null $returned_to_user_id
 * @property Carbon $occurred_at
 * @property Carbon|null $expected_return_at
 * @property string|null $notes
 * @property string|null $signature_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class KeyHandover extends Model {
    /** @use HasFactory<KeyHandoverFactory> */
    use Auditable, BelongsToOrganization, HasFactory;
    use Searchable;

    protected $fillable = [
        'organization_id',
        'asset_id',
        'customer_id',
        'direction',
        'person_name',
        'person_reference',
        'handed_by_user_id',
        'returned_to_user_id',
        'occurred_at',
        'expected_return_at',
        'notes',
        'signature_token',
    ];

    protected $casts = [
        'direction' => KeyHandoverDirection::class,
        'occurred_at' => 'datetime',
        'expected_return_at' => 'datetime',
    ];

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function handedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'handed_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function returnedTo(): BelongsTo {
        return $this->belongsTo(User::class, 'returned_to_user_id');
    }

    /** @return list<string> */
    protected function searchableColumns(): array {
        return ['person_name', 'person_reference'];
    }
}
