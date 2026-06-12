<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsNormStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Isms;

use App\Enums\Isms\NormConformityStatus;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Database\Factories\Isms\IsmsNormStatusFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Konformitätsstatus je Normprofil und Geltungsbereich (Feature 046,
 * Inkrement B): genau eine Zeile pro Org + Scope + Norm/Ausgabe
 * (Unique-Index). Statusübergänge laufen AUSSCHLIESSLICH über
 * {@see \App\Services\Isms\ConformityService::transition()} entlang
 * {@see NormConformityStatus::allowedTransitions()} — `certified` nur
 * mit heute gültigem Zertifikat ({@see self::activeCertificate()}).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $isms_scope_id
 * @property string $norm
 * @property string $edition
 * @property NormConformityStatus $status
 * @property string|null $notes
 */
class IsmsNormStatus extends Model {
    use Auditable;

    use BelongsToOrganization;
    /** @use HasFactory<IsmsNormStatusFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'isms_scope_id',
        'norm',
        'edition',
        'status',
        'notes',
    ];

    protected $casts = [
        'status' => NormConformityStatus::class,
    ];

    /** Anzeige der Normreferenz, z. B. "ISO/IEC 27001:2022" oder "Eigene". */
    public function normLabel(): string {
        return $this->edition === '-' ? $this->norm : $this->norm . ':' . $this->edition;
    }

    /** @return BelongsTo<IsmsScope, $this> */
    public function scope(): BelongsTo {
        return $this->belongsTo(IsmsScope::class, 'isms_scope_id');
    }

    /** @return HasMany<IsmsCertificate, $this> */
    public function certificates(): HasMany {
        return $this->hasMany(IsmsCertificate::class, 'isms_norm_status_id');
    }

    /**
     * Heute gültiges Zertifikat (valid_from ≤ heute ≤ valid_until) —
     * Grundlage der strikten 046-Regel für den Status `certified`.
     */
    public function activeCertificate(): ?IsmsCertificate {
        $today = Carbon::today();

        return $this->certificates()
            ->whereDate('valid_from', '<=', $today)
            ->whereDate('valid_until', '>=', $today)
            ->orderByDesc('valid_until')
            ->first();
    }
}
