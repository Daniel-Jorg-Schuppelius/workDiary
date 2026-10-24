<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GlpiConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Glpi\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasConnectionHealth};
use App\Plugins\Support\OAuthConnectionStatus;
use App\Plugins\Support\TaskSync\{IsTaskImportConnection, TaskImportConnection};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Geprüfte Verbindung zu einer GLPI-Instanz (Feature 174): eine je
 * Organisation, `source_id` ist der Instanzschlüssel aus der Adresse. Der
 * Zugang liegt in den Plugin-Einstellungen, nicht hier.
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $source_id
 * @property string|null $source_name
 * @property string|null $source_url
 * @property string|null $account_id
 * @property string|null $account_name
 * @property OAuthConnectionStatus $status
 * @property CarbonImmutable|null $last_synced_at
 * @property array<string, int>|null $last_sync_counters
 * @property string|null $last_error
 * @property Carbon|null $last_error_at
 * @property int $consecutive_failures
 * @property Carbon|null $disabled_at
 * @property Carbon|null $connected_at
 * @property Carbon|null $disconnected_at
 */
class GlpiConnection extends Model implements TaskImportConnection {
    use Auditable;
    use BelongsToOrganization;
    use HasConnectionHealth;
    use IsTaskImportConnection;

    protected $table = 'glpi_connections';

    protected $fillable = ['organization_id'];

    /** Der Zugang steht in den Plugin-Einstellungen, ob er gesetzt ist, prüft der Lauf über die Konfiguration. */
    protected function storesTokens(): bool {
        return false;
    }
}
