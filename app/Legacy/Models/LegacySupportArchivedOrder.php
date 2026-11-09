<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacySupportArchivedOrder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Archivierter Support-Auftrag (`kp_archiv`): gleiche Spalten wie `kp_auftrag`,
 * die Nummer wird beim Archivieren übernommen statt vergeben.
 *
 * @method static Builder<static>|LegacySupportArchivedOrder newModelQuery()
 * @method static Builder<static>|LegacySupportArchivedOrder newQuery()
 * @method static Builder<static>|LegacySupportArchivedOrder query()
 */
class LegacySupportArchivedOrder extends LegacySupportOrder {
    protected $table = 'kp_archiv';

    public $incrementing = false;
}
