<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccessMediumHandover.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Übergabevorgang eines Zutrittsmediums (Feature 092): Ausgabe oder Rückgabe
 * mit Inhaber, Zeitpunkt und Zustand — die Historie je Medium
 * (Muster {@see KeyHandover}).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $access_medium_id
 * @property string $direction
 * @property int|null $holder_user_id
 * @property string|null $holder_name
 * @property string|null $holder_company
 * @property Carbon $occurred_at
 * @property Carbon|null $expected_return_at
 * @property string|null $condition
 * @property string|null $signature_token
 * @property int|null $performed_by
 */
class AccessMediumHandover extends Model {
    use BelongsToOrganization;

    public const DIRECTION_ISSUE = 'issue';

    public const DIRECTION_RETURN = 'return';

    protected $fillable = [
        'organization_id', 'access_medium_id', 'direction', 'holder_user_id',
        'holder_name', 'holder_company', 'occurred_at', 'expected_return_at',
        'condition', 'signature_token', 'performed_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'occurred_at' => 'datetime',
        'expected_return_at' => 'datetime',
    ];

    /** @return BelongsTo<AccessMedium, $this> */
    public function medium(): BelongsTo {
        return $this->belongsTo(AccessMedium::class, 'access_medium_id');
    }

    /** @return BelongsTo<User, $this> */
    public function holderUser(): BelongsTo {
        return $this->belongsTo(User::class, 'holder_user_id');
    }
}
