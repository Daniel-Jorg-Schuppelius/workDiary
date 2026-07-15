<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Sync\SyncCommandStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Idempotenz-Register der Offline-Sync-Befehle (Feature 035, Phase 1).
 * Eine Zeile = ein verarbeiteter Outbox-Befehl; das Unique (user_id,
 * client_uuid) macht Wiederholungen nach Verbindungsabbruch erkennbar
 * ({@see \App\Services\Sync\SyncCommandService}).
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $user_id
 * @property string $client_uuid
 * @property string $type
 * @property array<string, mixed>|null $payload
 * @property SyncCommandStatus $result_status
 * @property string|null $result_ref
 * @property array<string, mixed>|null $result_errors
 * @property Carbon|null $captured_at
 */
class SyncCommand extends Model {
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'user_id',
        'client_uuid',
        'type',
        'payload',
        'result_status',
        'result_ref',
        'result_errors',
        'captured_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'payload' => 'array',
        'result_status' => SyncCommandStatus::class,
        'result_errors' => 'array',
        'captured_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
