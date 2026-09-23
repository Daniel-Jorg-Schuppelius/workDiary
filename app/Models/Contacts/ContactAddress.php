<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactAddress.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Polymorphe Adresse eines Kontakts (Customer/Supplier). Bildet die
 * Lexoffice-Adressen (billing/shipping) strukturiert ab.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $addressable_type
 * @property int $addressable_id
 * @property string $kind
 * @property string|null $supplement
 * @property string|null $street
 * @property string|null $zip
 * @property string|null $city
 * @property string|null $country_code
 * @property bool $is_primary
 * @property string|null $external_id
 */
class ContactAddress extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    public const KIND_BILLING = 'billing';

    public const KIND_SHIPPING = 'shipping';

    public const KIND_DEFAULT = 'default';

    protected $fillable = [
        'organization_id',
        'addressable_type',
        'addressable_id',
        'kind',
        'supplement',
        'street',
        'zip',
        'city',
        'country_code',
        'is_primary',
        'external_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_primary' => 'boolean',
        // Vollständige Adress-PII at-rest verschlüsselt. PLZ/Ort werden nirgends
        // gefiltert/sortiert, daher ebenfalls verschlüsselbar.
        'street' => 'encrypted',
        'supplement' => 'encrypted',
        'zip' => 'encrypted',
        'city' => 'encrypted',
    ];

    /** @return MorphTo<Model, $this> */
    public function addressable(): MorphTo {
        return $this->morphTo();
    }
}
