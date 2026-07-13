<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CardDavCard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lokaler Sync-Spiegel einer CardDAV-Karte (Bauturbo A9, MVP-329):
 * href → UID/ETag je Verbindung. Grundlage für den ETag-Fallback bei Servern
 * ohne sync-collection (RFC 6578) und für die Idempotenz des Imports —
 * unveränderte Karten (gleicher ETag) werden übersprungen. Kein Kontaktinhalt:
 * die Daten selbst leben ausschließlich als Snapshot in der Integrations-Inbox
 * bzw. in ExternalReference.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $carddav_connection_id
 * @property string $href
 * @property string|null $uid
 * @property string $etag
 */
class CardDavCard extends Model {
    use BelongsToOrganization;

    /** Tabellenname explizit (Klassenname würde sonst zu `card_dav_cards`). */
    protected $table = 'carddav_cards';

    protected $fillable = [
        'organization_id',
        'carddav_connection_id',
        'href',
        'uid',
        'etag',
    ];

    /** @return BelongsTo<CardDavConnection, $this> */
    public function connection(): BelongsTo {
        return $this->belongsTo(CardDavConnection::class, 'carddav_connection_id');
    }
}
