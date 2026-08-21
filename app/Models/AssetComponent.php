<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetComponent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Verbautes Teil eines Assets (Feature 118, MVP-607).
 *
 * @property Carbon|null $installed_on
 * @property Carbon|null $removed_on
 */
class AssetComponent extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    public const STATUS_INSTALLED = 'installed';

    public const STATUS_REMOVED = 'removed';

    public const STATUS_REPLACED = 'replaced';

    protected $fillable = [
        'organization_id',
        'asset_id',
        'article_id',
        'label',
        'quantity',
        'unit',
        'position',
        'serial_no',
        'stock_serial_id',
        'installed_on',
        'removed_on',
        'replace_interval_months',
        'status',
        'replaced_by_id',
        'note',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'installed_on' => 'date',
        'removed_on' => 'date',
        'replace_interval_months' => 'integer',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'installed', 'quantity' => '1.000'];

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo {
        return $this->belongsTo(Article::class);
    }

    /** @return BelongsTo<AssetComponent, $this> */
    public function replacedBy(): BelongsTo {
        return $this->belongsTo(AssetComponent::class, 'replaced_by_id');
    }

    /** Anzeigename: Artikelbezeichnung vor Freitext. */
    public function displayName(): string {
        return (string) ($this->article->name ?? $this->label ?? '—');
    }

    public function isInstalled(): bool {
        return $this->status === self::STATUS_INSTALLED;
    }

    /**
     * Fällig zum Wechsel? Ohne Intervall oder Einbaudatum gibt es keine
     * Fälligkeit — geraten wird nicht.
     */
    public function dueOn(): ?Carbon {
        if ($this->replace_interval_months === null || $this->installed_on === null) {
            return null;
        }

        return $this->installed_on->copy()->addMonths((int) $this->replace_interval_months);
    }

    public function isDue(?\Carbon\CarbonInterface $reference = null): bool {
        $due = $this->dueOn();

        return $this->isInstalled() && $due !== null && $due->lessThanOrEqualTo($reference ?? Carbon::today());
    }

    /**
     * Seriennummer aus der eigenen Bestandsführung (Feature 118, optional).
     * Fremdteile haben keine — für sie bleibt `serial_no` als Freitext.
     *
     * @return BelongsTo<StockSerial, $this>
     */
    public function stockSerial(): BelongsTo {
        return $this->belongsTo(StockSerial::class, 'stock_serial_id');
    }
}
