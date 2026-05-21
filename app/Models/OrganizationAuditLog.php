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

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Audit-Trail für Lebenszyklus-Ereignisse einer Organisation
 * (Deaktivieren, Reaktivieren, Export, Purge).
 *
 * Bewusst ohne FK auf organizations: die Einträge sollen den Vorgang
 * auch nach dem endgültigen Löschen einer Organisation belegen können.
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
 * @property Carbon|null $created_at
 */
class OrganizationAuditLog extends Model {
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
}
