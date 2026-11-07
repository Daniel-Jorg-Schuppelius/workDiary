<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInvoicePosition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Finance;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zuordnung einer eingefrorenen Übergabeposition zu einer extern ausgestellten
 * Rechnung (Feature 196). Je Position genau eine Rechnung; so lassen sich
 * geteilte und gebündelte Übergaben nachvollziehen.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $external_invoice_id
 * @property int $billing_transfer_id
 * @property int $billing_transfer_position_id
 * @property int|null $created_by
 * @property-read ExternalInvoice $externalInvoice
 * @property-read BillingTransfer $transfer
 * @property-read BillingTransferPosition $position
 */
class ExternalInvoicePosition extends Model {
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'external_invoice_id', 'billing_transfer_id', 'billing_transfer_position_id', 'created_by'];

    /** @return BelongsTo<ExternalInvoice, $this> */
    public function externalInvoice(): BelongsTo {
        return $this->belongsTo(ExternalInvoice::class);
    }

    /** @return BelongsTo<BillingTransfer, $this> */
    public function transfer(): BelongsTo {
        return $this->belongsTo(BillingTransfer::class, 'billing_transfer_id');
    }

    /** @return BelongsTo<BillingTransferPosition, $this> */
    public function position(): BelongsTo {
        return $this->belongsTo(BillingTransferPosition::class, 'billing_transfer_position_id');
    }
}
