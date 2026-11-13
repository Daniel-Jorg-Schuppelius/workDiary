<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Customer;

use App\Enums\Period\PeriodKind;
use App\Models\Classification\EntryType;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use App\Support\Period\PeriodKey;
use App\Support\Query\DateRange;
use Carbon\CarbonInterface;
use Database\Factories\Customer\CustomerServiceFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Leistungsumfang eines Kunden (Feature 197, MVP-1630): eine Leistung ist
 * Eintragsart + Produktgruppe (leer = jede), Pflicht oder Wahl, mit Periodenart
 * und Gültigkeit. Gelesen von den Fristen-Regelsätzen (welche
 * Jahreserklärungen, ob Lohn) und vom Leistungsplan (Feature 199). Eine
 * beendete Leistung bekommt `valid_until`, sie wird nicht gelöscht.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $customer_id
 * @property int $entry_type_id
 * @property string|null $product_group_code
 * @property bool $is_mandatory
 * @property PeriodKind $period_kind
 * @property Carbon $valid_from
 * @property Carbon|null $valid_until
 * @property string|null $note
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CustomerService extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<CustomerServiceFactory> */
    use HasFactory;
    use HasSqid;

    protected $table = 'customer_services';

    protected $fillable = [
        'organization_id',
        'customer_id',
        'entry_type_id',
        'product_group_code',
        'is_mandatory',
        'period_kind',
        'valid_from',
        'valid_until',
        'note',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_mandatory' => 'boolean',
        'period_kind' => PeriodKind::class,
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<EntryType, $this> */
    public function entryType(): BelongsTo {
        return $this->belongsTo(EntryType::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Gilt an mindestens einem Tag zwischen $from und $to (je einschließlich).
     * DATE-Grenzen halboffen gegen den Folgetag (SQLite speichert `Y-m-d 00:00:00`).
     *
     * @param  Builder<CustomerService>  $query
     */
    public function scopeValidBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): void {
        $query->where('valid_from', '<', DateRange::dayAfter($to))
            ->where(static fn (Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', DateRange::day($from)));
    }

    /** Gilt die Leistung in der Periode (an mindestens einem Tag)? */
    public function coversPeriod(PeriodKey $period): bool {
        return $this->valid_from->toDateString() <= $period->end()->toDateString()
            && ($this->valid_until === null || $this->valid_until->toDateString() >= $period->start()->toDateString());
    }

    /** Läuft die Leistung am Tag (Vorgabe: heute)? */
    public function isActiveOn(?CarbonInterface $day = null): bool {
        $date = ($day ?? Carbon::today())->toDateString();

        return $this->valid_from->toDateString() <= $date && ($this->valid_until === null || $this->valid_until->toDateString() >= $date);
    }
}
