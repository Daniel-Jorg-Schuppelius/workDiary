<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudDocumentItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\CloudIntake;

use App\Enums\CloudIntake\{CloudIntakeItemStatus, CloudIntakeProvider, CloudIntakeRouteTarget};
use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};
use Illuminate\Support\Carbon;

/**
 * Übergabenachweis des Cloud-Dokumenteingangs (Feature 080, MVP-352):
 * eine Zeile je Provider-Item-Revision. Der Nachweis überlebt Trennung der
 * Verbindung und Remote-Löschung (Status `source_gone`) — lokale Dokumente
 * werden nie automatisch entfernt. Unique läuft über den Hash aus
 * (external_item_id, revision), weil Provider-IDs den MySQL-Indexrahmen
 * sprengen ({@see itemRevisionHash()}).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $connection_id
 * @property int|null $route_id
 * @property CloudIntakeProvider $provider
 * @property string $external_item_id
 * @property string $revision
 * @property string $source_path
 * @property string|null $sha256
 * @property int $size
 * @property CloudIntakeItemStatus $status
 * @property string|null $status_reason
 * @property CloudIntakeRouteTarget|null $target
 * @property string|null $imported_type
 * @property int|null $imported_id
 * @property Carbon|null $imported_at
 * @property string $item_revision_hash
 */
class CloudDocumentItem extends Model {
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id',
        'connection_id',
        'route_id',
        'provider',
        'external_item_id',
        'revision',
        'source_path',
        'sha256',
        'size',
        'status',
        'status_reason',
        'target',
        'imported_type',
        'imported_id',
        'imported_at',
        'item_revision_hash',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'provider' => CloudIntakeProvider::class,
        'status' => CloudIntakeItemStatus::class,
        'target' => CloudIntakeRouteTarget::class,
        'size' => 'integer',
        'imported_at' => 'datetime',
    ];

    protected static function booted(): void {
        static::saving(function (self $item): void {
            $item->item_revision_hash = self::itemRevisionHash($item->external_item_id, $item->revision);
        });
    }

    /** Kanonischer Unique-Schlüssel je Item-Revision. */
    public static function itemRevisionHash(string $externalItemId, string $revision): string {
        return CryptoHelper::hash($externalItemId . '|' . $revision);
    }

    /** @return BelongsTo<CloudDocumentConnection, $this> */
    public function connection(): BelongsTo {
        return $this->belongsTo(CloudDocumentConnection::class, 'connection_id');
    }

    /** @return BelongsTo<CloudDocumentRoute, $this> */
    public function route(): BelongsTo {
        return $this->belongsTo(CloudDocumentRoute::class, 'route_id');
    }

    /**
     * Importiertes Zielobjekt (Document/DocumentVersion/IncomingInvoice).
     *
     * @return MorphTo<Model, $this>
     */
    public function imported(): MorphTo {
        return $this->morphTo('imported');
    }
}
