<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceTemplate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Invoicing;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Supplier\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Belegvorlage mit Zonen (Phase 151, MVP-1219): Fingerabdruck aus Kopf und Fuß
 * der ersten Seite plus Zonen je Feld (relativ zur Seitengröße). Gezeichnet im
 * Editor oder aus Korrekturen gelernt; eine gelernte Zone gilt erst nach dem
 * zweiten übereinstimmenden Treffer als bestätigt.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $supplier_id
 * @property string $name
 * @property array<string, mixed> $fingerprint
 * @property list<array<string, mixed>> $zones
 * @property string $source
 * @property int $hits
 * @property \Illuminate\Support\Carbon|null $last_matched_at
 * @property int|null $created_by
 */
class IncomingInvoiceTemplate extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_LEARNED = 'learned';

    /** @var list<string> */
    protected $fillable = ['organization_id', 'supplier_id', 'name', 'fingerprint', 'zones', 'source', 'hits', 'last_matched_at', 'created_by'];

    /** @var array<string, string> */
    protected $casts = [
        'fingerprint' => 'array',
        'zones' => 'array',
        'hits' => 'integer',
        'last_matched_at' => 'datetime',
    ];

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo {
        return $this->belongsTo(Supplier::class);
    }

    /** @return list<array<string, mixed>> */
    public function zoneList(): array {
        return array_values(array_filter((array) $this->zones, 'is_array'));
    }

    /** @return list<string> Felder, für die eine Zone existiert */
    public function fieldKeys(): array {
        return array_values(array_unique(array_map(static fn (array $zone): string => (string) ($zone['field'] ?? ''), $this->zoneList())));
    }
}
