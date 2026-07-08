<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceQueue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Ticket-Queue (Feature 065, MVP-150): Arbeitsvorrat des Helpdesks mit
 * optionalem Team, Eingangspostfach, Standard-SLA und Geschäftszeiten.
 * data_ownership steuert ab P8, ob WorkDiary oder ein externes System
 * (z. B. Zammad) die Tickets dieser Queue führt.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string|null $purpose
 * @property int|null $team_id
 * @property string $data_ownership
 * @property array<int, string>|null $supported_kinds
 * @property array<string, mixed>|null $business_hours
 * @property string|null $holiday_region
 * @property int|null $default_sla_contract_id
 * @property int|null $email_connection_id
 * @property array<string, mixed>|null $sender_identity
 * @property string $visibility
 * @property bool $is_default
 */
class ServiceQueue extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'name',
        'purpose',
        'team_id',
        'data_ownership',
        'supported_kinds',
        'business_hours',
        'holiday_region',
        'default_sla_contract_id',
        'email_connection_id',
        'sender_identity',
        'visibility',
        'is_default',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'supported_kinds' => 'array',
        'business_hours' => 'array',
        'sender_identity' => 'array',
        'is_default' => 'boolean',
    ];

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<SlaContract, $this> */
    public function defaultSlaContract(): BelongsTo {
        return $this->belongsTo(SlaContract::class, 'default_sla_contract_id');
    }

    /** @return HasMany<ServiceTicket, $this> */
    public function tickets(): HasMany {
        return $this->hasMany(ServiceTicket::class, 'queue_id');
    }
}
