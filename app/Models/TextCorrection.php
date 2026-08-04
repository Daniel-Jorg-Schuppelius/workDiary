<?php
/*
 * Created on   : Mon Aug 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TextCorrection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Schreibfehler-Wörterbuch-Eintrag (falsch => richtig). Wirkt deterministisch
 * und automatisch beim Aufbau generierter Positionstexte
 * ({@see \App\Services\Invoicing\TextCorrectionService}); Quelldaten bleiben
 * unverändert. `origin=learned` entsteht NUR über den bestätigten
 * „Merken?"-Dialog — stilles Lernen gibt es nicht.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $wrong
 * @property string $wrong_normalized
 * @property string $correct
 * @property bool $active
 * @property string $origin
 * @property int $usage_count
 * @property Carbon|null $last_used_at
 */
class TextCorrection extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    public const ORIGIN_MANUAL = 'manual';

    public const ORIGIN_LEARNED = 'learned';

    protected $fillable = [
        'organization_id',
        'wrong',
        'correct',
        'active',
        'origin',
        'created_by_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'active' => 'boolean',
        'usage_count' => 'integer',
        'last_used_at' => 'datetime',
    ];

    protected static function booted(): void {
        static::saving(function (self $correction): void {
            $correction->wrong = StringHelper::normalizeWhitespace($correction->wrong);
            $correction->correct = StringHelper::normalizeWhitespace($correction->correct);
            $correction->wrong_normalized = self::normalizeKey($correction->wrong);
        });
    }

    /** Lookup-Schlüssel des Falschworts (case-insensitiv, Whitespace kollabiert). */
    public static function normalizeKey(string $wrong): string {
        return StringHelper::toLower(StringHelper::normalizeWhitespace($wrong));
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
