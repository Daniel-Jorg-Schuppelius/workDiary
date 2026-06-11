<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Document.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Enums\Document\{DocumentStatus, DocumentType};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};
use Illuminate\Support\Carbon;

/**
 * Verwaltetes Dokument (MVP-031): typisierte Datei mit Metadaten,
 * Versionshistorie, Gültigkeit und optionalem polymorphem Bezug auf
 * Kunde, Projekt, Auftrag (DiaryEntry) oder Asset.
 *
 * @property int $id
 * @property int $organization_id
 * @property string|null $documentable_type
 * @property int|null $documentable_id
 * @property string $title
 * @property DocumentType $document_type
 * @property DocumentStatus $status
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 * @property string|null $description
 * @property int $created_by_user_id
 * @property int|null $current_version_id
 */
class Document extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'documentable_type',
        'documentable_id',
        'title',
        'document_type',
        'status',
        'valid_from',
        'valid_until',
        'description',
        'created_by_user_id',
        'current_version_id',
    ];

    protected $casts = [
        'document_type' => DocumentType::class,
        'status' => DocumentStatus::class,
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    /** @return MorphTo<Model, $this> */
    public function documentable(): MorphTo {
        return $this->morphTo();
    }

    /** @return HasMany<DocumentVersion, $this> */
    public function versions(): HasMany {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version_no');
    }

    /** @return BelongsTo<DocumentVersion, $this> */
    public function currentVersion(): BelongsTo {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Gültigkeit läuft innerhalb der nächsten $days Tage ab
     * (heute eingeschlossen, bereits abgelaufene ausgenommen).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeExpiringWithin(Builder $query, int $days): Builder {
        return $query
            ->where('status', '!=', DocumentStatus::Archived->value)
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '>=', Carbon::today())
            ->whereDate('valid_until', '<=', Carbon::today()->addDays($days));
    }

    /**
     * Gültigkeit überschritten (Anzeige als „abgelaufen", kein Cron nötig).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeExpired(Builder $query): Builder {
        return $query
            ->where('status', '!=', DocumentStatus::Archived->value)
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', Carbon::today());
    }

    /**
     * Aktive (nicht archivierte, nicht abgelaufene) Dokumente.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder {
        return $query
            ->where('status', DocumentStatus::Active->value)
            ->where(function (Builder $q): void {
                $q->whereNull('valid_until')
                    ->orWhereDate('valid_until', '>=', Carbon::today());
            });
    }

    public function isExpired(): bool {
        return $this->status !== DocumentStatus::Archived
            && $this->valid_until !== null
            && $this->valid_until->isPast()
            && ! $this->valid_until->isToday();
    }

    /**
     * Anzeigestatus: persistierter Status, außer die Gültigkeit ist
     * überschritten — dann „abgelaufen" (berechnet, nicht persistiert).
     */
    public function effectiveStatus(): DocumentStatus {
        if ($this->status === DocumentStatus::Archived) {
            return DocumentStatus::Archived;
        }
        if ($this->isExpired()) {
            return DocumentStatus::Expired;
        }

        return $this->status;
    }
}
