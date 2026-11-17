<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Knowledge;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Rubrik eines Bereichs (Feature 191, MVP-1664) — der „Abschnitt“ im
 * Notizbuch. Wird eine Rubrik gelöscht, rutschen ihre Seiten an den Bereich.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $knowledge_space_id
 * @property string $title
 * @property int $position
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class KnowledgeSection extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'knowledge_space_id',
        'title',
        'position',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'position' => 'integer',
    ];

    /** @return BelongsTo<KnowledgeSpace, $this> */
    public function space(): BelongsTo {
        return $this->belongsTo(KnowledgeSpace::class, 'knowledge_space_id');
    }

    /** @return HasMany<KnowledgePage, $this> */
    public function pages(): HasMany {
        return $this->hasMany(KnowledgePage::class);
    }
}
