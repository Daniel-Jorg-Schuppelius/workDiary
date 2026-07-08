<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaContractQuota.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\ServiceTicket\SlaQuotaPeriod;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Inklusivzeit-Kontingent eines SLA-Vertrags (Feature 010 → Rang 44). Der
 * Verbrauch wird aus den abrechenbaren Zeiteinträgen des Vertragskunden im
 * jeweiligen Zeitraum berechnet ({@see \App\Services\ServiceTicket\SlaQuotaService});
 * `overage_rate`/`flat_fee` sind reine Nachweisfelder (Rechnungshoheit extern).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $sla_contract_id
 * @property SlaQuotaPeriod $period_kind
 * @property int $included_minutes
 * @property string|null $overage_rate
 * @property string|null $flat_fee
 * @property int $warn_threshold_pct
 * @property string|null $last_warned_period
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SlaContractQuota extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'sla_contract_id',
        'period_kind',
        'included_minutes',
        'overage_rate',
        'flat_fee',
        'warn_threshold_pct',
        'last_warned_period',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'period_kind' => SlaQuotaPeriod::class,
        'included_minutes' => 'int',
        'warn_threshold_pct' => 'int',
    ];

    /** @return BelongsTo<SlaContract, $this> */
    public function slaContract(): BelongsTo {
        return $this->belongsTo(SlaContract::class);
    }
}
