<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FeatureUsageCounter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

/**
 * Aggregierter Feature-Nutzungszähler (Telemetry-Light, Feature 036).
 *
 * Ein Datensatz pro Organisation + Feature + Tag; `count` wird per Upsert
 * hochgezählt (siehe {@see \App\Services\Metrics\OperationsMetricsService::increment()}).
 * Der Schreibpfad ist über das Setting `telemetry.enabled` abschaltbar
 * (Opt-out je Org/Installation, MVP-337) — Daten verlassen die
 * Installation nie.
 *
 * Bewusste Abweichungen von der Model-Blaupause:
 * - KEIN Auditable: Zähler-Inkremente sind technische Telemetrie; jedes
 *   Inkrement in der Audit-Hash-Kette würde die Kette mit Rauschen fluten.
 * - KEIN HasSqid/SoftDeletes: Aggregat ohne eigene Routen/URLs und ohne
 *   fachlichen Lösch-Workflow (Aufbewahrung regelt der Datenlebenszyklus).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $feature
 * @property \Illuminate\Support\Carbon $period_date
 * @property int $count
 */
class FeatureUsageCounter extends Model {
    use BelongsToOrganization;

    protected $table = 'feature_usage_counters';

    protected $fillable = [
        'organization_id',
        'feature',
        'period_date',
        'count',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'period_date' => 'date',
        'count' => 'integer',
    ];
}
