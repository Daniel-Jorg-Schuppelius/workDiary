<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsAdvisory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Isms;

use App\Enums\Isms\AdvisoryFormat;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Database\Factories\Isms\IsmsAdvisoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Importiertes Advisory (Feature 044, MVP 2): Nachweis-Ablage des
 * Original-Advisories (CSAF/VEX-JSON) je Import — Datei im local-Disk
 * (file_path) plus SHA-256 (file_hash). Aus dem Import entstehen
 * {@see IsmsVulnerability}-Einträge (vuln_count). Re-Import-Idempotenz über
 * (organization_id, file_hash): identische Dateien werden nicht doppelt
 * abgelegt (AdvisoryImportService). KEIN SoftDelete — Nachweis-Charakter.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $title
 * @property AdvisoryFormat $format
 * @property string|null $document_id_ref
 * @property string $file_path
 * @property string $file_hash
 * @property int|null $imported_by_user_id
 * @property int $vuln_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class IsmsAdvisory extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<IsmsAdvisoryFactory> */
    use HasFactory;
    use HasSqid;

    protected $table = 'isms_advisories';

    protected $fillable = [
        'organization_id',
        'title',
        'format',
        'document_id_ref',
        'file_path',
        'file_hash',
        'imported_by_user_id',
        'vuln_count',
    ];

    protected $casts = [
        'format' => AdvisoryFormat::class,
        'vuln_count' => 'integer',
    ];

    /** @return BelongsTo<User, $this> */
    public function importedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'imported_by_user_id');
    }

    /** @return HasMany<IsmsVulnerability, $this> */
    public function vulnerabilities(): HasMany {
        return $this->hasMany(IsmsVulnerability::class, 'isms_advisory_id');
    }
}
