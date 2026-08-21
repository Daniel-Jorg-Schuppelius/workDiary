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

use App\Casts\MoneyCast;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
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
 * @property \Illuminate\Support\Carbon|null $follow_up_at
 * @property int|null $follow_up_user_id
 * @property \Illuminate\Support\Carbon|null $followed_up_at
 * @property array<string, mixed>|null $decision_snapshot
 * @property Money|null $subtotal
 * @property Money|null $tax_amount
 * @property Money|null $total
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
        // Nachfassen (Feature 112, MVP-601) — Vertriebstermin, nicht Rechtsfrist.
        'follow_up_at', 'follow_up_user_id', 'followed_up_at',
        'subtotal', 'tax_amount', 'total', 'acceptance_token_hash',
        'decided_at', 'decision_snapshot', 'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'valid_until' => 'date',
        'follow_up_at' => 'date',
        'followed_up_at' => 'datetime',
        'decided_at' => 'datetime',
        'decision_snapshot' => 'array',
        'version' => 'integer',
        // Angebote führen keine Währungsspalte — der Cast fällt auf Euro zurück.
        'subtotal' => MoneyCast::class,
        'tax_amount' => MoneyCast::class,
        'total' => MoneyCast::class,
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

    /** @return BelongsTo<User, $this> */
    public function followUpUser(): BelongsTo {
        return $this->belongsTo(User::class, 'follow_up_user_id');
    }

    /**
     * Steht das Nachfassen an? (Feature 112, MVP-601)
     *
     * Nur für versandte/freigegebene Angebote — vor dem Versand gibt es nichts
     * nachzufassen. `followed_up_at` schließt den Fall ab, bis ein neuer
     * Termin gesetzt wird.
     */
    public function isFollowUpDue(): bool {
        return $this->follow_up_at !== null
            && $this->followed_up_at === null
            && ! $this->follow_up_at->isFuture()
            && in_array($this->status, ['approved', 'sent'], true);
    }

    public function isExpired(): bool {
        return $this->valid_until !== null && $this->valid_until->isPast()
            && in_array($this->status, ['approved', 'sent'], true);
    }

    public function recalculate(): void {
        // Angebote führen keine eigene Währungsspalte — Belegwährung ist der Euro.
        $currency = CurrencyCode::Euro;
        $sub = Money::zero($currency);
        $tax = Money::zero($currency);
        $fallbackRate = null;
        foreach ($this->items as $item) {
            // Vor der Entscheidung (accepted=null) zählen Pflichtpositionen,
            // Optionen nicht; nach der Entscheidung zählt NUR Angenommenes.
            $counts = $item->accepted ?? ! $item->optional;
            if (! $counts) {
                continue;
            }
            // MVP-416: Zeilennetto inkl. Positionsrabatt.
            $net = $item->netAmount();
            $sub = $sub->plus($net);
            // Positionen ohne eigenen Satz: Satz aus dem TaxResolver
            // (§ 19 UStG → 0, Org-Override, Länderkatalog) statt hart 19 % —
            // sonst zeigt eine Kleinunternehmer-Org falsche Bruttopreise.
            $rate = $item->tax_rate !== null
                ? (float) $item->tax_rate->getNumericValue()
                : ($fallbackRate ??= $this->defaultTaxRate());
            $tax = $tax->plus($net->percentage($rate));
        }
        $this->subtotal = $sub;
        $this->tax_amount = $tax;
        $this->total = $sub->plus($tax);
    }

    /** Steuersatz-Fallback für Positionen ohne eigenen Satz (s. recalculate). */
    private function defaultTaxRate(): float {
        $organization = $this->organization()->first();
        $customer = $this->customer()->first();
        if ($organization === null || $customer === null) {
            return 19.0; // defensiv: Alt-Verhalten ohne aufgelösten Kontext
        }

        return (float) app(\App\Services\Invoicing\TaxResolver::class)->resolve($organization, $customer)['rate'];
    }
}
