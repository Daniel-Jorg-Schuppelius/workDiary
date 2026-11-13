<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerServicePeriodNote.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\ServicePlan;

use App\Casts\PeriodKeyCast;
use App\Models\Classification\EntryType;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Services\ServicePlan\Dto\ServiceKey;
use App\Support\Period\PeriodKey;
use Database\Factories\ServicePlan\CustomerServicePeriodNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * „Unterlagen da am“ und Bemerkung einer Zelle des Leistungsplans (Feature
 * 199, MVP-1638) — Angaben, die der Auftrag nicht trägt und die oft schon vor
 * ihm entstehen (Belege des Kunden sind da, der Auftrag noch nicht angelegt).
 * Geschrieben wird über {@see \App\Services\ServicePlan\ServicePlanCells}.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $customer_id
 * @property int $entry_type_id
 * @property string|null $product_group_code
 * @property PeriodKey $period_key
 * @property Carbon|null $documents_received_on
 * @property string|null $note
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CustomerServicePeriodNote extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<CustomerServicePeriodNoteFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'customer_id',
        'entry_type_id',
        'product_group_code',
        'period_key',
        'documents_received_on',
        'note',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'period_key' => PeriodKeyCast::class,
        'documents_received_on' => 'date',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<EntryType, $this> */
    public function entryType(): BelongsTo {
        return $this->belongsTo(EntryType::class);
    }

    public function serviceKey(): ServiceKey {
        return new ServiceKey((int) $this->entry_type_id, $this->product_group_code);
    }

    /** Leer, wenn weder Datum noch Bemerkung gesetzt ist. */
    public function isEmpty(): bool {
        return $this->documents_received_on === null && trim((string) $this->note) === '';
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
