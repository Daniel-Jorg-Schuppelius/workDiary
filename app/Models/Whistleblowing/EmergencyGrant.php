<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EmergencyGrant.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Whistleblowing;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Whistleblowing\Casts\CaseEncrypted;
use App\Models\Whistleblowing\Concerns\ProvidesCaseDek;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Notfallfreigabe (Abschnitt 7.4 / 25): zeitlich begrenzter Zugriff einer NICHT
 * zugewiesenen Person, erteilt durch einen zwingend ANDEREN Zweit-Genehmiger
 * mit Permission. Laeuft automatisch ab (expires_at).
 *
 * @property string|null $reason_ciphertext Klartext beim Lesen/Setzen
 */
class EmergencyGrant extends Model implements ProvidesCaseDek {
    use BelongsToOrganization;

    protected $table = 'whistleblowing_emergency_grants';

    public $timestamps = false;

    protected $fillable = [
        'organization_id', 'case_id', 'user_id', 'granted_by',
        'reason_ciphertext', 'granted_at', 'expires_at', 'revoked_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'reason_ciphertext' => CaseEncrypted::class,
        'granted_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /** @param Builder<EmergencyGrant> $query */
    public function scopeActive(Builder $query): void {
        $query->whereNull('revoked_at')->where('expires_at', '>', Carbon::now());
    }

    public function caseDek(): ?string {
        return $this->case?->caseDek();
    }

    /** @return BelongsTo<WhistleblowingCase, $this> */
    public function case(): BelongsTo {
        return $this->belongsTo(WhistleblowingCase::class, 'case_id');
    }
}
