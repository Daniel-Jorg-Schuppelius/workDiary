<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceSenderRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Invoicing;

use App\Enums\Billing\DocumentDirection;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Customer\Customer;
use App\Models\Supplier\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Absenderregel des Rechnungspostfachs (Feature 163, MVP-1108): Rechnungen
 * dieser Mailadresse gehören zu dieser Partei. Entsteht nur durch die
 * ausdrückliche Wahl „Absender merken“ und gilt nur, wenn der Beleg selbst
 * keine Partei eindeutig nennt.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $email
 * @property DocumentDirection $direction
 * @property int|null $supplier_id
 * @property int|null $customer_id
 * @property int|null $created_by
 */
class InvoiceSenderRule extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'email', 'direction', 'supplier_id', 'customer_id', 'created_by'];

    /** @var array<string, string> */
    protected $casts = [
        'direction' => DocumentDirection::class,
    ];

    /** Vergleichsform einer Mailadresse. */
    public static function normalize(string $email): string {
        return mb_strtolower(trim($email));
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }
}
