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
 * Persistentes Tag-/Kategorie-Mapping des CSV-Imports (Rang 58): Quellwert →
 * Tag oder „ignorieren" je Organisation + Import-Entität; Wiederholimporte
 * lösen darüber auf statt blind neu anzulegen (Muster ExternalReferenceAlias).
 */
class ImportValueMapping extends Model {
    use BelongsToOrganization;

    public const KIND_TAG = 'tag';

    public const KIND_IGNORE = 'ignore';

    protected $fillable = [
        'organization_id',
        'entity',
        'source_value',
        'target_kind',
        'tag_id',
    ];

    /** @return BelongsTo<Tag, $this> */
    public function tag(): BelongsTo {
        return $this->belongsTo(Tag::class);
    }

    /** Normalisierter Lookup-Schlüssel eines Quellwerts. */
    public static function normalize(string $value): string {
        return mb_strtolower(trim($value));
    }

    /**
     * Mapping eines Quellwerts auflösen: Tag-ID, 'ignore' oder null (unbekannt).
     */
    public static function resolveValue(int $organizationId, string $entity, string $sourceValue): int|string|null {
        $mapping = self::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('entity', $entity)
            ->where('source_value', self::normalize($sourceValue))
            ->first();

        if ($mapping === null) {
            return null;
        }

        return $mapping->target_kind === self::KIND_IGNORE ? self::KIND_IGNORE : (int) $mapping->tag_id;
    }
}
