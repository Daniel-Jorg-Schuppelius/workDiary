<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AsanaConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Asana\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasConnectionHealth};
use App\Plugins\Support\OAuthConnectionStatus;
use App\Plugins\Support\TaskSync\{IsTaskImportConnection, TaskImportConnection};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Asana-Verbindung einer Organisation (Feature 169): eine je
 * Organisation, `source_id` ist der gewählte Workspace (GID) aus `available_sources`,
 * `account_id` das Asana-Konto. Tokens verschlüsselt und nie serialisiert.
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $source_id
 * @property string|null $source_name
 * @property string|null $source_url
 * @property array<int, mixed>|null $available_sources
 * @property string|null $account_id
 * @property string|null $account_name
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property string|null $scopes
 * @property OAuthConnectionStatus $status
 * @property CarbonImmutable|null $last_synced_at
 * @property array<string, int>|null $last_sync_counters
 * @property string|null $last_error
 * @property Carbon|null $last_error_at
 * @property int $consecutive_failures
 * @property Carbon|null $disabled_at
 * @property int|null $connected_by
 * @property Carbon|null $connected_at
 * @property Carbon|null $disconnected_at
 */
class AsanaConnection extends Model implements TaskImportConnection {
    use Auditable;
    use BelongsToOrganization;
    use HasConnectionHealth;
    use IsTaskImportConnection;

    protected $table = 'asana_connections';

    protected $hidden = ['access_token', 'refresh_token'];

    protected $fillable = ['organization_id'];
}
