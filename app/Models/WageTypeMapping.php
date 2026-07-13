<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WageTypeMapping.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;

/**
 * Lohnarten-Mapping für den Zeitexport (A21 · MVP-019, ../WorkDiary-Architecture/zeit-export.md §5.1):
 * ordnet je Organisation und Export-Profil eine interne Lohnart
 * (TimeExportLine.wage_type — work.normal, surcharge.<code>, absence.*, …)
 * einer externen Lohnartennummer des Ziel-Lohnprogramms zu. Aufgelöst vom
 * {@see \App\Services\TimeExport\WageTypeResolver}; ohne Mapping bleiben die
 * bisherigen Defaults wirksam (Rückwärtskompatibilität).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $profile
 * @property string $wage_type
 * @property string $external_code
 */
class WageTypeMapping extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    /** Interne Standard-Lohnarten laut ../WorkDiary-Architecture/zeit-export.md §5.1. */
    public const STANDARD_WAGE_TYPES = [
        'work.normal',
        'work.night',
        'work.sunday',
        'work.holiday',
        'work.oncall',
        'absence.vacation',
        'absence.sick',
        'travel.time',
    ];

    protected $fillable = [
        'organization_id',
        'profile',
        'wage_type',
        'external_code',
    ];
}
