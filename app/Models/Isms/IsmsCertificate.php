<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsCertificate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Isms;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\Document;
use Database\Factories\Isms\IsmsCertificateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Hinterlegtes Zertifikat (Feature 046, Inkrement B) zu einem
 * Konformitätsstatus ({@see IsmsNormStatus}): dokumentiert die
 * 046-Pflichtfelder (zertifizierte Organisation, Geltungsbereich laut
 * Zertifikat, Zertifizierungsstelle, Zertifikatsnummer, Ausstellungsdatum,
 * Gültigkeitszeitraum) plus optionale Überwachungstermine; die
 * Zertifikatsdatei (PDF) liegt im Dokumentenmodul (document_id).
 * Pflege ausschließlich über
 * {@see \App\Services\Isms\ConformityService::addCertificate()}.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $isms_norm_status_id
 * @property string $certified_organization
 * @property string $scope_description
 * @property string $certification_body
 * @property string $certificate_no
 * @property Carbon $issued_on
 * @property Carbon $valid_from
 * @property Carbon $valid_until
 * @property Carbon|null $surveillance_audit_1_on
 * @property Carbon|null $surveillance_audit_2_on
 * @property int|null $document_id
 * @property string|null $notes
 */
class IsmsCertificate extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<IsmsCertificateFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'isms_norm_status_id',
        'certified_organization',
        'scope_description',
        'certification_body',
        'certificate_no',
        'issued_on',
        'valid_from',
        'valid_until',
        'surveillance_audit_1_on',
        'surveillance_audit_2_on',
        'document_id',
        'notes',
    ];

    protected $casts = [
        'issued_on' => 'date',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'surveillance_audit_1_on' => 'date',
        'surveillance_audit_2_on' => 'date',
    ];

    /** @return BelongsTo<IsmsNormStatus, $this> */
    public function normStatus(): BelongsTo {
        return $this->belongsTo(IsmsNormStatus::class, 'isms_norm_status_id');
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo {
        return $this->belongsTo(Document::class, 'document_id');
    }

    /** Gilt das Zertifikat am Stichtag? (valid_from ≤ Tag ≤ valid_until) */
    public function isValidOn(Carbon $date): bool {
        $day = $date->copy()->startOfDay();

        return $this->valid_from->startOfDay()->lte($day)
            && $this->valid_until->startOfDay()->gte($day);
    }

    /** Nächster anstehender Überwachungstermin (heute oder später). */
    public function nextSurveillanceOn(): ?Carbon {
        $today = Carbon::today();

        return collect([$this->surveillance_audit_1_on, $this->surveillance_audit_2_on])
            ->filter(fn(?Carbon $on): bool => $on !== null && $on->startOfDay()->gte($today))
            ->sort()
            ->first();
    }

    /** Überwachungstermin innerhalb der nächsten X Tage (Warn-Badge). */
    public function surveillanceSoon(int $days = 60): bool {
        $next = $this->nextSurveillanceOn();

        return $next !== null && $next->startOfDay()->lte(Carbon::today()->addDays($days));
    }
}
