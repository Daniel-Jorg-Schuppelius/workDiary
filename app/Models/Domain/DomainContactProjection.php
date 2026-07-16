<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainContactProjection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Domain;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Registry-Kontakt (Contact-Handle) als providergeführte Projektion
 * (Feature 083, MVP-386). Nur ein minimierter, redigierter Snapshot; die
 * Handles werden NICHT mit dem WorkDiary-Kundenstamm synchronisiert.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $connection_id
 * @property string $external_handle
 * @property string|null $external_user
 * @property array<string, mixed>|null $snapshot
 * @property string|null $revision
 * @property string|null $raw_hash
 * @property Carbon|null $synced_at
 */
class DomainContactProjection extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'connection_id',
        'external_handle',
        'external_user',
        'snapshot',
        'revision',
        'raw_hash',
        'synced_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'snapshot' => 'array',
        'synced_at' => 'datetime',
    ];

    /** @return BelongsTo<DomainProviderConnection, $this> */
    public function connection(): BelongsTo {
        return $this->belongsTo(DomainProviderConnection::class, 'connection_id');
    }
}
