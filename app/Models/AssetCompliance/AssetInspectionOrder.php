<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetInspectionOrder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\AssetCompliance;

use App\Enums\AssetCompliance\AssetInspectionOrderStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Supplier\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Prüfauftrag an einen Dienstleister (MVP-938): Angebot und Ergebnisse
 * kommen über einen Link (nur als Hash gespeichert).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $supplier_id
 * @property string $title
 * @property AssetInspectionOrderStatus $status
 * @property string $token_hash
 * @property string $recipient_email
 * @property Carbon $expires_at
 * @property numeric-string|null $offer_amount
 * @property string|null $currency
 * @property Carbon|null $offer_planned_on
 * @property string|null $offer_note
 * @property Carbon|null $offered_at
 * @property Carbon|null $accepted_at
 * @property int|null $accepted_by
 * @property Carbon|null $reported_at
 * @property Carbon|null $completed_at
 * @property string|null $note
 * @property int|null $created_by
 */
class AssetInspectionOrder extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'supplier_id', 'title', 'status', 'token_hash', 'recipient_email', 'expires_at', 'offer_amount', 'currency',
        'offer_planned_on', 'offer_note', 'offered_at', 'accepted_at', 'accepted_by', 'reported_at', 'completed_at', 'note', 'created_by',
    ];

    /** @var list<string> */
    protected $hidden = ['token_hash'];

    /** @var array<string, string> */
    protected $casts = [
        'status' => AssetInspectionOrderStatus::class,
        'expires_at' => 'datetime',
        'offer_amount' => 'decimal:2',
        'offer_planned_on' => 'date',
        'offered_at' => 'datetime',
        'accepted_at' => 'datetime',
        'reported_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<AssetInspectionOrderItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(AssetInspectionOrderItem::class);
    }
}
