<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeeAccount.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Club;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Beitragskonto (MVP-849): die zahlungspflichtige Person im Kunden-/Debitoren-
 * stamm — ein Elternteil kann mehrere Kinder bezahlen, ohne Mitglied zu sein.
 * Zuordnung immer explizit, nie anhand gleicher E-Mail oder IBAN.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $customer_id
 * @property string $name
 * @property string|null $email
 * @property string|null $notes
 * @property int|null $user_id
 * @property int|null $sepa_mandate_id
 */
class ClubFeeAccount extends Model {
    use Auditable;

    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'customer_id', 'name', 'email', 'notes', 'user_id', 'sepa_mandate_id'];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<ClubFeeAssignment, $this> */
    public function assignments(): HasMany {
        return $this->hasMany(ClubFeeAssignment::class)->orderByDesc('valid_from');
    }
}
