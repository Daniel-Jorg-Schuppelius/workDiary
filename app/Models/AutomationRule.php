<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AutomationRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $trigger_event
 * @property array<int, array<string, mixed>>|array<string, mixed> $conditions
 * @property array<int, array<string, mixed>> $actions
 * @property bool $is_active
 * @property int $priority
 * @property int|null $created_by_id
 */
class AutomationRule extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'name',
        'trigger_event',
        'conditions',
        'actions',
        'is_active',
        'priority',
        'created_by_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    /** @return HasMany<AutomationRuleRun, $this> */
    public function runs(): HasMany {
        return $this->hasMany(AutomationRuleRun::class, 'rule_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
