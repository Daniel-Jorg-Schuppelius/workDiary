<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicePaymentLink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Invoicing;

use App\Models\Concerns\{BelongsToOrganization, HasAccessToken};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stabiler Zahlungslink einer Rechnung (MVP-1067). Der Klartext liegt
 * verschlüsselt, weil PDF und Mail ihn bei jedem Rendern brauchen; aufgelöst
 * wird nur über den Abdruck.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $invoice_id
 * @property string $token
 * @property string $token_hash
 */
class InvoicePaymentLink extends Model {
    use BelongsToOrganization;
    use HasAccessToken;

    protected $fillable = ['organization_id', 'invoice_id', 'token', 'token_hash'];

    protected $hidden = ['token', 'token_hash'];

    /** @var array<string, string> */
    protected $casts = [
        'token' => 'encrypted',
    ];

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo {
        return $this->belongsTo(Invoice::class);
    }
}
