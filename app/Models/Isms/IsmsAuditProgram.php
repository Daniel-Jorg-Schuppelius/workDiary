<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsAuditProgram.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Isms;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Mehrjähriges Auditprogramm (Nachtrag 044d): Zyklus + Normbezug je
 * Geltungsbereich; die geplanten Audits eines Jahres ergeben sich aus den
 * verknüpften {@see IsmsAudit}-Einträgen (planned_on), der Nachweis aus
 * deren Feststellungen/Maßnahmen und Auditpaketen.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $isms_scope_id
 * @property string $name
 * @property string|null $norm
 * @property string|null $edition
 * @property int $cycle_years
 * @property \Illuminate\Support\Carbon $starts_on
 * @property string $status
 * @property string|null $notes
 */
class IsmsAuditProgram extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;
    use SoftDeletes;

    protected $table = 'isms_audit_programs';

    protected $fillable = [
        'organization_id',
        'isms_scope_id',
        'name',
        'norm',
        'edition',
        'cycle_years',
        'starts_on',
        'status',
        'notes',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'starts_on' => 'date',
        'cycle_years' => 'integer',
    ];

    /** @return BelongsTo<IsmsScope, $this> */
    public function scope(): BelongsTo {
        return $this->belongsTo(IsmsScope::class, 'isms_scope_id');
    }

    /** @return HasMany<IsmsAudit, $this> */
    public function audits(): HasMany {
        return $this->hasMany(IsmsAudit::class, 'isms_audit_program_id')->orderBy('planned_on');
    }

    /**
     * Geplante/durchgeführte Audits gruppiert nach Programm-Jahr (1-basiert).
     *
     * @return array<int, list<IsmsAudit>>
     */
    public function auditsByCycleYear(): array {
        $byYear = [];
        foreach ($this->audits as $audit) {
            $planned = $audit->getAttribute('planned_on');
            $year = 1;
            if ($planned !== null) {
                $year = max(1, min($this->cycle_years, (int) $this->starts_on->diffInYears($planned, false) + 1));
            }
            $byYear[$year][] = $audit;
        }
        ksort($byYear);

        return $byYear;
    }
}
