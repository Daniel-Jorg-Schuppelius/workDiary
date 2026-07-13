<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportValueMapping.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persistentes Tag-/Kategorie-Mapping des CSV-/XLSX-Imports (Rang 58, A13):
 * Quellwert → Tag, Klassifikation oder „ignorieren" je Organisation +
 * Import-Entität; Wiederholimporte lösen darüber auf statt blind neu
 * anzulegen (Muster ExternalReferenceAlias).
 */
class ImportValueMapping extends Model {
    use BelongsToOrganization;

    public const KIND_TAG = 'tag';

    public const KIND_CLASSIFICATION = 'classification';

    public const KIND_IGNORE = 'ignore';

    protected $fillable = [
        'organization_id',
        'entity',
        'source_value',
        'target_kind',
        'tag_id',
        'classification_id',
    ];

    /** @return BelongsTo<Tag, $this> */
    public function tag(): BelongsTo {
        return $this->belongsTo(Tag::class);
    }

    /** @return BelongsTo<Classification, $this> */
    public function classification(): BelongsTo {
        return $this->belongsTo(Classification::class);
    }

    /** Normalisierter Lookup-Schlüssel eines Quellwerts. */
    public static function normalize(string $value): string {
        return mb_strtolower(trim($value));
    }

    /**
     * Persistierte Zuordnung eines Quellwerts finden (null = unbekannt).
     */
    public static function findFor(int $organizationId, string $entity, string $sourceValue): ?self {
        return self::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('entity', $entity)
            ->where('source_value', self::normalize($sourceValue))
            ->first();
    }
}
