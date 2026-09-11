<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResalePeriodLink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Reselling;

use App\Casts\MoneyCast;
use App\Enums\Reselling\LinkOrigin;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\{Organization, User};
use App\Services\Reselling\Mirror\{InvoiceMirror, MirrorLine};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Rechnungsbezug einer Periode (Feature 152, MVP-761): Belegposition einer
 * Spiegelquelle (Morph auf die Position der Quelle, z. B. `LexofficeVoucherLine`
 * oder `InvoiceItem`) oder — Altbestand — ein Belegkopf, mit gedeckten
 * Lizenzmonaten. Eine Position kann mehrere Perioden decken (Mehrjahresblock),
 * eine Periode mehrere Positionen. Die Position selbst liefert der Spiegel
 * ({@see mirrorLine()}); Listen laden sie gebündelt ({@see InvoiceMirror::preload()}).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $period_id
 * @property int $subscription_id
 * @property string $linkable_type
 * @property int $linkable_id
 * @property string|null $voucher_number
 * @property CarbonImmutable|null $voucher_date
 * @property string $quantity
 * @property string $months
 * @property Money|null $amount
 * @property CurrencyCode $currency
 * @property LinkOrigin $origin
 * @property string|null $note
 * @property int|null $created_by_user_id
 * @property CarbonImmutable|null $confirmed_at
 * @property-read ResalePeriod $period
 * @property-read Model|null $linkable
 */
class ResalePeriodLink extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'resale_period_links';

    protected $fillable = [
        'organization_id',
        'period_id',
        'subscription_id',
        'linkable_type',
        'linkable_id',
        'voucher_number',
        'voucher_date',
        'quantity',
        'months',
        'amount',
        'currency',
        'origin',
        'note',
        'created_by_user_id',
        'confirmed_at',
    ];

    private ?MirrorLine $mirrorLine = null;

    private bool $mirrorLineResolved = false;

    protected $casts = [
        'voucher_date' => 'immutable_date',
        'quantity' => 'decimal:3',
        'months' => 'decimal:2',
        'currency' => CurrencyCode::class,
        'amount' => MoneyCast::class . ':currency,2',
        'origin' => LinkOrigin::class,
        'confirmed_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<ResalePeriod, $this> */
    public function period(): BelongsTo {
        return $this->belongsTo(ResalePeriod::class, 'period_id');
    }

    /** @return BelongsTo<ResaleSubscription, $this> */
    public function subscription(): BelongsTo {
        return $this->belongsTo(ResaleSubscription::class, 'subscription_id');
    }

    /** @return MorphTo<Model, $this> */
    public function linkable(): MorphTo {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** Bemerkung anhängen (Trenner „ · "), bestehende bleibt. */
    public function appendNote(string $text): void {
        $this->note = ResalePeriod::joinNote($this->note, $text);
    }

    /** Schlüssel der Position im Spiegel ({@see MirrorLine::identity()}). */
    public function mirrorIdentity(): string {
        return MirrorLine::identityOf((string) $this->linkable_type, (int) $this->linkable_id);
    }

    /** Gebündelt geladene Spiegelposition anhängen ({@see InvoiceMirror::preload()}). */
    public function attachMirrorLine(?MirrorLine $line): void {
        $this->mirrorLine = $line;
        $this->mirrorLineResolved = true;
    }

    /** Position aus dem Spiegel — vorgeladen, sonst einzeln über die Quelle des Morph-Typs; null ohne Quelle/Position. */
    public function mirrorLine(): ?MirrorLine {
        if (! $this->mirrorLineResolved) {
            $organization = $this->getRelationValue('organization');
            $this->mirrorLine = $organization instanceof Organization
                ? app(InvoiceMirror::class)->lineById($organization, (string) $this->linkable_type, (int) $this->linkable_id)
                : null;
            $this->mirrorLineResolved = true;
        }

        return $this->mirrorLine;
    }

    /** Anzeigetext der verknüpften Position; Belegkopf ohne Position (Altbestand) als solcher. */
    public function lineLabel(): string {
        $line = $this->mirrorLine();
        if ($line !== null) {
            return $line->label();
        }

        return app(InvoiceMirror::class)->sourceFor((string) $this->linkable_type) === null ? (string) __('resale.link.voucher_only') : '';
    }
}
