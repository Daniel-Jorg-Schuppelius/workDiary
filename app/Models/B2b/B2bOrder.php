<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : B2bOrder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\B2b;

use App\Casts\MoneyCast;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{Customer, DiaryEntry, User};
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Spiegel einer eingegangenen openTRANS-2.1-ORDER-Bestellung (Feature 099,
 * MVP-458). Bestellungen erscheinen ausschließlich als Inbox-Vorschlag der
 * Integrations-Drehscheibe; erst die Buchung erzeugt den Auftrag
 * ({@see DiaryEntry}). Idempotenz über (organization, external_order_id,
 * buyer_key) — eine Wiederanlieferung erzeugt keinen zweiten Datensatz.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $access_id
 * @property int|null $customer_id
 * @property string $external_order_id
 * @property string $buyer_key
 * @property array<string, mixed>|null $buyer
 * @property CurrencyCode $currency
 * @property \CommonToolkit\ValueObjects\Money|null $total_net
 * @property array<int, array<string, mixed>> $lines
 * @property string $source
 * @property string $status
 * @property Carbon|null $ordered_at
 * @property Carbon|null $requested_delivery_date
 * @property int|null $diary_entry_id
 * @property int|null $booked_by
 * @property Carbon|null $booked_at
 */
class B2bOrder extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    public const STATUS_OPEN = 'open';
    public const STATUS_BOOKED = 'booked';
    public const STATUS_DISMISSED = 'dismissed';

    public const SOURCE_UPLOAD = 'upload';
    public const SOURCE_MAIL = 'mail';
    public const SOURCE_CLOUD = 'cloud';

    protected $fillable = [
        'organization_id',
        'access_id',
        'customer_id',
        'external_order_id',
        'buyer_key',
        'buyer',
        'currency',
        'total_net',
        'lines',
        'source',
        'status',
        'ordered_at',
        'requested_delivery_date',
        'diary_entry_id',
        'booked_by',
        'booked_at',
    ];

    protected $casts = [
        'buyer' => 'array',
        'currency' => CurrencyCode::class,
        'total_net' => MoneyCast::class . ':currency,2',
        'lines' => 'array',
        'ordered_at' => 'datetime',
        'requested_delivery_date' => 'date',
        'booked_at' => 'datetime',
    ];

    public function isOpen(): bool {
        return $this->status === self::STATUS_OPEN;
    }

    /** @param Builder<B2bOrder> $query */
    public function scopeOpen(Builder $query): void {
        $query->where('status', self::STATUS_OPEN);
    }

    /** @return BelongsTo<B2bCatalogAccess, $this> */
    public function access(): BelongsTo {
        return $this->belongsTo(B2bCatalogAccess::class, 'access_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<DiaryEntry, $this> */
    public function diaryEntry(): BelongsTo {
        return $this->belongsTo(DiaryEntry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function booker(): BelongsTo {
        return $this->belongsTo(User::class, 'booked_by');
    }
}
