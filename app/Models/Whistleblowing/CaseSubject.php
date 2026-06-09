<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CaseSubject.php
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
 * Ein benannter Betroffener/Beschuldigter eines Falls (Abschnitt 7.4). Wird von
 * Bearbeitern markiert; die Person ist fuer den Fall gesperrt (keine Zuweisung,
 * kein Zugriff).
 *
 * @property string|null $note_ciphertext Klartext beim Lesen/Setzen
 */
class CaseSubject extends Model implements ProvidesCaseDek {
    use BelongsToOrganization;

    protected $table = 'whistleblowing_case_subjects';

    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id', 'case_id', 'user_id', 'added_by', 'note_ciphertext',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'note_ciphertext' => CaseEncrypted::class,
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
