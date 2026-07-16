<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainDnsZoneProjection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Domain;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * DNS-Zonen-Projektion (Feature 083, MVP-389). Vor einem vollständigen
 * `rrN`-Replace wird der gelesene Zustand (Records) als Snapshot gehalten;
 * nach jeder Mutation liest WorkDiary die Zone erneut und zeigt Abweichungen
 * als Konflikt.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $connection_id
 * @property int|null $domain_projection_id
 * @property string $zone
 * @property string $zone_hash
 * @property array<string, mixed>|null $soa
 * @property string|null $revision
 * @property string|null $raw_hash
 * @property Carbon|null $synced_at
 */
class DomainDnsZoneProjection extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'connection_id',
        'domain_projection_id',
        'zone',
        'zone_hash',
        'soa',
        'revision',
        'raw_hash',
        'synced_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'soa' => 'array',
        'synced_at' => 'datetime',
    ];

    public static function hashFor(string $zone): string {
        return hash('sha256', mb_strtolower(trim($zone)));
    }

    /** @return BelongsTo<DomainProviderConnection, $this> */
    public function connection(): BelongsTo {
        return $this->belongsTo(DomainProviderConnection::class, 'connection_id');
    }

    /** @return BelongsTo<DomainProjection, $this> */
    public function domainProjection(): BelongsTo {
        return $this->belongsTo(DomainProjection::class, 'domain_projection_id');
    }

    /** @return HasMany<DomainDnsRecordProjection, $this> */
    public function records(): HasMany {
        return $this->hasMany(DomainDnsRecordProjection::class, 'zone_id')->orderBy('position');
    }
}
