<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleImport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Reselling;

use App\Enums\Reselling\{ImportStatus, SubscriptionProvider};
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use App\Models\{Organization, User};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Ein Import-Lauf des Reselling-Registers (Feature 152): eine Anbieterdatei
 * (Telekom-Käufe, Quality-Hosting-Verträge, Preisliste) mit Zählern.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $created_by_user_id
 * @property SubscriptionProvider $provider
 * @property string $kind
 * @property string $file_name
 * @property string|null $file_path
 * @property ImportStatus $status
 * @property int $rows_total
 * @property int $rows_created
 * @property int $rows_updated
 * @property int $rows_unchanged
 * @property int $rows_unassigned
 * @property list<string>|null $issues
 * @property string|null $error
 * @property CarbonImmutable $created_at
 */
class ResaleImport extends Model {
    use BelongsToOrganization;
    use HasSqid;

    public const DISK = 'local';

    public const KIND_PURCHASES = 'purchases';
    public const KIND_CONTRACTS = 'contracts';
    public const KIND_PRICELIST = 'pricelist';

    protected $table = 'resale_imports';

    protected $fillable = [
        'organization_id',
        'created_by_user_id',
        'provider',
        'kind',
        'file_name',
        'file_path',
        'status',
        'rows_total',
        'rows_created',
        'rows_updated',
        'rows_unchanged',
        'rows_unassigned',
        'issues',
        'error',
    ];

    protected $casts = [
        'provider' => SubscriptionProvider::class,
        'status' => ImportStatus::class,
        'rows_total' => 'integer',
        'rows_created' => 'integer',
        'rows_updated' => 'integer',
        'rows_unchanged' => 'integer',
        'rows_unassigned' => 'integer',
        'issues' => 'array',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<ResaleSubscription, $this> */
    public function subscriptions(): HasMany {
        return $this->hasMany(ResaleSubscription::class, 'import_id');
    }

    public function kindLabel(): string {
        return (string) __('resale.import.kind.' . $this->kind);
    }
}
