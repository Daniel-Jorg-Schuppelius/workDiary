<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceDispatch.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zustellversuch einer Ausgangsrechnung (Feature 066, MVP-168): Kanal,
 * Empfänger, Format und Dateihash je Versand/Download — ein erneuter
 * Versand ist ein WEITERER Zustellversuch, nie eine neue Rechnung.
 * Technischer Status (queued/sent/failed) bleibt vom fachlichen
 * Empfang getrennt.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $invoice_id
 * @property string $channel
 * @property string|null $format
 * @property string $status
 * @property string|null $recipient
 * @property string|null $sha256
 * @property array<string, mixed>|null $meta
 * @property int|null $created_by
 */
class InvoiceDispatch extends Model {
    use BelongsToOrganization;
    use HasSqid;

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_DOWNLOAD = 'download';

    public const CHANNEL_PEPPOL = 'peppol';

    public const CHANNEL_STORAGE = 'storage';

    protected $fillable = [
        'organization_id', 'invoice_id', 'channel', 'format', 'status',
        'recipient', 'sha256', 'meta', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'meta' => 'array',
    ];

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo {
        return $this->belongsTo(Invoice::class);
    }
}
