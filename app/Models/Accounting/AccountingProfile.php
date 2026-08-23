<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingProfile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Enums\Finance\{AccountingSovereignty, ProfitDetermination};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Buchhaltungsprofil einer Organisation (Feature 125, MVP-671).
 *
 * Fehlt die Zeile, gilt {@see AccountingSovereignty::Preaccounting} — der
 * heutige Zustand. Das Profil entsteht erst, wenn jemand die Einrichtung
 * bewusst öffnet; ein Deployment legt keines an.
 *
 * @property AccountingSovereignty $sovereignty
 * @property ProfitDetermination $profit_determination
 * @property CurrencyCode $base_currency
 * @property array<string, mixed>|null $preflight
 */
class AccountingProfile extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'sovereignty',
        'external_provider',
        'profit_determination',
        'base_currency',
        'fiscal_year_start_month',
        'starts_on',
        'preflight',
        'activated_at',
        'activated_by',
        'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sovereignty' => AccountingSovereignty::class,
        'profit_determination' => ProfitDetermination::class,
        'base_currency' => CurrencyCode::class,
        'fiscal_year_start_month' => 'integer',
        'starts_on' => 'date',
        'preflight' => 'array',
        'activated_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function activatedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'activated_by');
    }

    /** @return HasMany<AccountingFiscalYear, $this> */
    public function fiscalYears(): HasMany {
        return $this->hasMany(AccountingFiscalYear::class, 'organization_id', 'organization_id');
    }

    /** Ist die lokale Buchhaltung aktiv (Hoheit + Startdatum gesetzt)? */
    public function isLocalActive(): bool {
        return $this->sovereignty === AccountingSovereignty::Local && $this->starts_on !== null;
    }
}
