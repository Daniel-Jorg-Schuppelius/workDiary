<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommissionSettlementRun.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Sales;

use App\Casts\MoneyCast;
use App\Enums\Sales\CommissionSettlementStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\{Collection, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Provisions-Abrechnungslauf je Periode (Feature 146, MVP-729).
 *
 * Der Entwurf ist die **Vorschau**: er sammelt alle offenen Provisionszeilen
 * der Periode und laesst sich beliebig oft neu berechnen. Das Schliessen
 * schreibt fest — danach aendert sich an den Zeilen des Laufs nichts mehr,
 * auch nicht bei einem spaeteren Storno; der mindert ueber eine Rueckrechnung
 * im Lauf der Folgeperiode.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $period
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property CommissionSettlementStatus $status
 * @property CurrencyCode $currency
 * @property Money $total_base
 * @property Money $total_commission
 * @property int $entry_count
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property string|null $note
 * @property int|null $created_by
 * @property-read Collection<int, InvoiceCommission> $commissions
 */
class CommissionSettlementRun extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'commission_settlement_runs';

    protected $fillable = [
        'organization_id',
        'period',
        'period_start',
        'period_end',
        'status',
        'currency',
        'total_base',
        'total_commission',
        'entry_count',
        'closed_at',
        'closed_by',
        'note',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'status' => CommissionSettlementStatus::class,
        'currency' => CurrencyCode::class,
        'total_base' => MoneyCast::class,
        'total_commission' => MoneyCast::class,
        'entry_count' => 'integer',
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void {
        // Festschreibung (Feature 146): ein geschlossener Lauf ist der Beleg
        // gegenueber der Lohnabrechnung. Danach sind nur noch Notiz und
        // Zeitstempel-Spalten beweglich — inhaltliche Korrekturen laufen
        // ausschliesslich ueber eine Rueckrechnung im naechsten Lauf.
        static::updating(function (self $run): void {
            if ($run->getRawOriginal('status') !== CommissionSettlementStatus::Closed->value) {
                return;
            }
            $blocked = array_diff(array_keys($run->getDirty()), ['note', 'updated_at']);
            if ($blocked !== []) {
                throw new RuntimeException(
                    'Geschlossene Provisionslaeufe sind festgeschrieben (Felder: ' . implode(', ', $blocked) . ').',
                );
            }
        });

        static::deleting(function (self $run): void {
            if ($run->status === CommissionSettlementStatus::Closed) {
                throw new RuntimeException('Geschlossene Provisionslaeufe koennen nicht geloescht werden.');
            }
        });
    }

    /** @return HasMany<InvoiceCommission, $this> */
    public function commissions(): HasMany {
        return $this->hasMany(InvoiceCommission::class, 'settlement_run_id');
    }

    /** @return BelongsTo<User, $this> */
    public function closer(): BelongsTo {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isClosed(): bool {
        return $this->status === CommissionSettlementStatus::Closed;
    }
}
