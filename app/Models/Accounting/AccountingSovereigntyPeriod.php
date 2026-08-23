<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingSovereigntyPeriod.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Enums\Finance\AccountingSovereignty;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Migration\AccountingMigrationRun;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Abschnitt der Buchungshoheit (Feature 125, MVP-671).
 *
 * Die Abschnitte sind überschneidungsfrei und lückenlos; zusammen
 * beantworten sie „wer führte das Hauptbuch am Tag X?" auch Jahre nach einem
 * Wechsel. Genau das trennt einen nachweisbaren Übergang von einem stillen
 * Doppelbetrieb.
 *
 * @property AccountingSovereignty $sovereignty
 */
class AccountingSovereigntyPeriod extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'sovereignty',
        'external_provider',
        'valid_from',
        'valid_to',
        'accounting_migration_run_id',
        'actor_user_id',
        'reason',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sovereignty' => AccountingSovereignty::class,
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return BelongsTo<AccountingMigrationRun, $this> */
    public function migrationRun(): BelongsTo {
        return $this->belongsTo(AccountingMigrationRun::class, 'accounting_migration_run_id');
    }

    /** Deckt der Abschnitt diesen Tag ab? */
    public function covers(CarbonInterface $date): bool {
        if ($date->startOfDay()->lessThan($this->valid_from->startOfDay())) {
            return false;
        }

        return $this->valid_to === null || $date->startOfDay()->lessThanOrEqualTo($this->valid_to->startOfDay());
    }
}
