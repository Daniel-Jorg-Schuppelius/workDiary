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

use App\Enums\Contract\{ContractObligationKind, ContractObligationStatus};
use App\Enums\Period\PeriodKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Contracts\PeriodicOccurrence;
use App\Models\Platform\User;
use App\Support\Period\PeriodKey;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vertragsobligation / Kalendertermin (Welle D, CLM): Kündigungsfrist,
 * Verlängerungswarnung, Zahlungs-/Prüf-/Indexierungstermin — mit Vorwarnzeit.
 * Speist die Fristen-/Eskalationsmechanik (contract.deadlineDue). Optional
 * wiederkehrend: beim Erledigen wird die nächste Fälligkeit erzeugt.
 *
 * Träger der Merker für die nächste Periode (Feature 201, MVP-1646): eine
 * wiederkehrende Pflicht ist ein Vorkommen der Reihe Vertrag × Pflichtart
 * (`contract_obligation:<vertrag>:<art>`), ihre Periode ist die Fälligkeit in
 * der Art des Wiederholungsabstands (12 Monate → Jahr, 3 Monate → Quartal …).
 * Die beim Erledigen neu angelegte Pflicht zeigt so die Merker der vorigen.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $contract_id
 * @property ContractObligationKind $kind
 * @property \Illuminate\Support\Carbon $due_on
 * @property int $warn_days_before
 * @property bool $recurring
 * @property int|null $recurrence_months
 * @property ContractObligationStatus $status
 */
class ContractObligation extends Model implements PeriodicOccurrence {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'contract_id', 'kind', 'title', 'due_on',
        'warn_days_before', 'recurring', 'recurrence_months', 'status',
        'responsible_user_id', 'note', 'done_at', 'done_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => ContractObligationKind::class,
        'status' => ContractObligationStatus::class,
        'due_on' => 'date',
        'warn_days_before' => 'integer',
        'recurrence_months' => 'integer',
        'recurring' => 'boolean',
        'done_at' => 'datetime',
    ];

    /** @param Builder<self> $query */
    public function scopeOpen(Builder $query): void {
        $query->where('status', ContractObligationStatus::Open);
    }

    public function isDueForWarning(): bool {
        return $this->status === ContractObligationStatus::Open
            && $this->due_on->copy()->subDays($this->warn_days_before)->startOfDay()->isPast();
    }

    /** Reihe Vertrag × Pflichtart — nur wiederkehrende Pflichten (MVP-1646). */
    public function carryForwardSeries(): ?string {
        if (! $this->recurring || (int) $this->recurrence_months <= 0 || (int) $this->contract_id <= 0) {
            return null;
        }

        return 'contract_obligation:' . (int) $this->contract_id . ':' . $this->kind->value;
    }

    /** Fälligkeit als Periode in der Art des Wiederholungsabstands. */
    public function carryForwardPeriod(): ?PeriodKey {
        if ($this->carryForwardSeries() === null) {
            return null;
        }

        return PeriodKey::containing(PeriodKind::forMonths((int) $this->recurrence_months), $this->due_on);
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
