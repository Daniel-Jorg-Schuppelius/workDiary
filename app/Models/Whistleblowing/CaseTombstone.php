<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CaseTombstone.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Whistleblowing;

use Illuminate\Database\Eloquent\Model;

/**
 * Ueberlebender Minimalnachweis nach der Loeschung eines Falls (Abschnitt 16 /
 * 25). Traegt KEINE Meldeinhalte – nur Fallnummer, Zeitraum, Abschlusskategorie,
 * Loeschzeitpunkt und der letzte Audit-Hash. Wird beim Restore erneut angewandt,
 * um nach dem Backup geloeschte Faelle wieder zu sperren.
 */
class CaseTombstone extends Model {
    protected $table = 'whistleblowing_case_tombstones';

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'case_number',
        'public_id',
        'period_from',
        'period_to',
        'closed_category',
        'deleted_at',
        'audit_hash',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'deleted_at' => 'datetime',
    ];
}
