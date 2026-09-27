<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Recall.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Enums\Inventory\{RecallKind, RecallMeasure, RecallRiskLevel, RecallStatus};
use App\Models\Article\ArticleVariant;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use App\Models\Document\DocumentDispatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Rückrufaktion (MVP-921) für eine Artikelvariante, eingegrenzt über
 * Fertigungsaufträge, Lieferzeitraum und Seriennummern (alle Angaben
 * zusammen, leere Angaben grenzen nicht ein).
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $number
 * @property int $article_variant_id
 * @property RecallKind $kind
 * @property RecallStatus $status
 * @property string $title
 * @property string $reason
 * @property string|null $customer_message
 * @property list<int>|null $manufacturing_order_ids
 * @property \Illuminate\Support\Carbon|null $delivered_from
 * @property \Illuminate\Support\Carbon|null $delivered_until
 * @property list<string>|null $serial_numbers
 * @property bool $is_blocking_stock
 * @property \Illuminate\Support\Carbon|null $activated_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property string|null $hazard_kind
 * @property string|null $hazard_description
 * @property RecallRiskLevel|null $risk_level
 * @property RecallMeasure|null $measure
 * @property list<string>|null $countries
 * @property string|null $authority_name
 * @property string|null $authority_reference
 * @property \Illuminate\Support\Carbon|null $authority_reported_on
 * @property string|null $contact_name
 * @property string|null $contact_email
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class Recall extends Model {
    /** Belegart im Versandnachweis (`document_dispatches`) für Kundenanschreiben (MVP-922). */
    public const DOCUMENT_KIND = 'recall_notice';

    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'number', 'article_variant_id', 'kind', 'status', 'title', 'reason', 'customer_message',
        'manufacturing_order_ids', 'delivered_from', 'delivered_until', 'serial_numbers', 'is_blocking_stock',
        'activated_at', 'completed_at', 'created_by', 'updated_by',
        'hazard_kind', 'hazard_description', 'risk_level', 'measure', 'countries', 'authority_name', 'authority_reference', 'authority_reported_on', 'contact_name', 'contact_email',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => RecallKind::class,
        'status' => RecallStatus::class,
        'manufacturing_order_ids' => 'array',
        'serial_numbers' => 'array',
        'delivered_from' => 'date',
        'delivered_until' => 'date',
        'is_blocking_stock' => 'boolean',
        'activated_at' => 'datetime',
        'completed_at' => 'datetime',
        'risk_level' => RecallRiskLevel::class,
        'measure' => RecallMeasure::class,
        'countries' => 'array',
        'authority_reported_on' => 'date',
    ];

    /** @return BelongsTo<ArticleVariant, $this> */
    public function variant(): BelongsTo {
        return $this->belongsTo(ArticleVariant::class, 'article_variant_id');
    }

    /** @return HasMany<RecallItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(RecallItem::class);
    }

    /**
     * Versandnachweise der Kundenanschreiben (MVP-922), neueste zuerst.
     *
     * @return HasMany<DocumentDispatch, $this>
     */
    public function dispatches(): HasMany {
        return $this->hasMany(DocumentDispatch::class, 'document_id')->where('document_kind', self::DOCUMENT_KIND)->orderByDesc('id');
    }
}
