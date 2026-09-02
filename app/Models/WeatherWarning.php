<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WeatherWarning.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\Weather\WeatherWarningThreshold;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ausgelöste Wetterwarnung für einen disponierten Einsatz (Feature 062,
 * MVP-716): genau eine Zeile je Einsatz+Vorhersagetag+Schwelle (Unique-Key =
 * Dedupe-Schlüssel der Benachrichtigung). Kein Ist-Snapshot — die
 * Vorhersagezeile liegt nur als Nachweis im `forecast`-JSON.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $diary_entry_id
 * @property Carbon $forecast_date
 * @property WeatherWarningThreshold $threshold
 * @property string $value
 * @property string $limit_value
 * @property string $provider
 * @property array<string, mixed> $forecast
 * @property-read DiaryEntry|null $diaryEntry
 */
class WeatherWarning extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'diary_entry_id',
        'forecast_date',
        'threshold',
        'value',
        'limit_value',
        'provider',
        'forecast',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'forecast_date' => 'date',
        'value' => 'decimal:2',
        'limit_value' => 'decimal:2',
        'threshold' => WeatherWarningThreshold::class,
        'forecast' => 'array',
    ];

    /** @return BelongsTo<DiaryEntry, $this> */
    public function diaryEntry(): BelongsTo {
        return $this->belongsTo(DiaryEntry::class);
    }
}
