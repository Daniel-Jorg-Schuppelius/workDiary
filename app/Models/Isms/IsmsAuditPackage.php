<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsAuditPackage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Isms;

use App\Enums\Isms\AuditPackageStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use App\Models\User;
use Database\Factories\Isms\IsmsAuditPackageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Stichtagsbezogenes Auditpaket (Feature 046, Inkrement E / 044
 * „Auditbereitschaft"): friert bei der Finalisierung den ISMS-Stand
 * (SoA, Risikoregister, Maßnahmen, Konformität, Audits, Reviews,
 * Softwareinventar) als JSON-Snapshot ein — Datei (file_path) +
 * SHA-256-Integritätsnachweis (file_hash).
 *
 * Stichtags-Semantik (MVP, ehrlich): as_of_date ist der dokumentierte
 * Berichtsstichtag; eingefroren wird der Datenstand zum Zeitpunkt der
 * Finalisierung (data_captured_at im JSON-meta) — KEINE rückwirkende
 * Zeitreise-Rekonstruktion.
 *
 * Finalisierte Pakete sind UNVERÄNDERLICH: Model-Guards werfen bei
 * update/delete eine ValidationException (Muster IsmsRiskAssessment).
 * Prüfer-Downloads laufen über zeitlich begrenzte Tokens
 * ({@see IsmsAuditPackageToken}, nur Hash persistiert).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $isms_scope_id
 * @property int $package_no
 * @property string $title
 * @property Carbon $as_of_date
 * @property string|null $norm
 * @property string|null $edition
 * @property AuditPackageStatus $status
 * @property string|null $file_path
 * @property string|null $file_hash
 * @property int|null $finalized_by_user_id
 * @property Carbon|null $finalized_at
 * @property int|null $created_by_user_id
 */
class IsmsAuditPackage extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<IsmsAuditPackageFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'isms_scope_id',
        'package_no',
        'title',
        'as_of_date',
        'norm',
        'edition',
        'status',
        'file_path',
        'file_hash',
        'finalized_by_user_id',
        'finalized_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'package_no' => 'integer',
        'as_of_date' => 'date',
        'status' => AuditPackageStatus::class,
        'finalized_at' => 'datetime',
    ];

    /**
     * Unveränderlichkeits-Guards: finalisierte Pakete können weder geändert
     * noch (soft-)gelöscht werden — der Integritätsnachweis (file_hash)
     * bliebe sonst wertlos (046-Akzeptanzkriterium „gegen nachträgliche
     * unbemerkte Änderung geschützt").
     */
    protected static function booted(): void {
        static::updating(function (self $package): void {
            if ($package->getOriginal('status') === AuditPackageStatus::Finalized) {
                throw ValidationException::withMessages([
                    'status' => __('isms.error.package_already_finalized'),
                ]);
            }
        });

        static::deleting(function (self $package): void {
            if ($package->isFinalized()) {
                throw ValidationException::withMessages([
                    'status' => __('isms.error.package_already_finalized'),
                ]);
            }
        });
    }

    /** Anzeige-Kennung in der Liste (z. B. "P-3"). */
    public function displayNo(): string {
        return 'P-' . $this->package_no;
    }

    /** Finalisiert und damit unveränderlich? */
    public function isFinalized(): bool {
        return $this->status === AuditPackageStatus::Finalized;
    }

    /** Anzeige des Norm-Filters, z. B. "ISO/IEC 27001:2022" (null = alle). */
    public function normLabel(): ?string {
        if ($this->norm === null || $this->norm === '') {
            return null;
        }

        return $this->norm . ($this->edition !== null && $this->edition !== '' && $this->edition !== '-' ? ':' . $this->edition : '');
    }

    /** @return BelongsTo<IsmsScope, $this> */
    public function scope(): BelongsTo {
        return $this->belongsTo(IsmsScope::class, 'isms_scope_id');
    }

    /** @return BelongsTo<User, $this> */
    public function finalizedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'finalized_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<IsmsAuditPackageToken, $this> */
    public function tokens(): HasMany {
        return $this->hasMany(IsmsAuditPackageToken::class, 'isms_audit_package_id');
    }
}
