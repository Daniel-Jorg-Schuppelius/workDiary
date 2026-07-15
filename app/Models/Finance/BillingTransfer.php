<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingTransfer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Finance;

use App\Enums\Finance\{TransferChannel, TransferStatus, TransferTarget};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{Customer, ExternalReference, User};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Übergabenachweis (Feature 045): dokumentiert revisionsnah, welche Quellen
 * (Zeit ODER Material, nie gemischt) wann an welches Fakturierungsziel
 * übergeben wurden. Statusmaschine siehe {@see TransferStatus}; Übergänge
 * laufen ausschließlich über {@see \App\Services\Finance\BillingTransferService}
 * und schreiben je Statuswechsel ein {@see BillingTransferEvent} (Hash-Kette).
 *
 * Unveränderlich nach Übergabe: ein transferierter Nachweis (transferred/
 * transferred_at gesetzt) darf nicht mehr gespeichert/gelöscht werden (Guard
 * in booted()) — analog DatevBookingBatch (exported); `Transferred` ist in der
 * Statusmaschine terminal, Korrekturen laufen über Storno-/Differenzübergaben.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $customer_id
 * @property TransferChannel $channel
 * @property TransferTarget $target
 * @property TransferStatus $status
 * @property Carbon|null $period_from
 * @property Carbon|null $period_to
 * @property int $position_count
 * @property string|null $total_amount
 * @property string|null $total_quantity
 * @property string $payload_hash
 * @property int|null $external_reference_id
 * @property string|null $file_path
 * @property int|null $created_by_user_id
 * @property Carbon|null $transferred_at
 * @property string|null $failure_reason
 * @property-read \Illuminate\Database\Eloquent\Collection<int, BillingTransferItem> $items
 * @property-read \Illuminate\Database\Eloquent\Collection<int, BillingTransferEvent> $events
 * @property-read Customer $customer
 */
class BillingTransfer extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'customer_id',
        'channel',
        'target',
        'status',
        'period_from',
        'period_to',
        'position_count',
        'total_amount',
        'total_quantity',
        'payload_hash',
        'external_reference_id',
        'file_path',
        'created_by_user_id',
        'transferred_at',
        'failure_reason',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'channel' => TransferChannel::class,
        'target' => TransferTarget::class,
        'status' => TransferStatus::class,
        'period_from' => 'date',
        'period_to' => 'date',
        'position_count' => 'integer',
        'total_amount' => 'decimal:2',
        'total_quantity' => 'decimal:2',
        'transferred_at' => 'datetime',
    ];

    protected static function booted(): void {
        static::updating(function (self $transfer): void {
            // Unveränderlich nach Übergabe: der Service setzt transferred_at
            // innerhalb der markTransferred()-Transaktion (confirmed →
            // transferred ist erlaubt, weil der ORIGINAL-Wert noch leer ist).
            $originalStatus = $transfer->getOriginal('status');
            if ($transfer->getOriginal('transferred_at') !== null
                || $originalStatus === TransferStatus::Transferred
                || $originalStatus === TransferStatus::Transferred->value) {
                throw new \RuntimeException('BillingTransfer ist nach erfolgter Übergabe unveränderlich.');
            }
        });

        static::deleting(function (self $transfer): void {
            if ($transfer->wasTransferred() && ! $transfer->isForceDeleting()) {
                throw new \RuntimeException('Ein übergebener BillingTransfer darf nicht gelöscht werden.');
            }
        });
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<BillingTransferItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(BillingTransferItem::class);
    }

    /** @return HasMany<BillingTransferEvent, $this> */
    public function events(): HasMany {
        return $this->hasMany(BillingTransferEvent::class);
    }

    /** @return BelongsTo<ExternalReference, $this> */
    public function externalReference(): BelongsTo {
        return $this->belongsTo(ExternalReference::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** Wurde der Transfer jemals erfolgreich übergeben? (Quellen verbraucht) */
    public function wasTransferred(): bool {
        return $this->transferred_at !== null || $this->status === TransferStatus::Transferred;
    }

    /** @return Factory<BillingTransfer> */
    protected static function newFactory(): Factory {
        return \Database\Factories\Finance\BillingTransferFactory::new();
    }
}
