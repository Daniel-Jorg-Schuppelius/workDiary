<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeInvoiceHandover.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Plugins\Lexoffice;

use App\Enums\Lexoffice\LexofficeHandoverStatus;
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Übergabestand eines lokal ausgestellten Belegs an Lexware (Feature 158,
 * MVP-833). Genau ein Datensatz je Rechnung; der Status ist vom Rechnungs-
 * und Versandstatus getrennt. Fachlogik im
 * {@see \App\Plugins\Lexoffice\Handover\LexofficeInvoiceHandoverService}.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $invoice_id
 * @property string $channel
 * @property LexofficeHandoverStatus $status
 * @property string|null $invoice_number
 * @property string|null $document_sha256
 * @property \Illuminate\Support\Carbon|null $exported_at
 * @property int|null $exported_by
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property int|null $confirmed_by
 * @property string|null $confirmation_note
 * @property string|null $external_file_id
 * @property string|null $external_voucher_id
 * @property int $attempts
 * @property string|null $last_error
 */
class LexofficeInvoiceHandover extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'invoice_id', 'channel', 'status', 'invoice_number', 'document_sha256',
        'exported_at', 'exported_by', 'confirmed_at', 'confirmed_by', 'confirmation_note',
        'external_file_id', 'external_voucher_id', 'attempts', 'last_error',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => LexofficeHandoverStatus::class,
        'exported_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'attempts' => 'integer',
    ];

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function exporter(): BelongsTo {
        return $this->belongsTo(User::class, 'exported_by');
    }

    /** @return BelongsTo<User, $this> */
    public function confirmer(): BelongsTo {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
