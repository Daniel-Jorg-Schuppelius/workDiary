<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AutomationRuleRun.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int $rule_id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $decision
 * @property array<string, mixed>|null $log
 * @property \Illuminate\Support\Carbon $ran_at
 */
class AutomationRuleRun extends Model {
    use BelongsToOrganization;

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'rule_id',
        'subject_type',
        'subject_id',
        'decision',
        'log',
        'ran_at',
    ];

    protected static function booted(): void {
        // RuleEngine wird i. d. R. aus Queue-/Konsolen-Kontexten heraus
        // aufgerufen, dort ist currentOrganization nicht gebunden. Wir
        // leiten die Org daher zusätzlich aus der zugehörigen Regel ab.
        static::creating(function (self $run): void {
            if (! empty($run->organization_id) || empty($run->rule_id)) {
                return;
            }
            // TENANT-BYPASS: Queue-Worker hat keine currentOrganization-Bindung;
            // organization_id wird direkt aus der zugehörigen AutomationRule
            // übernommen, deren Mandantengrenze damit auch für den Run greift.
            $rule = AutomationRule::query()->withoutGlobalScopes()->find($run->rule_id);
            if ($rule instanceof AutomationRule && ! empty($rule->organization_id)) {
                $run->organization_id = $rule->organization_id;
            }
        });
    }

    /** @var array<string, string> */
    protected $casts = [
        'log' => 'array',
        'ran_at' => 'datetime',
    ];

    /** @return BelongsTo<AutomationRule, $this> */
    public function rule(): BelongsTo {
        return $this->belongsTo(AutomationRule::class, 'rule_id');
    }
}
