<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserQualification.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zuordnung einer Qualifikation/Unterweisung zu einem Mitarbeitenden
 * (Pivot user_qualifications) als eigenständiges Modell, damit der
 * Fristen-Scanner ablaufende Gültigkeiten je (User, Qualifikation) mit
 * stabilem Subjekt deduplizieren kann (Feature 013). Org-Kontext wird über
 * den betroffenen User aufgelöst (kein eigener organization_id-Schlüssel).
 *
 * @property int $id
 * @property int $user_id
 * @property int $qualification_id
 * @property \Illuminate\Support\Carbon|null $valid_from
 * @property \Illuminate\Support\Carbon|null $valid_until
 */
class UserQualification extends Model {
    protected $table = 'user_qualifications';

    protected $fillable = [
        'user_id',
        'qualification_id',
        'valid_from',
        'valid_until',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Qualification, $this> */
    public function qualification(): BelongsTo {
        return $this->belongsTo(Qualification::class, 'qualification_id');
    }
}
