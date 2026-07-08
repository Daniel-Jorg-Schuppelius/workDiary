<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Shipment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Shipping\ShipmentStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use Illuminate\Database\Eloquent\Factories\{Factory, HasFactory};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Versandauftrag einer Organisation (Feature 059, MVP-128): erzeugt aus einer
 * Auslieferung ({@see StockDelivery}), trägt Carrier, Trackingnummer,
 * Carrier-Sendungs-ID (Storno), Status und Sendungsverlauf. Das Label-PDF liegt
 * als {@see Attachment} (`meta_type = shipping_label`) am Versandauftrag.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $stock_delivery_id
 * @property string $carrier
 * @property ShipmentStatus $status
 * @property string|null $tracking_number
 * @property string|null $carrier_shipment_id
 * @property string|null $billing_number
 * @property array<string, mixed>|null $recipient_snapshot
 * @property array<int, array<string, mixed>>|null $events
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $last_tracked_at
 */
class Shipment extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasSqid;

    /** meta_type des Label-Attachments. */
    public const LABEL_META = 'shipping_label';

    protected $table = 'shipments';

    protected $fillable = [
        'organization_id',
        'stock_delivery_id',
        'carrier',
        'status',
        'tracking_number',
        'carrier_shipment_id',
        'billing_number',
        'recipient_snapshot',
        'events',
        'last_tracked_at',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => ShipmentStatus::class,
        'recipient_snapshot' => 'array',
        'events' => 'array',
        'last_tracked_at' => 'datetime',
    ];

    /** Bereits gelabelt (Trackingnummer vorhanden) — Idempotenzgrenze. */
    public function isLabeled(): bool {
        return $this->tracking_number !== null && $this->tracking_number !== '';
    }

    /** @return BelongsTo<StockDelivery, $this> */
    public function delivery(): BelongsTo {
        return $this->belongsTo(StockDelivery::class, 'stock_delivery_id');
    }
}
