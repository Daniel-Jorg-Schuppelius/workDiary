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
use App\Enums\Hr\HrDocumentCategory;
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
 * @property bool $customer_visible
 * @property Carbon|null $customer_released_at
 * @property int|null $customer_released_by
 * @property bool $confidential
 * @property HrDocumentCategory|null $hr_category
 * @property Carbon|null $retention_until
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
        'webdav_mirror_detached',
        'sharepoint_mirror_detached',
        'customer_visible',
        'customer_released_at',
        'customer_released_by',
        'confidential',
        'hr_category',
        'retention_until',
    ];

    protected $casts = [
        'document_type' => DocumentType::class,
        'status' => DocumentStatus::class,
        'valid_from' => 'date',
        'valid_until' => 'date',
        'webdav_mirror_detached' => 'boolean',
        'sharepoint_mirror_detached' => 'boolean',
        'customer_visible' => 'boolean',
        'customer_released_at' => 'datetime',
        'confidential' => 'boolean',
        'hr_category' => HrDocumentCategory::class,
        'retention_until' => 'date',
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

    /** @return BelongsTo<User, $this> */
    public function customerReleaser(): BelongsTo {
        return $this->belongsTo(User::class, 'customer_released_by');
    }

    /**
     * Kundenzuordnung des Dokuments: für kunden-/auftragsgebundene Dokumente
     * die zugehörige Kunden-ID, sonst null. Grundlage der Freigabe-Validierung
     * („nur kunden-/auftragsgebundene Dokumente sind freigebbar") und des
     * Portal-Scopes. Liest die geladene `documentable`-Relation.
     */
    public function customerId(): ?int {
        return match ($this->documentable_type) {
            Customer::class => $this->documentable_id !== null ? (int) $this->documentable_id : null,
            Project::class, DiaryEntry::class, Asset::class,
            \App\Models\Disposal\DisposalJob::class => ($cid = $this->documentable?->getAttribute('customer_id')) !== null ? (int) $cid : null,
            default => null,
        };
    }

    /**
     * Freigebbar ist ein Dokument nur, wenn es einem Kunden oder einem Auftrag
     * (direkt oder über Projekt/Asset) zugeordnet ist — ein freies oder rein
     * internes Dokument kann keinem Portal-Kunden zugeordnet werden.
     */
    public function isReleasableToCustomer(): bool {
        return $this->customerId() !== null;
    }

    /**
     * Harte Portal-Sichtbarkeitsgrenze: NUR freigegebene Dokumente der
     * eigenen Organisation, die dem Kunden direkt (Kundenkonto) oder über
     * einen seiner Aufträge/Projekte/Objekte zugeordnet sind. Interne oder
     * fremde Dokumente können hier prinzipiell nicht auftauchen.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleToCustomer(Builder $query, int $organizationId, int $customerId): Builder {
        return $query
            ->where('organization_id', $organizationId)
            ->where('customer_visible', true)
            ->where(function (Builder $outer) use ($organizationId, $customerId): void {
                $outer
                    ->where(function (Builder $q) use ($customerId): void {
                        $q->where('documentable_type', Customer::class)
                            ->where('documentable_id', $customerId);
                    })
                    ->orWhere(function (Builder $q) use ($organizationId, $customerId): void {
                        $q->where('documentable_type', Project::class)
                            ->whereIn('documentable_id', Project::query()
                                ->where('organization_id', $organizationId)
                                ->where('customer_id', $customerId)
                                ->select('id'));
                    })
                    ->orWhere(function (Builder $q) use ($organizationId, $customerId): void {
                        $q->where('documentable_type', DiaryEntry::class)
                            ->whereIn('documentable_id', DiaryEntry::query()
                                ->where('organization_id', $organizationId)
                                ->where('customer_id', $customerId)
                                ->select('id'));
                    })
                    ->orWhere(function (Builder $q) use ($organizationId, $customerId): void {
                        $q->where('documentable_type', Asset::class)
                            ->whereIn('documentable_id', Asset::query()
                                ->where('organization_id', $organizationId)
                                ->where('customer_id', $customerId)
                                ->select('id'));
                    })
                    // Entsorgungsakte (Feature 100): Kundennachweis + Belege.
                    ->orWhere(function (Builder $q) use ($organizationId, $customerId): void {
                        $q->where('documentable_type', \App\Models\Disposal\DisposalJob::class)
                            ->whereIn('documentable_id', \App\Models\Disposal\DisposalJob::query()
                                ->where('organization_id', $organizationId)
                                ->where('customer_id', $customerId)
                                ->select('id'));
                    });
            });
    }

    /**
     * Personalakte (Feature 141): Dokument am Mitarbeiter — eigener
     * hrFile-Zugriffskreis, immer vertraulich, nie kundenfreigebbar.
     */
    public function isPersonnelFile(): bool {
        return $this->documentable_type === User::class;
    }

    /**
     * Akte eines Mitglieds (Feature 141).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePersonnelFilesOf(Builder $query, User $member): Builder {
        return $query
            ->where('documentable_type', User::class)
            ->where('documentable_id', $member->id);
    }

    /**
     * Sichtbarkeit in Listen: Personalakten (Feature 141) sehen NUR Inhaber
     * von hrFile.viewAny — auch Admins nicht (die eigene Akte liest die
     * betroffene Person unter „Mein Konto", nicht in der Übersicht).
     * Allgemeine Dokumente: vertrauliche Dokumente anderer Erfasser werden
     * ohne `document.confidential.manage` ausgeblendet — Muster
     * Kommunikationsnotizen (Vollaudit 2026-07, N10).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder {
        $seesConfidential = $user->isAdmin() || $user->can(\App\Enums\User\Permission::DocumentConfidentialManage->value);
        $seesPersonnelFiles = $user->hasEffectivePermission(\App\Services\Hr\PersonnelFilePermissions::VIEW_ANY);

        return $query->where(function (Builder $outer) use ($user, $seesConfidential, $seesPersonnelFiles): void {
            $outer->where(function (Builder $general) use ($user, $seesConfidential): void {
                $general->where(function (Builder $q): void {
                    $q->whereNull('documentable_type')
                        ->orWhere('documentable_type', '!=', User::class);
                });
                if (! $seesConfidential) {
                    $general->where(function (Builder $q) use ($user): void {
                        $q->where('confidential', false)
                            ->orWhere('created_by_user_id', $user->id);
                    });
                }
            });
            if ($seesPersonnelFiles) {
                $outer->orWhere('documentable_type', User::class);
            }
        });
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
