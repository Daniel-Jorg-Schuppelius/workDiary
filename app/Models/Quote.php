<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Quote.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Angebot (Feature 066, MVP-170): versioniert, mit Optionen, Bindefrist
 * und kontrollierter Überführung — Annahme friert den Stand ein
 * (decision_snapshot), keine stille Rückwirkung.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $customer_id
 * @property string $number
 * @property int $version
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $valid_until
 * @property string|null $acceptance_token_hash
 * @property array<string, mixed>|null $decision_snapshot
 * @property float $subtotal
 * @property float $tax_amount
 * @property float $total
 * @property int|null $previous_version_id
 * @property int|null $created_by
 */
class Quote extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const STATUSES = ['draft', 'approved', 'sent', 'accepted', 'partially_accepted', 'rejected', 'expired'];

    protected $fillable = [
        'organization_id', 'customer_id', 'project_id', 'number', 'version',
        'previous_version_id', 'status', 'valid_until', 'terms',
        'subtotal', 'tax_amount', 'total', 'acceptance_token_hash',
        'decided_at', 'decision_snapshot', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'valid_until' => 'date',
        'decided_at' => 'datetime',
        'decision_snapshot' => 'array',
        'version' => 'integer',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'draft', 'version' => 1];

    /** @return HasMany<QuoteItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(QuoteItem::class)->orderBy('position');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    public function isExpired(): bool {
        return $this->valid_until !== null && $this->valid_until->isPast()
            && in_array($this->status, ['approved', 'sent'], true);
    }

    public function recalculate(): void {
        $sub = 0.0;
        $tax = 0.0;
        foreach ($this->items as $item) {
            // Vor der Entscheidung (accepted=null) zählen Pflichtpositionen,
            // Optionen nicht; nach der Entscheidung zählt NUR Angenommenes.
            $counts = $item->accepted ?? ! $item->optional;
            if (! $counts) {
                continue;
            }
            $net = round((float) $item->quantity * (float) $item->unit_price, 2);
            $sub += $net;
            $tax += round($net * ((float) ($item->tax_rate ?? 19)) / 100, 2);
        }
        $this->subtotal = round($sub, 2);
        $this->tax_amount = round($tax, 2);
        $this->total = round($sub + $tax, 2);
    }
}
