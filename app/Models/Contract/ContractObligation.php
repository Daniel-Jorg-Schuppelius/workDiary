<?php
/*
 * Created on   : Mon Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractObligation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Contract;

use App\Enums\Contract\ContractObligationKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vertragsobligation / Kalendertermin (Welle D, CLM): Kündigungsfrist,
 * Verlängerungswarnung, Zahlungs-/Prüf-/Indexierungstermin — mit Vorwarnzeit.
 * Speist die Fristen-/Eskalationsmechanik (contract.deadlineDue). Optional
 * wiederkehrend: beim Erledigen wird die nächste Fälligkeit erzeugt.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $contract_id
 * @property ContractObligationKind $kind
 * @property \Illuminate\Support\Carbon $due_on
 * @property int $warn_days_before
 * @property bool $recurring
 * @property int|null $recurrence_months
 * @property string $status
 */
class ContractObligation extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const STATUSES = ['open', 'done', 'missed'];

    protected $fillable = [
        'organization_id', 'contract_id', 'kind', 'title', 'due_on',
        'warn_days_before', 'recurring', 'recurrence_months', 'status',
        'responsible_user_id', 'note', 'done_at', 'done_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => ContractObligationKind::class,
        'due_on' => 'date',
        'warn_days_before' => 'integer',
        'recurrence_months' => 'integer',
        'recurring' => 'boolean',
        'done_at' => 'datetime',
    ];

    /** @param Builder<self> $query */
    public function scopeOpen(Builder $query): void {
        $query->where('status', 'open');
    }

    public function isDueForWarning(): bool {
        return $this->status === 'open'
            && $this->due_on->copy()->subDays($this->warn_days_before)->startOfDay()->isPast();
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo {
        return $this->belongsTo(Contract::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
