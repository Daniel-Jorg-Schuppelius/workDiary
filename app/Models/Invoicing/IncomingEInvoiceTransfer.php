<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingEInvoiceTransfer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Invoicing;

use App\Enums\Invoicing\IncomingInvoiceTransferStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Übergabe eines Rechnungseingangs an ein Buchhaltungsziel (Feature 163,
 * MVP-1111): eine Zeile je Eingang und Ziel, mit externer Beleg-ID,
 * Versuchen und letzter Fehlermeldung.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $incoming_einvoice_id
 * @property string $target
 * @property IncomingInvoiceTransferStatus $status
 * @property string|null $external_id
 * @property string|null $external_number
 * @property int $attempts
 * @property string|null $error
 * @property \Illuminate\Support\Carbon|null $transferred_at
 */
class IncomingEInvoiceTransfer extends Model {
    use BelongsToOrganization;

    protected $table = 'incoming_einvoice_transfers';

    protected $fillable = [
        'organization_id', 'incoming_einvoice_id', 'target', 'status', 'external_id',
        'external_number', 'attempts', 'error', 'transferred_at',
    ];

    protected $attributes = [
        'status' => 'pending',
        'attempts' => 0,
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => IncomingInvoiceTransferStatus::class,
        'attempts' => 'integer',
        'transferred_at' => 'datetime',
    ];

    /** @return BelongsTo<IncomingEInvoice, $this> */
    public function incoming(): BelongsTo {
        return $this->belongsTo(IncomingEInvoice::class, 'incoming_einvoice_id');
    }
}
