<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContentReference.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Knowledge;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo, Relation};
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

/**
 * Verweis zwischen zwei Inhalten (MVP-811, Feature 155). Seit dem Umzug
 * tragen auch die früheren Artikel-Verknüpfungen und Ideenknoten-Referenzen
 * diese Form; die Fachbedeutung steht in `kind`.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $source_type
 * @property int $source_id
 * @property string $target_type
 * @property int $target_id
 * @property string $kind
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $source
 * @property-read Model|null $target
 * @property-read User|null $creator
 */
class ContentReference extends Model {
    use BelongsToOrganization;
    use HasSqid;

    /** Wissensartikel „hat hier geholfen“ bzw. Ideenknoten auf bestehendes Ziel. */
    public const KIND_LINKED = 'linked';

    /** Ideenknoten wurde in das Ziel überführt. */
    public const KIND_CONVERTED = 'converted';

    /** Von Hand gesetzter Verweis zwischen Wissensinhalten. */
    public const KIND_MENTIONED = 'mentioned';

    protected $table = 'content_references';

    protected $fillable = [
        'organization_id',
        'source_type',
        'source_id',
        'target_type',
        'target_id',
        'kind',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'source_id' => 'integer',
        'target_id' => 'integer',
    ];

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function target(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Entfernt Verweise, deren Quelle oder Ziel endgültig gelöscht ist. Beide
     * Seiten sind polymorph und haben keinen Fremdschlüssel — was früher die
     * Kaskade an `idea_node_references` erledigte, läuft jetzt hier.
     * Papierkorb-Einträge (SoftDeletes) zählen als vorhanden.
     */
    public static function pruneOrphans(int $organizationId): int {
        $removed = 0;
        foreach (['source', 'target'] as $side) {
            $types = static::query()->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->distinct()
                ->pluck($side . '_type');

            foreach ($types as $type) {
                $class = Relation::getMorphedModel((string) $type) ?? (string) $type;
                if (! is_subclass_of($class, Model::class)) {
                    continue;
                }
                $model = new $class();
                $table = $model->getTable();
                $key = $model->getKeyName();

                $removed += static::query()->withoutGlobalScopes()
                    ->where('organization_id', $organizationId)
                    ->where($side . '_type', $type)
                    ->whereNotExists(static fn (QueryBuilder $q) => $q->selectRaw('1')->from($table)
                        ->whereColumn($table . '.' . $key, 'content_references.' . $side . '_id'))
                    ->delete();
            }
        }

        return $removed;
    }
}
