<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportColumnMapping.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Integration;

use App\Enums\Import\ImportEntity;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;

/**
 * Gespeicherte Spaltenzuordnung des Imports (Feature 148/024, MVP-1020):
 * Kopfzelle einer Quelldatei → kanonische Spalte je Organisation und
 * Importart. Der {@see \App\Services\Import\HeaderMapper} liest sie als
 * zusätzliche Aliase; die Aliase der Spec gehen vor.
 *
 * @property int $id
 * @property int $organization_id
 * @property ImportEntity $entity
 * @property string $source_header Normalisiert (klein, getrimmt)
 * @property string $target_column
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class ImportColumnMapping extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'entity',
        'source_header',
        'target_column',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'entity' => ImportEntity::class,
    ];

    /**
     * {normalisierte Kopfzelle => kanonische Spalte} der Organisation.
     *
     * @return array<string, string>
     */
    public static function aliasesFor(int $organizationId, ImportEntity $entity): array {
        return self::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('entity', $entity->value)
            ->pluck('target_column', 'source_header')
            ->all();
    }
}
