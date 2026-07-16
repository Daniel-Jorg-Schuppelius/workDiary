<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainDnsRecordProjection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Domain;

use App\Enums\Domain\DomainDnsRecordType;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Einzelner DNS-Resource-Record einer Zone (Feature 083, MVP-389). Typisiert
 * gehalten; die Serialisierung ins Provider-RR-Format erfolgt im
 * {@see \App\Services\Domain\DomainDnsService}, nicht in der UI.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $zone_id
 * @property DomainDnsRecordType $type
 * @property string $name
 * @property int|null $ttl
 * @property int|null $priority
 * @property string $content
 * @property string|null $raw
 * @property int $position
 */
class DomainDnsRecordProjection extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'zone_id',
        'type',
        'name',
        'ttl',
        'priority',
        'content',
        'raw',
        'position',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'type' => DomainDnsRecordType::class,
        'ttl' => 'integer',
        'priority' => 'integer',
        'position' => 'integer',
    ];

    /** @return BelongsTo<DomainDnsZoneProjection, $this> */
    public function zone(): BelongsTo {
        return $this->belongsTo(DomainDnsZoneProjection::class, 'zone_id');
    }
}
