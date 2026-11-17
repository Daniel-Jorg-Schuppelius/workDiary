<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeGlossaryTerm.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Knowledge;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Glossarbegriff der Organisation (Feature 191, MVP-1667) mit Kürzeln und
 * Synonymen. Die Suche nach Wissensseiten nimmt sie mit: „USt-VA“ findet
 * „Umsatzsteuer-Voranmeldung“.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $term
 * @property list<string>|null $synonyms
 * @property string|null $description
 * @property int|null $knowledge_page_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read KnowledgePage|null $page
 */
class KnowledgeGlossaryTerm extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const int MAX_SYNONYMS = 20;

    protected $fillable = [
        'organization_id',
        'term',
        'synonyms',
        'description',
        'knowledge_page_id',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'synonyms' => 'array',
    ];

    /** @return BelongsTo<KnowledgePage, $this> */
    public function page(): BelongsTo {
        return $this->belongsTo(KnowledgePage::class, 'knowledge_page_id');
    }

    /** @return list<string> Begriff und alle Kürzel/Synonyme */
    public function forms(): array {
        return array_values(array_filter(array_map('strval', [$this->term, ...($this->synonyms ?? [])]), static fn (string $form): bool => trim($form) !== ''));
    }
}
