<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactBankAccount.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Polymorphe Bankverbindung eines Kontakts (Customer/Supplier).
 *
 * Lexoffice exponiert Bankdaten nicht über die Contact-API — diese Daten
 * sind daher lokal/push-führend.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $accountable_type
 * @property int $accountable_id
 * @property string|null $account_holder
 * @property string|null $iban
 * @property string|null $bic
 * @property string|null $bank_name
 * @property bool $is_primary
 * @property string|null $external_id
 */
class ContactBankAccount extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'accountable_type',
        'accountable_id',
        'account_holder',
        'iban',
        'bic',
        'bank_name',
        'is_primary',
        'external_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_primary' => 'boolean',
    ];

    /** @return MorphTo<Model, $this> */
    public function accountable(): MorphTo {
        return $this->morphTo();
    }
}
