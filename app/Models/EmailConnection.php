<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EmailConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasConnectionHealth, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;

/**
 * IMAP-Eingangspostfach einer Organisation (Feature 056, MVP-117). Das Passwort
 * ist at-rest verschlüsselt (`encrypted`-Cast, APP_KEY) und nie serialisiert/
 * auditiert (`$hidden`). Der Abruf erfolgt über den Scheduler; eingehende Mails
 * landen als Vorschläge in der Integrations-Inbox (WorkDiary ist kein Mail-Client).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $host
 * @property int $port
 * @property string $encryption
 * @property string $username
 * @property string $password
 * @property string $folder
 * @property string|null $processed_folder
 * @property bool $active
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $last_polled_at
 */
class EmailConnection extends Model {
    use HasConnectionHealth;

    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    /** Tabellenname explizit (defensiv, konsistent zur Migration). */
    protected $table = 'email_connections';

    /** Geheimnisse nie serialisieren/auditieren. */
    protected $hidden = [
        'password',
    ];

    protected $fillable = [
        'organization_id',
        'name',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'folder',
        'processed_folder',
        'active',
        'last_polled_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'password' => 'encrypted',
        'port' => 'integer',
        'active' => 'boolean',
        'last_polled_at' => 'datetime',
    ];

    /** Betriebsbereit: aktiv geschaltet und vollständig konfiguriert. */
    public function isActive(): bool {
        return $this->active && $this->host !== '' && $this->username !== '' && $this->password !== '';
    }
}
