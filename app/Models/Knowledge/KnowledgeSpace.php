<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeSpace.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Knowledge;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Platform\User;
use Database\Factories\Knowledge\KnowledgeSpaceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Bereich der internen Wissensdatenbank (Feature 191, MVP-1664) — das
 * „Notizbuch“: Rubriken und Seiten hängen darunter. Ein eingeschränkter
 * Bereich (`is_restricted`) ist nur für die Leser aus
 * {@see KnowledgeSpaceReader} sichtbar (Leserecht je Bereich, MVP-1667).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $title
 * @property string|null $description
 * @property int $position
 * @property bool $is_restricted
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class KnowledgeSpace extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<KnowledgeSpaceFactory> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'title',
        'description',
        'position',
        'is_restricted',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'position' => 'integer',
        'is_restricted' => 'boolean',
    ];

    /** @return HasMany<KnowledgeSection, $this> */
    public function sections(): HasMany {
        return $this->hasMany(KnowledgeSection::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<KnowledgePage, $this> */
    public function pages(): HasMany {
        return $this->hasMany(KnowledgePage::class);
    }

    /** @return HasMany<KnowledgeSpaceReader, $this> */
    public function readers(): HasMany {
        return $this->hasMany(KnowledgeSpaceReader::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }
}
