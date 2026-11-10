<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IcalSubscription.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Time;

use App\Enums\Import\{IcalSyncStatus, ImportEntity};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Abonnierte iCal-Quelle der Zeiterfassung (Feature 094, MVP-1516). Die URL
 * liegt verschlüsselt, weil Kalenderdienste den Zugriff über ein Geheimnis in
 * der Adresse gewähren. Statt der URL kann eine Kalenderverbindung die Quelle
 * sein (`calendar_source` = `<quelle>:<verbindung>`, MVP-1531).
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $user_id
 * @property ImportEntity $import_entity
 * @property string $name
 * @property string|null $url
 * @property string|null $calendar_source
 * @property list<string>|null $category_allowlist
 * @property bool $allow_private_network
 * @property bool $is_active
 * @property string|null $http_etag
 * @property string|null $http_last_modified
 * @property Carbon|null $last_synced_at
 * @property IcalSyncStatus|null $last_status
 * @property string|null $last_error
 * @property array<string, int>|null $last_result
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class IcalSubscription extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'user_id', 'import_entity', 'name', 'url', 'calendar_source', 'category_allowlist',
        'allow_private_network', 'is_active', 'created_by', 'updated_by',
    ];

    protected $hidden = ['url', 'http_etag'];

    /** @var array<string, string> */
    protected $casts = [
        'import_entity' => ImportEntity::class,
        'url' => 'encrypted',
        'category_allowlist' => 'array',
        'allow_private_network' => 'boolean',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
        'last_result' => 'array',
        'last_status' => IcalSyncStatus::class,
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<IcalSubscriptionItem, $this> */
    public function events(): HasMany {
        return $this->hasMany(IcalSubscriptionItem::class);
    }

    /** Host der Quelle für die Anzeige — der Rest der Adresse kann ein Zugriffsgeheimnis sein. */
    public function sourceHost(): string {
        return (string) (parse_url((string) $this->url, PHP_URL_HOST) ?: '—');
    }
}
