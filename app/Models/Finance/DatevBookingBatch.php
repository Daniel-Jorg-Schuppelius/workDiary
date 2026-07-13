<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevBookingBatch.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Finance;

use App\Enums\Finance\DatevBatchStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * DATEV-Buchungsstapel (Feature 045, „Priorität 2 / Phase 3"): ein
 * Übergabe-Aggregat, das gestellte Rechnungen/Gutschriften und freigegebene
 * Spesen eines abgeschlossenen Zeitraums als prüfbaren DATEV-V700-
 * Buchungsstapel abbildet. Statuswechsel laufen ausschließlich über den
 * {@see \App\Services\Finance\DatevBookingService} und schreiben je Wechsel ein
 * {@see DatevBookingEvent} (revisionssichere Hash-Kette).
 *
 * Unveränderlich nach Finalisierung: ein bereits exportierter Stapel
 * (status = exported) darf nicht mehr gespeichert/gelöscht werden (Guard in
 * booted()) — analog BillingTransfer (transferred) / AuditPackage.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $batch_no
 * @property Carbon $period_from
 * @property Carbon $period_to
 * @property DatevBatchStatus $status
 * @property string $skr
 * @property int $advisor_number
 * @property int $client_number
 * @property string|null $file_path
 * @property string|null $file_hash
 * @property int $booking_count
 * @property string $total_amount
 * @property bool $finalized_locked
 * @property string $selection_mode all|manual — Zuschnitt des Export-Laufs (MVP-334)
 * @property int|null $created_by_user_id
 * @property Carbon|null $finalized_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DatevBookingSource> $sources
 * @property-read \Illuminate\Database\Eloquent\Collection<int, DatevBookingEvent> $events
 */
class DatevBookingBatch extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'batch_no',
        'period_from',
        'period_to',
        'status',
        'skr',
        'advisor_number',
        'client_number',
        'file_path',
        'file_hash',
        'booking_count',
        'total_amount',
        'finalized_locked',
        'selection_mode',
        'created_by_user_id',
        'finalized_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'status' => DatevBatchStatus::class,
        'advisor_number' => 'integer',
        'client_number' => 'integer',
        'booking_count' => 'integer',
        'total_amount' => 'decimal:2',
        'finalized_locked' => 'boolean',
        'finalized_at' => 'datetime',
    ];

    protected static function booted(): void {
        static::updating(function (self $batch): void {
            // Unveränderlich nach Finalisierung: ein exportierter Stapel darf
            // nicht mehr geändert werden. Der Service setzt den Status
            // innerhalb der finalize()-Transaktion (saving auf draft → exported
            // ist erlaubt, weil der ORIGINAL-Wert noch draft ist).
            $original = $batch->getOriginal('status');
            if ($original === DatevBatchStatus::Exported->value || $original === DatevBatchStatus::Exported) {
                throw new \RuntimeException('DatevBookingBatch ist nach Finalisierung unveränderlich.');
            }
        });

        static::deleting(function (self $batch): void {
            if ($batch->isFinal() && ! $batch->isForceDeleting()) {
                throw new \RuntimeException('Ein finalisierter DatevBookingBatch darf nicht gelöscht werden.');
            }
        });
    }

    /** @return HasMany<DatevBookingSource, $this> */
    public function sources(): HasMany {
        return $this->hasMany(DatevBookingSource::class);
    }

    /** @return HasMany<DatevBookingEvent, $this> */
    public function events(): HasMany {
        return $this->hasMany(DatevBookingEvent::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isFinal(): bool {
        return $this->status->isFinal();
    }

    /** @return Factory<DatevBookingBatch> */
    protected static function newFactory(): Factory {
        return \Database\Factories\Finance\DatevBookingBatchFactory::new();
    }
}
