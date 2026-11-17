<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppWish.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Wishes;

use App\Enums\Wishes\{AppWishKind, AppWishStatus};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Database\Factories\Wishes\AppWishFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

/**
 * Wunsch, Fehler oder Frage an die App (Feature 206, MVP-1658). Gehört einer
 * Organisation; dort sehen ihn alle Mitglieder und stimmen über ihn ab.
 * Weitergegeben (MVP-1659) sieht der Plattformbetrieb nur `forwarded_body`,
 * Art, Seite und Stimmenzahl — nie `body`, `created_by` oder Namen. Was an
 * den Hersteller ging, steht in `vendor_body` (MVP-1677).
 * Geschrieben wird über {@see \App\Services\Wishes\AppWishService}.
 *
 * @property int $id
 * @property int $organization_id
 * @property AppWishKind $kind
 * @property string $body
 * @property string|null $route_name
 * @property AppWishStatus $status
 * @property string|null $answer
 * @property Carbon|null $answered_at
 * @property int|null $answered_by
 * @property string|null $app_version
 * @property int|null $created_by
 * @property Carbon|null $forwarded_at
 * @property int|null $forwarded_by
 * @property string|null $forwarded_body
 * @property AppWishStatus|null $platform_status
 * @property string|null $platform_answer
 * @property Carbon|null $platform_answered_at
 * @property int|null $platform_bundle_id
 * @property string|null $vendor_body
 * @property Carbon|null $vendor_sent_at
 * @property int|null $vendor_sent_by
 * @property string|null $vendor_answer
 * @property Carbon|null $vendor_answered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $votes_count
 */
class AppWish extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<AppWishFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'kind',
        'body',
        'route_name',
        'status',
        'answer',
        'answered_at',
        'answered_by',
        'app_version',
        'created_by',
        'forwarded_at',
        'forwarded_by',
        'forwarded_body',
        'platform_status',
        'platform_answer',
        'platform_answered_at',
        'platform_bundle_id',
        'vendor_body',
        'vendor_sent_at',
        'vendor_sent_by',
        'vendor_answer',
        'vendor_answered_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => AppWishKind::class,
        'status' => AppWishStatus::class,
        'platform_status' => AppWishStatus::class,
        'answered_at' => 'datetime',
        'forwarded_at' => 'datetime',
        'platform_answered_at' => 'datetime',
        'vendor_sent_at' => 'datetime',
        'vendor_answered_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function answerer(): BelongsTo {
        return $this->belongsTo(User::class, 'answered_by');
    }

    /** @return HasMany<AppWishVote, $this> */
    public function votes(): HasMany {
        return $this->hasMany(AppWishVote::class);
    }

    /**
     * Bündel der Plattformsicht: der führende Wunsch, unter dem gleichartige zusammengefasst sind (MVP-1659).
     *
     * @return BelongsTo<AppWish, $this>
     */
    public function bundleLead(): BelongsTo {
        return $this->belongsTo(self::class, 'platform_bundle_id');
    }

    /** @return HasMany<AppWish, $this> */
    public function bundled(): HasMany {
        return $this->hasMany(self::class, 'platform_bundle_id');
    }

    public function isForwarded(): bool {
        return $this->forwarded_at !== null;
    }

    public function isSentToVendor(): bool {
        return $this->vendor_sent_at !== null;
    }

    /** Link zur Seite des Wunsches, wenn die Route ohne Parameter erreichbar ist; sonst nur der Name. */
    public function pageUrl(): ?string {
        $route = $this->route_name !== null ? Route::getRoutes()->getByName($this->route_name) : null;
        if ($route === null || $route->parameterNames() !== [] || ! in_array('GET', $route->methods(), true)) {
            return null;
        }

        return route($this->route_name);
    }

    /** Bearbeitbar durch die verfassende Person, solange der Wunsch neu ist. */
    public function isEditableByAuthor(): bool {
        return $this->status === AppWishStatus::New && ! $this->isForwarded();
    }
}
