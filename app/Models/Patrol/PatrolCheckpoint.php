<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PatrolCheckpoint.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Patrol;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kontrollpunkt einer Route (Feature 089): Ort, gehashter Scan-Token und
 * Soll-Fenster relativ zum Rundgangsstart.
 *
 * Ein verlorener Tag wird über {@see reissueToken()} ersetzt — die Route
 * bleibt, nur der Token wechselt.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $patrol_route_id
 * @property int $position
 * @property string $label
 * @property string $token_hash
 * @property string $token_suffix
 * @property int $expected_offset_minutes
 * @property int $tolerance_minutes
 */
class PatrolCheckpoint extends Model {
    use BelongsToOrganization;
    use HasSqid;

    /** Die gehashte Kennung nie serialisieren. */
    protected $hidden = ['token_hash'];

    protected $fillable = [
        'organization_id', 'patrol_route_id', 'position', 'label',
        'token_hash', 'token_suffix', 'expected_offset_minutes', 'tolerance_minutes',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'position' => 'integer',
        'expected_offset_minutes' => 'integer',
        'tolerance_minutes' => 'integer',
    ];

    public static function hashToken(string $token): string {
        return CryptoHelper::hash(trim($token));
    }

    /** @return BelongsTo<PatrolRoute, $this> */
    public function route(): BelongsTo {
        return $this->belongsTo(PatrolRoute::class, 'patrol_route_id');
    }
}
