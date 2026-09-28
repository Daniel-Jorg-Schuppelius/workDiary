<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MedicalCheckupOccasion.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Safety;

use App\Enums\Safety\MedicalCheckupKind;
use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Carbon;

/**
 * Vorsorgeanlass der Organisation (MVP-986) mit Art und Wiederholungsintervall;
 * daraus ergibt sich die nächste Fälligkeit einer Vorsorge, wenn sie nicht
 * von Hand gesetzt ist. Deaktivieren statt löschen — Vorsorgen verweisen darauf.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $label
 * @property MedicalCheckupKind $kind
 * @property int|null $interval_months
 * @property bool $is_active
 * @property int|null $created_by
 */
class MedicalCheckupOccasion extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'label', 'kind', 'interval_months', 'is_active', 'created_by'];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => MedicalCheckupKind::class,
        'interval_months' => 'integer',
        'is_active' => 'boolean',
    ];

    public function nextDueFrom(CarbonInterface $performedOn): ?Carbon {
        return $this->interval_months === null || $this->interval_months < 1
            ? null
            : Carbon::parse($performedOn->toDateString())->addMonthsNoOverflow($this->interval_months);
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void {
        $query->where('is_active', true);
    }
}
