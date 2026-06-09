<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CaseConflict.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Whistleblowing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\User;
use App\Models\Whistleblowing\Casts\CaseEncrypted;
use App\Models\Whistleblowing\Concerns\ProvidesCaseDek;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Interessenkonflikt-Selbstsperre (Abschnitt 7.4): solange ein Eintrag besteht,
 * ist die Person fuer diesen Fall gesperrt – auch wenn sie zugewiesen waere.
 *
 * @property string|null $reason_ciphertext Klartext beim Lesen/Setzen
 */
class CaseConflict extends Model implements ProvidesCaseDek {
    use BelongsToOrganization;

    protected $table = 'whistleblowing_case_conflicts';

    public $timestamps = false;

    protected $fillable = [
        'organization_id', 'case_id', 'user_id', 'reason_ciphertext', 'declared_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'reason_ciphertext' => CaseEncrypted::class,
        'declared_at' => 'datetime',
    ];

    public function caseDek(): ?string {
        return $this->case?->caseDek();
    }

    /** @return BelongsTo<WhistleblowingCase, $this> */
    public function case(): BelongsTo {
        return $this->belongsTo(WhistleblowingCase::class, 'case_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }
}
