<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadlineProfile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Customer;

use App\Casts\FieldDocumentCast;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use App\Services\Fields\{FieldDocument, FieldValues};
use App\Support\Query\DateRange;
use Carbon\CarbonInterface;
use Database\Factories\Customer\CustomerDeadlineProfileFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Fristmerkmale eines Kunden für einen Regelsatz (Feature 197, MVP-1630):
 * Merkmale als Feldschema-Dokument (`traits`, Schema des Regelsatzes zum
 * Zeitpunkt der Erfassung + Werte), Gültigkeit und zuständige Person. Eine
 * Änderung legt eine neue Fassung an und schließt die alte mit dem Vortag ab —
 * nie überschreiben, damit vergangene Fristen ihre Grundlage behalten.
 * Geschrieben wird nur über {@see \App\Services\Customer\Deadlines\CustomerDeadlineProfileService}.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $customer_id
 * @property string $rule_set
 * @property FieldDocument|null $traits
 * @property Carbon $valid_from
 * @property Carbon|null $valid_until
 * @property int|null $responsible_user_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CustomerDeadlineProfile extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<CustomerDeadlineProfileFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'customer_id',
        'rule_set',
        'traits',
        'valid_from',
        'valid_until',
        'responsible_user_id',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'traits' => FieldDocumentCast::class,
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<CustomerDeadline, $this> */
    public function deadlines(): HasMany {
        return $this->hasMany(CustomerDeadline::class);
    }

    /** Werte der Merkmale (leer ohne Dokument). */
    public function traitValues(): FieldValues {
        return $this->traits !== null ? $this->traits->values : new FieldValues;
    }

    /**
     * Am Tag gültige Fassungen.
     *
     * @param  Builder<CustomerDeadlineProfile>  $query
     */
    public function scopeValidOn(Builder $query, CarbonInterface $day): void {
        $query->where('valid_from', '<', DateRange::dayAfter($day))
            ->where(static fn (Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', DateRange::day($day)));
    }

    /** Gilt die Fassung am Tag? */
    public function isValidOn(CarbonInterface $day): bool {
        $date = $day->toDateString();

        return $this->valid_from->toDateString() <= $date && ($this->valid_until === null || $this->valid_until->toDateString() >= $date);
    }
}
