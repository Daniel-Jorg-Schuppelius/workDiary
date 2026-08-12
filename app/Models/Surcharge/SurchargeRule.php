<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurchargeRule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Surcharge;

use App\Enums\Surcharge\SurchargeKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Carbon\CarbonInterface;
use Database\Factories\SurchargeRuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};
use Illuminate\Support\Carbon;

/**
 * Zuschlagsregel pro Organisation (Feature 005, MVP).
 *
 * Liefert dem {@see \App\Services\Surcharge\SurchargeCalculator} die
 * Definition zuschlagsfähiger Zeiten (Nacht/Sa/So/Feiertag/Custom).
 * `wage_type_code` ist die Lohnart für die DATEV-/Lexware-Übergabe.
 *
 * Stacking: Bei Überlappung mehrerer Regeln gewinnt der höchste
 * Prozentsatz (kein Addieren); `priority` bricht Gleichstände.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $code
 * @property string $label
 * @property SurchargeKind $kind
 * @property string|null $window_start  TIME (H:i:s)
 * @property string|null $window_end    TIME (H:i:s)
 * @property string $percentage
 * @property string|null $wage_type_code
 * @property int $priority
 * @property bool $active
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 */
class SurchargeRule extends Model {
    use Auditable;

    use BelongsToOrganization;

    /** @use HasFactory<SurchargeRuleFactory> */
    use HasFactory;
    use HasSqid;

    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'code',
        'label',
        'kind',
        'window_start',
        'window_end',
        'percentage',
        'wage_type_code',
        'tax_free_limit_pct',
        'taxable_wage_type_code',
        'priority',
        'active',
        'valid_from',
        'valid_until',
        'conditions',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => SurchargeKind::class,
        'percentage' => 'decimal:2',
        'tax_free_limit_pct' => 'decimal:2',
        'priority' => 'integer',
        'active' => 'boolean',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'conditions' => 'array',
    ];

    /**
     * MVP-513 (Feature 103): kombinierbare Bedingungen — jede KONFIGURIERTE
     * (nicht-leere) Liste muss zutreffen (UND-Verknüpfung); innerhalb einer
     * Liste genügt ein Treffer (ODER). Fehlt der Kontextwert zu einer
     * konfigurierten Bedingung (z. B. kein Standort ermittelbar), gilt die
     * Regel NICHT — Bedingungen sind Einschränkungen, keine Vorzugslogik.
     *
     * @param  array{team_ids?: list<int>, site_id?: int|null, shift_type_id?: int|null}  $context
     */
    public function matchesContext(array $context): bool {
        $conditions = $this->conditions ?? [];

        $teamIds = array_map('intval', (array) ($conditions['team_ids'] ?? []));
        if ($teamIds !== [] && array_intersect($teamIds, (array) ($context['team_ids'] ?? [])) === []) {
            return false;
        }

        $siteIds = array_map('intval', (array) ($conditions['site_ids'] ?? []));
        if ($siteIds !== [] && ! in_array((int) ($context['site_id'] ?? 0), $siteIds, true)) {
            return false;
        }

        $shiftTypeIds = array_map('intval', (array) ($conditions['shift_type_ids'] ?? []));
        if ($shiftTypeIds !== [] && ! in_array((int) ($context['shift_type_id'] ?? 0), $shiftTypeIds, true)) {
            return false;
        }

        return true;
    }

    protected static function newFactory(): SurchargeRuleFactory {
        return SurchargeRuleFactory::new();
    }

    /**
     * Nur aktive Regeln.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder {
        return $query->where('active', true);
    }

    /** Gilt die Regel (Gültigkeitszeitraum, inklusiv) am gegebenen Tag? */
    public function appliesOn(CarbonInterface $date): bool {
        $day = $date->toDateString();
        if ($this->valid_from !== null && $day < $this->valid_from->toDateString()) {
            return false;
        }
        if ($this->valid_until !== null && $day > $this->valid_until->toDateString()) {
            return false;
        }

        return true;
    }

    /** Lohnart-Schlüssel für TimeExportLine.wage_type (stabil, org-eindeutig). */
    /**
     * Steuerfrei/-pflichtig-Split (Rang 36, § 3b EStG als Konfiguration):
     * liegt der Zuschlag über der steuerfreien Obergrenze, wird er in zwei
     * Prozent-Anteile geteilt (wage-unabhängig — der €-Grundlohn-Deckel bleibt
     * Sache der externen Lohnrechnung). null = kein Split nötig.
     *
     * @return array{free_pct: float, taxable_pct: float}|null
     */
    public function taxSplit(): ?array {
        if ($this->tax_free_limit_pct === null) {
            return null;
        }

        $limit = (float) $this->tax_free_limit_pct;
        $pct = (float) $this->percentage;
        if ($limit >= $pct) {
            return null;
        }

        return [
            'free_pct' => round($limit, 2),
            'taxable_pct' => round($pct - $limit, 2),
        ];
    }

    public function wageType(): string {
        return 'surcharge.' . $this->code;
    }
}
