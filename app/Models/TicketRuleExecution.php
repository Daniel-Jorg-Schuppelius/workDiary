<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TicketRuleExecution.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Regel-Ausführungsprotokoll (Feature 065, MVP-153): „warum wurde
 * angewendet" ist Pflicht — je Anwendung Regel-Version, erfüllte
 * Bedingungen und angewendete Aktionen.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $ticket_routing_rule_id
 * @property int $service_ticket_id
 * @property int $rule_version
 * @property array<string, mixed> $matched_conditions
 * @property array<string, mixed> $applied_actions
 * @property bool $dry_run
 */
class TicketRuleExecution extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'ticket_routing_rule_id',
        'service_ticket_id',
        'rule_version',
        'matched_conditions',
        'applied_actions',
        'dry_run',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'matched_conditions' => 'array',
        'applied_actions' => 'array',
        'dry_run' => 'boolean',
        'rule_version' => 'integer',
    ];

    /** @return BelongsTo<TicketRoutingRule, $this> */
    public function rule(): BelongsTo {
        return $this->belongsTo(TicketRoutingRule::class, 'ticket_routing_rule_id');
    }

    /** @return BelongsTo<ServiceTicket, $this> */
    public function ticket(): BelongsTo {
        return $this->belongsTo(ServiceTicket::class, 'service_ticket_id');
    }
}
