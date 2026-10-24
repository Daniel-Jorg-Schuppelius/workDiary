<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FireflyConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Firefly\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasConnectionHealth};
use App\Plugins\Support\OAuthConnectionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Geprüfte Verbindung zu einer Firefly-III-Instanz (Feature 176): eine je
 * Organisation, `source_id` ist der Instanzschlüssel aus der Adresse. Der
 * Token liegt in den Plugin-Einstellungen, nicht hier.
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $source_id
 * @property string|null $source_name
 * @property string|null $source_url
 * @property string|null $account_name
 * @property OAuthConnectionStatus $status
 * @property CarbonImmutable|null $last_synced_at
 * @property array<string, int>|null $last_sync_counters
 * @property bool|null $is_last_run_complete
 * @property string|null $last_error
 * @property Carbon|null $last_error_at
 * @property Carbon|null $disabled_at
 * @property Carbon|null $connected_at
 */
class FireflyConnection extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasConnectionHealth;

    protected $table = 'firefly_connections';

    protected $fillable = ['organization_id'];

    protected $casts = [
        'status' => OAuthConnectionStatus::class,
        'last_synced_at' => 'immutable_datetime',
        'last_sync_counters' => 'array',
        'is_last_run_complete' => 'boolean',
        'last_error_at' => 'datetime',
        'disabled_at' => 'datetime',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    public function isReady(): bool {
        return $this->status === OAuthConnectionStatus::Active && $this->disabled_at === null && trim((string) $this->source_id) !== '';
    }
}
