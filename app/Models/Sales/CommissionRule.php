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

use App\Casts\PercentageCast;
use App\Enums\Sales\CommissionScope;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use App\Support\Query\DateRange;
use CommonToolkit\ValueObjects\Percentage;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'rate_percent',
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
        'valid_from' => 'date',
        'valid_to' => 'date',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
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
