<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Protocol.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Protocol\ProtocolStatus;
use App\Enums\Protocol\ProtocolType;
use App\Enums\Protocol\ProtocolVisibility;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasAttachments;
use Database\Factories\ProtocolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property ProtocolType $type
 * @property int|null $template_id
 * @property int|null $template_version
 * @property string $subject_type
 * @property int $subject_id
 * @property string $title
 * @property string|null $description
 * @property string|null $state_initial
 * @property string|null $state_final
 * @property ProtocolStatus $status
 * @property int $revision
 * @property int|null $supersedes_id
 * @property ProtocolVisibility $visibility
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property int $created_by_user_id
 * @property \Illuminate\Support\Carbon|null $signed_at
 * @property \Illuminate\Support\Carbon|null $archived_at
 */
class Protocol extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;

    /** @use HasFactory<ProtocolFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'type',
        'template_id',
        'template_version',
        'subject_type',
        'subject_id',
        'title',
        'description',
        'state_initial',
        'state_final',
        'status',
        'revision',
        'supersedes_id',
        'visibility',
        'occurred_at',
        'created_by_user_id',
        'signed_at',
        'archived_at',
    ];

    protected $casts = [
        'type' => ProtocolType::class,
        'status' => ProtocolStatus::class,
        'visibility' => ProtocolVisibility::class,
        'occurred_at' => 'datetime',
        'signed_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<self, $this> */
    public function supersedes(): BelongsTo {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /** @return HasMany<ProtocolItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(ProtocolItem::class)->orderBy('sort_order');
    }

    /** @return HasMany<ProtocolSignature, $this> */
    public function signatures(): HasMany {
        return $this->hasMany(ProtocolSignature::class);
    }

    /** @return HasMany<ProtocolEvent, $this> */
    public function events(): HasMany {
        return $this->hasMany(ProtocolEvent::class)->orderBy('created_at');
    }
}
