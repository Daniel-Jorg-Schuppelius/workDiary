<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevOnlineConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\DatevOnline\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasConnectionHealth};
use App\Plugins\DatevOnline\Enums\DatevConnectionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * DATEV-Verbindung einer Organisation (MVP-122): Token verschlüsselt, der
 * gewählte Mandant als Verbundnummer „Beraternummer-Mandantennummer“, ab wann
 * Belegbilder übertragen werden.
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property string|null $scopes
 * @property DatevConnectionStatus $status
 * @property string|null $datev_client_number
 * @property string|null $datev_client_name
 * @property bool $is_documents_enabled
 * @property Carbon|null $documents_since
 * @property Carbon|null $last_synced_at
 * @property string|null $last_error
 * @property Carbon|null $disabled_at
 */
class DatevOnlineConnection extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasConnectionHealth;

    protected $table = 'datev_online_connections';

    protected $hidden = ['access_token', 'refresh_token'];

    protected $fillable = [
        'organization_id', 'access_token', 'refresh_token', 'token_expires_at', 'scopes', 'status',
        'datev_client_number', 'datev_client_name', 'is_documents_enabled', 'documents_since', 'last_synced_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'status' => DatevConnectionStatus::class,
        'is_documents_enabled' => 'boolean',
        'documents_since' => 'date',
        'last_synced_at' => 'datetime',
        'last_error_at' => 'datetime',
        'disabled_at' => 'datetime',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    public function isActive(): bool {
        return $this->status === DatevConnectionStatus::Active
            && trim((string) $this->access_token) !== ''
            && $this->disabled_at === null;
    }

    /** Verbunden und ein Mandant gewählt — erst dann wird übertragen. */
    public function isReady(): bool {
        return $this->isActive() && $this->datev_client_number !== null;
    }
}
