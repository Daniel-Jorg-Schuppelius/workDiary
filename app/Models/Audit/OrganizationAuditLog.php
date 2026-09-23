<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationAuditLog.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{HashChainable, HashChained};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Audit-Trail für Lebenszyklus-Ereignisse einer Organisation
 * (Deaktivieren, Reaktivieren, Export, Purge).
 *
 * Bewusst ohne FK auf organizations: die Einträge sollen den Vorgang
 * auch nach dem endgültigen Löschen einer Organisation belegen können.
 *
 * Revisionssicher (GoBD) über den {@see HashChained}-Trait: SHA-256-Hash-Kette
 * + append-only. Prüfbar via `php artisan audit:verify`.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string|null $organization_slug
 * @property string|null $organization_name
 * @property string $action
 * @property int|null $actor_user_id
 * @property string|null $actor_email
 * @property array<string,mixed>|null $payload
 * @property string|null $export_hash
 * @property string|null $prev_hash
 * @property string|null $hash
 * @property Carbon|null $created_at
 *
 * @phpstan-consistent-constructor
 */
class OrganizationAuditLog extends Model implements HashChainable {
    use HashChained;

    public const ACTION_DEACTIVATE = 'deactivate';

    public const ACTION_REACTIVATE = 'reactivate';

    public const ACTION_EXPORT = 'export';

    public const ACTION_PURGE = 'purge';

    /** @var list<string> */
    public static array $actions = [
        self::ACTION_DEACTIVATE,
        self::ACTION_REACTIVATE,
        self::ACTION_EXPORT,
        self::ACTION_PURGE,
    ];

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'organization_slug',
        'organization_name',
        'action',
        'actor_user_id',
        'actor_email',
        'payload',
        'export_hash',
        'created_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Die in den Hash eingehenden Nutzdaten dieser Zeile (feste Reihenfolge).
     *
     * @return array<string, mixed>
     */
    public function hashPayload(): array {
        return [
            'organization_id' => $this->getAttribute('organization_id') === null ? null : (int) $this->getAttribute('organization_id'),
            'organization_slug' => $this->getAttribute('organization_slug'),
            'organization_name' => $this->getAttribute('organization_name'),
            'action' => $this->getAttribute('action'),
            'actor_user_id' => $this->getAttribute('actor_user_id') === null ? null : (int) $this->getAttribute('actor_user_id'),
            'actor_email' => $this->getAttribute('actor_email'),
            'payload' => $this->getAttribute('payload'),
            'export_hash' => $this->getAttribute('export_hash'),
            'created_at' => $this->hashCreatedAt(),
        ];
    }
}
