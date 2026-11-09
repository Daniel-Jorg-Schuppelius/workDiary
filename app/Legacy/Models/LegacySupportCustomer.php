<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacySupportCustomer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\{Builder, Collection, Model};
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Konto des Support-Portals im Altsystem (`kp_user`): Firma, Anmeldename und
 * Klartext-Passwort wie im Altportal.
 *
 * @property int $id
 * @property string $kunde
 * @property string $usern
 * @property string $passw
 * @property-read Collection<int, LegacySupportOrder> $orders
 * @property-read int|null $orders_count
 * @property-read Collection<int, LegacySupportArchivedOrder> $archivedOrders
 * @property-read int|null $archived_orders_count
 *
 * @method static Builder<static>|LegacySupportCustomer newModelQuery()
 * @method static Builder<static>|LegacySupportCustomer newQuery()
 * @method static Builder<static>|LegacySupportCustomer query()
 * @method static Builder<static>|LegacySupportCustomer customers()
 */
class LegacySupportCustomer extends Model {
    /** Die Konten davor sind die Bürozugänge des Altportals (`kunde = 'self'`), keine Kunden. */
    public const int FIRST_CUSTOMER_ID = 10;

    protected $connection = 'legacy';

    protected $table = 'kp_user';

    public $timestamps = false;

    protected $fillable = ['kunde', 'usern', 'passw'];

    protected $hidden = ['passw'];

    protected $primaryKey = 'id';

    #[Scope]
    protected function customers(Builder $query): void {
        $query->where('id', '>=', self::FIRST_CUSTOMER_ID);
    }

    public function isCustomer(): bool {
        return (int) $this->id >= self::FIRST_CUSTOMER_ID;
    }

    public function orders(): HasMany {
        return $this->hasMany(LegacySupportOrder::class, 'kunde', 'id');
    }

    public function archivedOrders(): HasMany {
        return $this->hasMany(LegacySupportArchivedOrder::class, 'kunde', 'id');
    }
}
