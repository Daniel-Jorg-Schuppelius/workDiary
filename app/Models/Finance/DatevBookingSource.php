<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevBookingSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Finance;

use App\Models\Concerns\HasSqid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};
use Illuminate\Support\Carbon;

/**
 * Quellnachweis je Buchungssatz (Feature 045): morphte Referenz auf
 * Invoice|Expense plus Buchungs-Snapshot (Debitor-/Erlöskonto, Soll/Haben,
 * Bruttobetrag, BU-Schlüssel, Belegfeld). Über diese Tabelle läuft der Schutz
 * vor Doppel-Übergabe: dieselbe Quelle darf nicht in zwei finalisierten/
 * exportierten Stapeln hängen (Service-Check via Exists).
 *
 * Bewusst OHNE BelongsToOrganization: Kind-Tabelle eines tenant-gebundenen
 * Aggregats — die Mandantengrenze wird transitiv über
 * datev_booking_batches.organization_id durchgesetzt (analog
 * BillingTransferItem); Zugriff erfolgt ausschließlich über den Batch. Siehe
 * Allow-List in tests/Unit/Architecture/TenantTraitCoverageTest.php.
 *
 * @property int $id
 * @property int $datev_booking_batch_id
 * @property string $source_type
 * @property int $source_id
 * @property string $debtor_account
 * @property string $revenue_account
 * @property string $soll_haben
 * @property string $amount
 * @property string|null $tax_key
 * @property string|null $document_ref
 * @property bool $is_reversal Generalumkehr-Satz (Storno-Übergabe, MVP-334)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DatevBookingSource extends Model {
    // Audit 2026-08 (W3.3): Formulare tragen Sqids, nie rohe IDs.
    use HasSqid;

    protected $fillable = [
        'datev_booking_batch_id',
        'source_type',
        'source_id',
        'debtor_account',
        'revenue_account',
        'soll_haben',
        'amount',
        'tax_key',
        'document_ref',
        'is_reversal',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'amount' => 'decimal:2',
        'is_reversal' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return BelongsTo<DatevBookingBatch, $this> */
    public function batch(): BelongsTo {
        return $this->belongsTo(DatevBookingBatch::class, 'datev_booking_batch_id');
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo {
        return $this->morphTo();
    }
}
