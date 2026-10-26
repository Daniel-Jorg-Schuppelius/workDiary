<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleProviderSupplier.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Reselling;

use App\Enums\Reselling\SubscriptionProvider;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Supplier\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lieferant im Stamm → Anbieter des Reselling-Registers (Feature 185, MVP-1419).
 * Eingangsrechnungen dieses Lieferanten werden nach der Zuordnung im
 * Rechnungseingang als Einkauf übernommen.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $supplier_id
 * @property SubscriptionProvider $provider
 * @property string|null $note
 * @property-read Supplier $supplier
 */
class ResaleProviderSupplier extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'resale_provider_suppliers';

    protected $fillable = ['organization_id', 'supplier_id', 'provider', 'note'];

    protected $casts = ['provider' => SubscriptionProvider::class];

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }
}
