<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsAuditPackageToken.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Isms;

use App\Models\Concerns\HasSqid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Zeitlich begrenzter Prüfer-Download-Token eines finalisierten
 * Auditpakets (Feature 046, Inkrement E / 044 „optionaler zeitlich
 * begrenzter, lesender Prüferzugang").
 *
 * Der Klartext-Token wird NICHT gespeichert; persistiert wird nur der
 * SHA-256-Hash (Muster {@see \App\Models\ProtocolSignatureToken} /
 * Laravel Password-Reset). Der Klartext wird nach der Erstellung genau
 * EINMAL angezeigt.
 *
 * Bewusst OHNE BelongsToOrganization: Kind-Tabelle des tenant-gebundenen
 * Auditpakets — Mandantengrenze transitiv über
 * isms_audit_packages.organization_id; zusätzlich muss der öffentliche
 * Prüfer-Download (ohne Login, ohne Org-Session) den Token über den Hash
 * auflösen können. Allow-List-Eintrag im TenantTraitCoverageTest,
 * Begründung in ../WorkDiary-Architecture/security/tenant-audit-2026.md.
 *
 * @property int $id
 * @property int $isms_audit_package_id
 * @property string $token_hash
 * @property string $label
 * @property Carbon $expires_at
 * @property int|null $created_by_user_id
 * @property Carbon|null $last_accessed_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 */
class IsmsAuditPackageToken extends Model {
    use HasSqid;

    /** Append-only-Lebenszyklus: nur created_at (kein updated_at). */
    public const UPDATED_AT = null;

    protected $fillable = [
        'isms_audit_package_id',
        'token_hash',
        'label',
        'expires_at',
        'created_by_user_id',
        'last_accessed_at',
        'revoked_at',
        'created_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'revoked_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<IsmsAuditPackage, $this> */
    public function package(): BelongsTo {
        return $this->belongsTo(IsmsAuditPackage::class, 'isms_audit_package_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** Nicht widerrufen und nicht abgelaufen? */
    public function isUsable(): bool {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
