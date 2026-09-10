<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResalePeriod.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Reselling;

use App\Casts\MoneyCast;
use App\Enums\Reselling\PeriodStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{LexofficeVoucher, LexofficeVoucherLine, Organization, User};
use App\Services\Reselling\Register\LicenseMonths;
use App\Support\Query\DateRange;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Erwartete Abrechnungsperiode eines Abos (Feature 152). Eindeutig je
 * (Abo, Beginn); der Status hält die Entscheidung — offen, berechnet,
 * teilweise, verzichtet, strittig — und überlebt jede Neuplanung.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $subscription_id
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property int $quantity
 * @property Money|null $expected_purchase
 * @property Money|null $expected_sale
 * @property CurrencyCode $currency
 * @property PeriodStatus $status
 * @property string|null $waived_reason
 * @property string|null $note
 * @property string|null $draft_reference  Lexoffice-Entwurfs-ID bzw. lokale Rechnungsnummer des Rechnungsvorschlags
 * @property CarbonImmutable|null $draft_created_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ResalePeriodLink> $links
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ResalePurchaseEntry> $purchases
 * @property int|null $decided_by_user_id
 * @property CarbonImmutable|null $decided_at
 * @property-read ResaleSubscription $subscription
 */
class ResalePeriod extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'resale_periods';

    protected $fillable = [
        'organization_id',
        'subscription_id',
        'starts_on',
        'ends_on',
        'quantity',
        'expected_purchase',
        'expected_sale',
        'currency',
        'status',
        'waived_reason',
        'note',
        'draft_reference',
        'draft_created_at',
        'decided_by_user_id',
        'decided_at',
    ];

    /** Höchstlänge der Bemerkungsspalte (string 255). */
    private const NOTE_LIMIT = 255;

    protected $casts = [
        'starts_on' => 'immutable_date',
        'ends_on' => 'immutable_date',
        'quantity' => 'integer',
        'currency' => CurrencyCode::class,
        'expected_purchase' => MoneyCast::class . ':currency,2',
        'expected_sale' => MoneyCast::class . ':currency,2',
        'status' => PeriodStatus::class,
        'decided_at' => 'immutable_datetime',
        'draft_created_at' => 'immutable_datetime',
    ];

    /**
     * Stichtag: der Kalendertag in Ortszeit (`Tz`) als Datumswert — bewusst
     * ohne Zeitanteil und in der App-Zeitzone wie die `immutable_date`-Spalten,
     * damit `starts_on->greaterThan(today())` am Periodenbeginn nicht am
     * UTC-Versatz scheitert (00:00–02:00 Ortszeit).
     */
    public static function today(): CarbonImmutable {
        return CarbonImmutable::parse(Tz::now()->toDateString());
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<ResaleSubscription, $this> */
    public function subscription(): BelongsTo {
        return $this->belongsTo(ResaleSubscription::class, 'subscription_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    /** @return HasMany<ResalePurchaseEntry, $this> */
    public function purchases(): HasMany {
        return $this->hasMany(ResalePurchaseEntry::class, 'period_id');
    }

    /** Ist-Einkauf aus Einkaufsbelegen (null, wenn keiner zugeteilt ist). */
    public function actualPurchase(): ?float {
        if ($this->purchases->isEmpty()) {
            return null;
        }

        return (float) $this->purchases->sum(static fn(ResalePurchaseEntry $e): float => $e->net_amount->toFloat());
    }

    /** @return HasMany<ResalePeriodLink, $this> */
    public function links(): HasMany {
        return $this->hasMany(ResalePeriodLink::class, 'period_id')->orderBy('voucher_date');
    }

    /** Länge der Periode in Monaten (Intervall des Abos: 12 oder 1). */
    public function termMonths(): int {
        return max(1, LicenseMonths::monthsBetween($this->starts_on, $this->ends_on));
    }

    /** Benötigte Lizenzmonate: Menge × Periodenlänge. */
    public function requiredMonths(): float {
        return (float) ($this->quantity * $this->termMonths());
    }

    /** Durch Bezüge gedeckte Lizenzmonate (Vorschläge eingeschlossen). */
    public function coveredMonths(): float {
        return (float) $this->links->sum(static fn(ResalePeriodLink $l): float => (float) $l->months);
    }

    /** Noch nicht gedeckte Lizenzmonate. */
    public function openMonths(): float {
        return max(0.0, $this->requiredMonths() - $this->coveredMonths());
    }

    /** Erwarteter Verkauf der offenen Monate: Soll × offen/benötigt (null ohne Verkaufspreis). */
    public function openAmount(): ?Money {
        $sale = $this->expected_sale;
        $required = $this->requiredMonths();
        if ($sale === null || $required <= 0.0) {
            return null;
        }

        return $sale->times($this->openMonths())->dividedBy($required)->withScale(2);
    }

    /** Status aus der Deckung: voll = berechnet, etwas = teilweise, nichts = offen (Toleranz 0,001). */
    public function statusFromCoverage(float $covered): PeriodStatus {
        if ($covered >= $this->requiredMonths() - 0.001) {
            return PeriodStatus::Billed;
        }

        return $covered > 0.001 ? PeriodStatus::Partial : PeriodStatus::Open;
    }

    /** Nur Vorschläge, noch nichts bestätigt oder von Hand gesetzt. */
    public function isProposedOnly(): bool {
        return $this->links->isNotEmpty() && $this->links->every(static fn(ResalePeriodLink $l): bool => ! $l->origin->isDecided());
    }

    /**
     * Vom Nutzer entschieden (bestätigt, manuell verknüpft, verzichtet,
     * strittig) — die Planung fasst Menge, Ende und Preis nicht mehr an.
     * Ein Status „berechnet" allein durch Vorschläge ist keine Entscheidung.
     */
    public function isLocked(): bool {
        return $this->decided_at !== null || in_array($this->status, [PeriodStatus::Waived, PeriodStatus::Disputed], true);
    }

    /** Bemerkung anhängen (Trenner „ · "), bestehende bleibt; auf die Spaltenlänge gekürzt. */
    public function appendNote(string $text): void {
        $this->note = self::joinNote($this->note, $text);
    }

    public static function joinNote(?string $existing, string $text): string {
        $existing = trim((string) $existing);
        $text = trim($text);
        $joined = $existing === '' ? $text : ($text === '' ? $existing : $existing . ' · ' . $text);

        return mb_substr($joined, 0, self::NOTE_LIMIT);
    }

    /**
     * Ist der Rechnungsvorschlag inzwischen eine Rechnung? Ein entschiedener
     * Bezug trägt die Nummer des lokalen Entwurfs oder der gespiegelte Beleg
     * die Lexoffice-ID des Entwurfs (Lexoffice behält die ID beim Abschließen).
     */
    public function draftIsInvoiced(): bool {
        $reference = $this->draft_reference;
        if ($reference === null || $reference === '') {
            return false;
        }
        foreach ($this->links as $link) {
            if (! $link->origin->isDecided()) {
                continue;
            }
            if ($link->voucher_number === $reference) {
                return true;
            }
            $linkable = $link->linkable;
            $voucher = $linkable instanceof LexofficeVoucherLine ? $linkable->voucher : ($linkable instanceof LexofficeVoucher ? $linkable : null);
            if ($voucher !== null && $voucher->external_id === $reference) {
                return true;
            }
        }

        return false;
    }

    /**
     * Offene Perioden fremder Halter, deren Beginn erreicht ist — nur die
     * können fehlen. Eigener Bestand wird nie berechnet und zählt nicht.
     *
     * @param  Builder<ResalePeriod>  $query
     * @return Builder<ResalePeriod>
     */
    public function scopeDue(Builder $query, ?CarbonImmutable $reference = null): Builder {
        return $query->where('status', PeriodStatus::Open->value)
            ->where('starts_on', '<', DateRange::dayAfter($reference ?? self::today()))
            ->whereHas('subscription', static fn(Builder $s) => $s->where('is_own_holding', false));
    }

    public function label(): string {
        return $this->starts_on->format('d.m.Y') . ' – ' . $this->ends_on->format('d.m.Y');
    }
}
