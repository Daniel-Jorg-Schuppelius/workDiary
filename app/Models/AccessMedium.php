<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccessMedium.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\Access\{AccessMediumStatus, AccessMediumType};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Zutrittsmedium (Feature 092, Stufe 1): Transponder, Karte oder Code als
 * verwalteter Bestand mit Verbleib — keine Live-Anlagensteuerung.
 *
 * Die Mediennummer liegt nur **gehasht** vor (Muster {@see UserBadge});
 * sichtbar bleibt das Anzeige-Suffix. Der Inhaber ist Nutzer ODER externe
 * Person — Reinigungsdienste haben kein Mitarbeiterkonto.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property AccessMediumType $type
 * @property string $number_hash
 * @property string $number_suffix
 * @property string|null $label
 * @property int|null $site_id
 * @property string|null $system_name
 * @property AccessMediumStatus $status
 * @property int|null $holder_user_id
 * @property string|null $holder_name
 * @property string|null $holder_company
 * @property int|null $block_task_id
 * @property Carbon|null $blocked_at
 * @property string|null $notes
 * @property int|null $created_by
 */
class AccessMedium extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'access_media';

    protected $fillable = [
        'organization_id', 'type', 'number_hash', 'number_suffix', 'label',
        'site_id', 'system_name', 'status', 'holder_user_id', 'holder_name',
        'holder_company', 'block_task_id', 'blocked_at', 'notes', 'created_by',
    ];

    /** Die gehashte Kennung nie serialisieren/auditieren. */
    protected $hidden = ['number_hash'];

    /** @var array<string, string> */
    protected $casts = [
        'type' => AccessMediumType::class,
        'status' => AccessMediumStatus::class,
        'blocked_at' => 'datetime',
    ];

    public static function hashNumber(string $number): string {
        return CryptoHelper::hash(trim($number));
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<User, $this> */
    public function holderUser(): BelongsTo {
        return $this->belongsTo(User::class, 'holder_user_id');
    }

    /** @return BelongsTo<Task, $this> */
    public function blockTask(): BelongsTo {
        return $this->belongsTo(Task::class, 'block_task_id');
    }

    /** @return HasMany<AccessMediumHandover, $this> */
    public function handovers(): HasMany {
        return $this->hasMany(AccessMediumHandover::class);
    }

    /** Anzeigename des aktuellen Inhabers (Nutzer oder externe Person). */
    public function holderDisplay(): ?string {
        if ($this->holderUser !== null) {
            return $this->holderUser->name;
        }
        $parts = array_filter([$this->holder_name, $this->holder_company]);

        return $parts !== [] ? implode(' · ', $parts) : null;
    }
}
