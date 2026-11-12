<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MonitoringToken.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Diagnostics;

use App\Models\Concerns\HasSqid;
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Plattformweiter Zugang einer externen Überwachung zum Diagnose-JSON
 * (MVP-1618). Nur Abdruck und Hinweis liegen in der Tabelle, siehe
 * {@see \App\Services\Diagnostics\MonitoringTokenService}.
 *
 * @property int $id
 * @property string $name
 * @property string $token_hash
 * @property string $token_hint
 * @property int|null $created_by
 * @property Carbon|null $last_used_at
 * @property Carbon|null $last_audited_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 */
class MonitoringToken extends Model {
    use HasSqid;

    protected $fillable = ['name', 'token_hash', 'token_hint', 'created_by', 'last_used_at', 'last_audited_at', 'revoked_at'];

    protected $hidden = ['token_hash'];

    /** @var array<string, string> */
    protected $casts = [
        'last_used_at' => 'datetime',
        'last_audited_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool {
        return $this->revoked_at === null;
    }
}
