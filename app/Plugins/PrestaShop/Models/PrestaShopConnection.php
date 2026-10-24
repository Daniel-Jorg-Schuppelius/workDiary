<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrestaShopConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\PrestaShop\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasConnectionHealth};
use App\Plugins\Support\OAuthConnectionStatus;
use App\Plugins\Support\ShopOrders\{IsShopConnection, ShopConnection};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Geprüfte Verbindung zu einem PrestaShop-Shop (Feature 175): eine je
 * Organisation, `source_id` ist der Instanzschlüssel aus der Shop-Adresse.
 * Die Schlüssel liegen in den Plugin-Einstellungen, nicht hier.
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $source_id
 * @property string|null $source_name
 * @property string|null $source_url
 * @property string|null $account_name
 * @property OAuthConnectionStatus $status
 * @property Carbon|null $import_confirmed_at
 * @property CarbonImmutable|null $checkpoint_at
 * @property CarbonImmutable|null $last_full_sync_at
 * @property CarbonImmutable|null $last_synced_at
 * @property array<string, int>|null $last_sync_counters
 * @property bool|null $is_last_run_complete
 * @property string|null $last_error
 * @property Carbon|null $last_error_at
 * @property int $consecutive_failures
 * @property Carbon|null $disabled_at
 * @property Carbon|null $connected_at
 * @property Carbon|null $disconnected_at
 */
class PrestaShopConnection extends Model implements ShopConnection {
    use Auditable;
    use BelongsToOrganization;
    use HasConnectionHealth;
    use IsShopConnection;

    protected $table = 'prestashop_connections';

    protected $fillable = ['organization_id'];
}
