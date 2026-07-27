<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CashRegister.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Carbon\Carbon;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kasse (MVP-414, Kassenbuch) — Bareinnahmen/-ausgaben laufen ausschließlich
 * über {@see \App\Services\Finance\CashBookService} in die append-only
 * Einträge; workDiary ist KEIN Aufzeichnungssystem i. S. v. § 146a AO
 * (kein POS, keine TSE-Pflicht).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property CurrencyCode $currency
 * @property \CommonToolkit\ValueObjects\Money|null $opening_balance
 * @property Carbon $opened_on
 * @property bool $active
 */
class CashRegister extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'name',
        'currency',
        'opening_balance',
        'opened_on',
        'active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'currency' => CurrencyCode::class,
        'opening_balance' => MoneyCast::class . ':currency,2',
        'opened_on' => 'date',
        'active' => 'boolean',
    ];

    /** @return HasMany<CashEntry, $this> */
    public function entries(): HasMany {
        return $this->hasMany(CashEntry::class)->orderBy('seq_no');
    }

    /** @return HasMany<CashDailyClosing, $this> */
    public function closings(): HasMany {
        return $this->hasMany(CashDailyClosing::class)->orderByDesc('closing_date');
    }

    /** Datum des letzten Tagesabschlusses; Einträge bis dahin sind festgeschrieben. */
    public function lastClosingDate(): ?Carbon {
        $value = $this->closings()->max('closing_date');

        return $value !== null ? Carbon::parse((string) $value) : null;
    }
}
