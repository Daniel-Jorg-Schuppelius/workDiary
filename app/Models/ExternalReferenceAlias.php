<?php
/*
 * Created on   : Tue Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalReferenceAlias.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Weiterleitung einer (oft veralteten) Fremd-ID auf ihr heutiges Ziel-Modell.
 * Entsteht beim Merge, wenn die Quell-Referenz wegen des Unique-Index in
 * {@see ExternalReference} nicht als Primär-Referenz aufs Ziel umgehängt werden
 * kann. Die Import-Lookups (Toggl, Integrations-Drehscheibe) konsultieren die
 * Aliase als Fallback vor der Namens-/Inbox-Logik.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $plugin_id
 * @property string $external_type
 * @property string $external_id
 * @property string $referenceable_type
 * @property int $referenceable_id
 */
class ExternalReferenceAlias extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'plugin_id',
        'external_type',
        'external_id',
        'referenceable_type',
        'referenceable_id',
    ];

    /** @return MorphTo<Model, $this> */
    public function referenceable(): MorphTo {
        return $this->morphTo();
    }

    /**
     * Löst eine Fremd-ID über die Alias-Tabelle auf ihr heutiges Ziel-Modell auf.
     * Bewusst ohne Global Scopes (Import läuft auch ohne gebundenen Org-Kontext),
     * dafür explizit mandanten-gefiltert. Liefert null, wenn kein Alias existiert
     * oder das Ziel inzwischen entfernt wurde.
     */
    public static function resolveModel(?int $organizationId, string $pluginId, string $externalType, string $externalId): ?Model {
        if ($organizationId === null || $externalId === '') {
            return null;
        }

        $alias = static::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', $pluginId)
            ->where('external_type', $externalType)
            ->where('external_id', $externalId)
            ->first();

        return $alias?->referenceable;
    }
}
