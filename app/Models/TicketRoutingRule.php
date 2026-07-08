<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TicketRoutingRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Routing-Regel (Feature 065, MVP-153): deterministisch — Position
 * aufsteigend, erste zutreffende Regel je Aktionstyp gewinnt; jede
 * Anwendung wird protokolliert (ticket_rule_executions).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property int $position
 * @property array<string, mixed> $conditions
 * @property array<string, mixed> $actions
 * @property int $version
 * @property bool $active
 */
class TicketRoutingRule extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'name',
        'position',
        'conditions',
        'actions',
        'version',
        'active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'active' => 'boolean',
        'version' => 'integer',
    ];

    /** @return HasMany<TicketRuleExecution, $this> */
    public function executions(): HasMany {
        return $this->hasMany(TicketRuleExecution::class, 'ticket_routing_rule_id');
    }
}
