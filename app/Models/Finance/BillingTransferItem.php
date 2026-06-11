<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingTransferItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};
use Illuminate\Support\Carbon;

/**
 * Quellnachweis je Übergabeposition (Feature 045): morphte Referenz auf
 * TimeEntry|MaterialUsage plus Snapshot von Menge/Betrag zum Übergabezeitpunkt.
 *
 * Bewusst OHNE BelongsToOrganization: Kind-Tabelle eines tenant-gebundenen
 * Aggregats — die Mandantengrenze wird transitiv über
 * billing_transfers.organization_id durchgesetzt (analog TimeExportLine);
 * Zugriff erfolgt ausschließlich über den BillingTransfer. Siehe Allow-List in
 * tests/Unit/Architecture/TenantTraitCoverageTest.php und
 * docs/security/tenant-audit-2026.md.
 *
 * @property int $id
 * @property int $billing_transfer_id
 * @property string $source_type
 * @property int $source_id
 * @property string|null $amount
 * @property string|null $quantity
 * @property Carbon|null $created_at
 */
class BillingTransferItem extends Model {
    public const UPDATED_AT = null;

    protected $fillable = [
        'billing_transfer_id',
        'source_type',
        'source_id',
        'amount',
        'quantity',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'amount' => 'decimal:2',
        'quantity' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<BillingTransfer, $this> */
    public function transfer(): BelongsTo {
        return $this->belongsTo(BillingTransfer::class, 'billing_transfer_id');
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo {
        return $this->morphTo();
    }
}
