<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcessingAgreement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Privacy;

use App\Enums\Privacy\AgreementStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Isms\IsmsSupplierAssessment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};

/**
 * Auftragsverarbeitungsvertrag (AVV/DPA, Art. 28) mit Gueltigkeit, Status,
 * Dokumentanhang, Unterauftragsverarbeitern und Verknuepfung zu
 * Verarbeitungstaetigkeiten. Enthaelt den Vertragsende-Workflow (Datenrueckgabe/
 * Loeschnachweis).
 *
 * @property int $id
 * @property int $organization_id
 */
class ProcessingAgreement extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $table = 'privacy_processing_agreements';

    protected $fillable = [
        'organization_id',
        'processor_id',
        'title',
        'version',
        'status',
        'valid_from',
        'valid_until',
        'review_due_at',
        'data_categories',
        'tom_checked',
        'document_path',
        'document_name',
        'terminated_at',
        'data_return',
        'data_return_confirmed_at',
        'notes',
        'created_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => AgreementStatus::class,
        'valid_from' => 'date',
        'valid_until' => 'date',
        'review_due_at' => 'date',
        'tom_checked' => 'boolean',
        'terminated_at' => 'datetime',
        'data_return_confirmed_at' => 'datetime',
    ];

    /** @return BelongsTo<Processor, $this> */
    public function processor(): BelongsTo {
        return $this->belongsTo(Processor::class, 'processor_id');
    }

    /** @return HasMany<Subprocessor, $this> */
    public function subprocessors(): HasMany {
        return $this->hasMany(Subprocessor::class, 'agreement_id')->orderBy('name');
    }

    /**
     * ISMS-Lieferantenbewertungen, die dieses AVV referenzieren (Feature 044,
     * Welle D — AVV-Kopplung). Wiederverwendung des AVV als Lieferantennachweis.
     *
     * @return HasMany<IsmsSupplierAssessment, $this>
     */
    public function supplierAssessments(): HasMany {
        return $this->hasMany(IsmsSupplierAssessment::class, 'processing_agreement_id');
    }

    /** @return BelongsToMany<ProcessingActivity, $this> */
    public function activities(): BelongsToMany {
        return $this->belongsToMany(
            ProcessingActivity::class,
            'privacy_agreement_activity',
            'agreement_id',
            'activity_id',
        );
    }

    public function isReviewOverdue(): bool {
        $due = $this->getAttribute('review_due_at');

        return $due !== null && $due->isPast();
    }
}
