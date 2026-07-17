<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetCalibrationCertificate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\AssetCompliance;

use App\Models\Concerns\{AppendOnly, Auditable, BelongsToOrganization, HasSqid};
use App\Models\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kalibrierzertifikat / Eich-/Prüfnachweis (MVP-287): Nummer, Aussteller,
 * Gültigkeit, Messbereich, Toleranz und Dokument (DMS, versioniert) mit
 * Inhalts-Hash — unveränderbarer Nachweis.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $asset_inspection_event_id
 * @property string $certificate_no
 * @property string $issuer
 * @property \Illuminate\Support\Carbon $issued_on
 * @property \Illuminate\Support\Carbon|null $valid_until
 */
class AssetCalibrationCertificate extends Model {
    // Nachweise sind unveränderbar — Korrektur nur über ein neues,
    // versioniertes Prüfereignis.
    use AppendOnly;

    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'asset_inspection_event_id', 'certificate_no',
        'issuer', 'issued_on', 'valid_until', 'measurement_range',
        'tolerance', 'document_id', 'sha256', 'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'issued_on' => 'date',
        'valid_until' => 'date',
    ];

    /** @return BelongsTo<AssetInspectionEvent, $this> */
    public function event(): BelongsTo {
        return $this->belongsTo(AssetInspectionEvent::class, 'asset_inspection_event_id');
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo {
        return $this->belongsTo(Document::class);
    }
}
