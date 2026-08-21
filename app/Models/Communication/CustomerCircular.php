<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerCircular.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Communication;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Rundschreiben an einen gefilterten Kundenkreis (Feature 119, MVP-608).
 *
 * @property Carbon|null $sent_at
 * @property array<string, mixed>|null $filters
 */
class CustomerCircular extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    protected $fillable = [
        'organization_id',
        'subject',
        'body',
        'is_mandatory',
        'portal_notice',
        'filters',
        'status',
        'sent_at',
        'created_by',
        'sent_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_mandatory' => 'boolean',
        'portal_notice' => 'boolean',
        'filters' => 'array',
        'sent_at' => 'datetime',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'draft', 'is_mandatory' => false, 'portal_notice' => false];

    /** @return HasMany<CustomerCircularRecipient, $this> */
    public function recipients(): HasMany {
        return $this->hasMany(CustomerCircularRecipient::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool {
        return $this->status === self::STATUS_DRAFT;
    }
}
