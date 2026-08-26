<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WarehouseBin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Optionaler Lagerplatz innerhalb eines Lagerorts (Feature 048, MVP-706).
 * Gesperrte oder inaktive Plätze nehmen keine Buchungen an; der Ledger
 * referenziert den Platz mit RESTRICT — ein Platz mit Bewegungen bleibt.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $warehouse_id
 * @property string $code
 * @property string|null $name
 * @property bool $active
 * @property bool $blocked
 * @property int $sort_order
 */
class WarehouseBin extends Model {
    use Auditable;
    use BelongsToOrganization;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasSqid;

    protected $fillable = [
        'organization_id',
        'warehouse_id',
        'code',
        'name',
        'active',
        'blocked',
        'sort_order',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'active' => 'boolean',
        'blocked' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** Aktiv und nicht gesperrt — nur dann sind Zu-/Abgänge zulässig. */
    public function isUsable(): bool {
        return $this->active && ! $this->blocked;
    }

    /** Anzeigename: Kürzel, bei Bezeichnung „Kürzel — Bezeichnung". */
    public function displayLabel(): string {
        $name = trim((string) $this->name);

        return $name === '' ? $this->code : $this->code . ' — ' . $name;
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return HasMany<StockMovement, $this> */
    public function movements(): HasMany {
        return $this->hasMany(StockMovement::class, 'bin_id');
    }

    /** @return HasMany<StockReservation, $this> */
    public function reservations(): HasMany {
        return $this->hasMany(StockReservation::class, 'bin_id');
    }
}
