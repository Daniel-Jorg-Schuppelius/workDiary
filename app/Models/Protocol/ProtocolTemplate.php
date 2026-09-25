<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolTemplate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Protocol;

use App\Enums\Protocol\ProtocolType;
use App\Models\Classification\EntryType;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Customer\Customer;
use App\Support\Query\DateRange;
use Database\Factories\Protocol\ProtocolTemplateFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Protokollvorlage (MVP-901): Punkte als Liste
 * `{label, item_type, description, required, config, children[]}`, optional
 * eingeschränkt auf Auftragsart und Kunde. Ein neues Protokoll kopiert die
 * Punkte; spätere Änderungen erhöhen die Version und lassen bestehende
 * Protokolle unberührt.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property ProtocolType $kind
 * @property string|null $description
 * @property list<array<string, mixed>> $items
 * @property int|null $entry_type_id
 * @property int|null $customer_id
 * @property int $version
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $valid_from
 * @property \Illuminate\Support\Carbon|null $valid_until
 */
class ProtocolTemplate extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<ProtocolTemplateFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'name',
        'kind',
        'description',
        'items',
        'entry_type_id',
        'customer_id',
        'version',
        'is_active',
        'valid_from',
        'valid_until',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'kind' => ProtocolType::class,
        'items' => 'array',
        'is_active' => 'bool',
        'version' => 'int',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    /** @return BelongsTo<EntryType, $this> */
    public function entryType(): BelongsTo {
        return $this->belongsTo(EntryType::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Aktiv und heute gültig; ohne Zeitraum unbefristet.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUsable(Builder $query): Builder {
        $tomorrow = DateRange::dayAfter(now());

        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhere('valid_from', '<', $tomorrow))
            ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()->toDateString()));
    }

    /** Leere Zuordnung passt überall; sonst müssen Auftragsart und Kunde stimmen. */
    public function matches(?int $entryTypeId, ?int $customerId): bool {
        if ($this->entry_type_id !== null && $this->entry_type_id !== $entryTypeId) {
            return false;
        }

        return $this->customer_id === null || $this->customer_id === $customerId;
    }
}
