<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalReference.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property string $plugin_id
 * @property string $external_type
 * @property string $referenceable_type
 * @property int $referenceable_id
 * @property string $external_id
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $synced_at
 */
class ExternalReference extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'plugin_id',
        'external_type',
        'referenceable_type',
        'referenceable_id',
        'external_id',
        'payload',
        'synced_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'payload' => 'array',
        'synced_at' => 'datetime',
    ];

    /** @return MorphTo<Model, $this> */
    public function referenceable(): MorphTo {
        return $this->morphTo();
    }

    // ── Query-Scopes (Vollreview W3b): das Idempotenz-Rückgrat der Plugins ──
    // ersetzt die zuvor ~100 handgebauten withoutGlobalScopes()+where-Ketten.

    /**
     * Org- und Plugin-Grenze in einem Schritt — deterministisch OHNE globale
     * Scopes, mit explizitem Org-Filter (Muster aller Plugin-Aufrufstellen).
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function forPlugin(Builder $query, Organization|int|null $organization, string $pluginId, ?string $externalType = null): void {
        $orgId = $organization instanceof Organization ? $organization->getKey() : $organization;

        $query->withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('plugin_id', $pluginId);

        if ($externalType !== null) {
            $query->where('external_type', $externalType);
        }
    }

    /** @param  Builder<static>  $query */
    #[Scope]
    protected function forExternalId(Builder $query, string $externalId): void {
        $query->where('external_id', $externalId);
    }

    /** @param  Builder<static>  $query */
    #[Scope]
    protected function forReferenceable(Builder $query, Model $model): void {
        $query->where('referenceable_type', $model->getMorphClass())
            ->where('referenceable_id', $model->getKey());
    }

    /**
     * Idempotenter Link Plugin↔Model, geschlüsselt über
     * (organization, plugin, external_type, external_id).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function link(Organization|int|null $organization, string $pluginId, string $externalType, Model $model, string $externalId, array $payload = [], ?Carbon $syncedAt = null): self {
        $orgId = $organization instanceof Organization ? $organization->getKey() : $organization;

        return self::query()->withoutGlobalScopes()->updateOrCreate([
            'organization_id' => $orgId,
            'plugin_id' => $pluginId,
            'external_type' => $externalType,
            'external_id' => $externalId,
        ], [
            'referenceable_type' => $model->getMorphClass(),
            'referenceable_id' => $model->getKey(),
            'payload' => $payload === [] ? null : $payload,
            'synced_at' => $syncedAt ?? Carbon::now(),
        ]);
    }
}
