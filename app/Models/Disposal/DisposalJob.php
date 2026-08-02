<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DisposalJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Disposal;

use App\Enums\Disposal\DisposalJobStatus;
use App\Models\{Attachment, Customer, DiaryEntry, Document, Site, User};
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasAttachments, HasSqid};
use Database\Factories\Disposal\DisposalJobFactory;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Entsorgungsakte (Feature 100, MVP-474/475): führt Abholung, Geräteliste,
 * Datenträger-Behandlung, Entsorger-Übergabe und den generierten
 * Kundennachweis als prüffeste Nachweiskette. Der Kundennachweis wird als
 * DMS-Dokument (record_document_id) versioniert und über die bestehende
 * Kundenfreigabe im Portal ausgegeben.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $number
 * @property DisposalJobStatus $status
 * @property int $customer_id
 * @property int|null $site_id
 * @property int|null $diary_entry_id
 * @property int|null $responsible_user_id
 * @property \Illuminate\Support\Carbon|null $picked_up_on
 * @property numeric-string|null $total_weight_kg
 * @property string|null $notes
 * @property int|null $record_document_id
 * @property string|null $signer_name
 * @property \Illuminate\Support\Carbon|null $signed_at
 * @property int|null $signature_attachment_id
 * @property string|null $signature_hash
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property int|null $completed_by
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property int $created_by_user_id
 */
class DisposalJob extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasAttachments;

    /** @use HasFactory<DisposalJobFactory> */
    use HasFactory;

    use HasSqid;

    protected $fillable = [
        'organization_id', 'number', 'status', 'customer_id', 'site_id',
        'diary_entry_id', 'responsible_user_id', 'picked_up_on',
        'total_weight_kg', 'notes', 'record_document_id', 'signer_name',
        'signed_at', 'signature_attachment_id', 'signature_hash',
        'completed_at', 'completed_by', 'cancelled_at', 'cancel_reason',
        'created_by_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => DisposalJobStatus::class,
        'picked_up_on' => 'date',
        'total_weight_kg' => 'decimal:3',
        'signed_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /** @param Builder<self> $query */
    public function scopeOpen(Builder $query): void {
        $query->whereIn('status', [
            DisposalJobStatus::Draft->value,
            DisposalJobStatus::Collected->value,
            DisposalJobStatus::InTreatment->value,
            DisposalJobStatus::HandedOver->value,
        ]);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<DiaryEntry, $this> */
    public function diaryEntry(): BelongsTo {
        return $this->belongsTo(DiaryEntry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<Document, $this> */
    public function recordDocument(): BelongsTo {
        return $this->belongsTo(Document::class, 'record_document_id');
    }

    /** @return BelongsTo<Attachment, $this> */
    public function signatureAttachment(): BelongsTo {
        return $this->belongsTo(Attachment::class, 'signature_attachment_id');
    }

    /** @return HasMany<DisposalItem, $this> */
    public function items(): HasMany {
        return $this->hasMany(DisposalItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<DisposalHandover, $this> */
    public function handovers(): HasMany {
        return $this->hasMany(DisposalHandover::class)->orderBy('handed_over_on')->orderBy('id');
    }

    /** @return HasMany<DisposalJobEvent, $this> */
    public function events(): HasMany {
        return $this->hasMany(DisposalJobEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function isSigned(): bool {
        return $this->signed_at !== null;
    }
}
