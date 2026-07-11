<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityReportSnapshot.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Sustainability;

use App\Models\Concerns\{BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;

/**
 * Berichts-Snapshot (Feature 071, MVP-233): eingefrorene Kennzahlen +
 * Methodik + Faktor-Setversionen für Managementbewertung/Nachweise.
 *
 * @property int $id
 * @property int $organization_id
 * @property \Illuminate\Support\Carbon $period_start
 * @property \Illuminate\Support\Carbon $period_end
 * @property array<string, mixed> $data
 */
class SustainabilityReportSnapshot extends Model {
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = ['organization_id', 'period_start', 'period_end', 'data', 'created_by'];

    /** @var array<string, string> */
    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'data' => 'array',
    ];
}
