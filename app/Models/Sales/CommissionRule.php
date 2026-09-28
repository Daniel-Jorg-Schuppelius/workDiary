<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommissionRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Sales;

use App\Casts\{MoneyCast, PercentageCast};
use App\Enums\Sales\{CommissionScope, CommissionTierPeriod};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use App\Support\Query\DateRange;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\{Money, Percentage};
use Illuminate\Database\Eloquent\{Builder, Collection, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Provisionsregel einer Organisation (Feature 146, MVP-729).
 *
 * Eine Regel ist ein Satz plus die Bedingung, wann er gilt: Geltungsbereich
 * ({@see CommissionScope}), Gueltigkeitszeitraum und Prioritaet. Je Beleg
 * gewinnt genau EINE Regel — es wird nichts summiert und nichts gestaffelt.
 *
 * Der Satz wird beim Entstehen der Provisionszeile eingefroren
 * (`invoice_commissions.rate_percent`); spaetere Regelaenderungen deuten
 * abgerechnete Perioden nie um.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property CommissionScope $scope
 * @property string|null $scope_value
 * @property int|null $user_id
 * @property int|null $commission_agent_id
 * @property CurrencyCode|null $currency
 * @property CommissionTierPeriod|null $tier_period
 * @property Money|null $annual_cap_amount
 * @property int|null $liability_days
 * @property bool $is_partial_accrual
 * @property Percentage $rate_percent
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_to
 * @property int $priority
 * @property bool $is_active
 * @property string|null $note
 * @property int|null $created_by
 */
class CommissionRule extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'commission_rules';

    protected $fillable = [
        'organization_id',
        'name',
        'scope',
        'scope_value',
        'user_id',
        'commission_agent_id',
        'rate_percent',
        'currency',
        'tier_period',
        'annual_cap_amount',
        'liability_days',
        'is_partial_accrual',
        'valid_from',
        'valid_to',
        'priority',
        'is_active',
        'note',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'scope' => CommissionScope::class,
        'rate_percent' => PercentageCast::class . ':2',
        'currency' => CurrencyCode::class,
        'tier_period' => CommissionTierPeriod::class,
        'annual_cap_amount' => MoneyCast::class . ':currency,2',
        'liability_days' => 'integer',
        'is_partial_accrual' => 'boolean',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<CommissionAgent, $this> */
    public function agent(): BelongsTo {
        return $this->belongsTo(CommissionAgent::class, 'commission_agent_id');
    }

    /** @return HasMany<CommissionRuleTier, $this> */
    public function tiers(): HasMany {
        return $this->hasMany(CommissionRuleTier::class, 'commission_rule_id')->orderBy('threshold_amount');
    }

    /**
     * Staffelstufen aufsteigend, mit der Regel als Währungsquelle der Schwellen.
     *
     * @return Collection<int, CommissionRuleTier>
     */
    public function orderedTiers(): Collection {
        $tiers = $this->tiers()->get();
        $tiers->each(fn (CommissionRuleTier $tier) => $tier->setRelation('rule', $this));

        return $tiers;
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Aktive Regeln, die am Stichtag gelten. Offene Grenzen (`null`) bedeuten
     * „seit jeher" bzw. „bis auf Weiteres".
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeValidOn(Builder $query, Carbon $date): Builder {
        return $query->where('is_active', true)
            ->where(function (Builder $q) use ($date): void {
                $q->whereNull('valid_from')->orWhere('valid_from', '<', DateRange::dayAfter($date));
            })
            ->where(function (Builder $q) use ($date): void {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date->toDateString());
            });
    }

    /** Sortierschluessel der Regelauswahl: Prioritaet, dann Spezifitaet, dann Alter. */
    public function selectionKey(): string {
        return sprintf('%05d-%d-%010d', $this->priority, $this->scope->specificity(), (int) $this->getKey());
    }
}
