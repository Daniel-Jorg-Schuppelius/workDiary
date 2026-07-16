<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiCapabilitySetting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Ai;

use App\Models\Concerns\{Auditable, BelongsToOrganization};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Capability-Routing je Organisation (Feature 025, MVP-399): Opt-in
 * (Default: deaktiviert), erlaubte Verbindungen in konfigurierter
 * Reihenfolge (zugleich Fallback-Kette), Default-Verbindung und der
 * Schalter, ob Nutzer je Aufgabe aus den erlaubten Verbindungen wählen
 * dürfen. Änderungen sind auditiert (Admin-UI folgt in MVP-400).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $capability
 * @property bool $enabled
 * @property int|null $default_connection_id
 * @property list<int>|null $allowed_connection_ids
 * @property bool $allow_user_choice
 */
class AiCapabilitySetting extends Model {
    use Auditable;
    use BelongsToOrganization;

    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'capability',
        'enabled',
        'default_connection_id',
        'allowed_connection_ids',
        'allow_user_choice',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'enabled' => 'boolean',
        'allowed_connection_ids' => 'array',
        'allow_user_choice' => 'boolean',
    ];

    /** @return BelongsTo<AiProviderConnection, $this> */
    public function defaultConnection(): BelongsTo {
        return $this->belongsTo(AiProviderConnection::class, 'default_connection_id');
    }
}
