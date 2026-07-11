<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetBlockException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\{Auditable, BelongsToOrganization, HasSqid};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Befristete, auditierte Ausnahmefreigabe einer Asset-Sperre (D12).
 * Ausnahmen gelten zweckgebunden je Einsatzkontext (rental/dispatch/…).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $asset_block_id
 * @property string $context
 * @property string $reason_text
 * @property \Illuminate\Support\Carbon $valid_until
 * @property \Illuminate\Support\Carbon|null $revoked_at
 */
class AssetBlockException extends Model {
    use Auditable;
    use BelongsToOrganization;
    use HasSqid;

    protected $fillable = [
        'organization_id', 'asset_block_id', 'context', 'reason_text',
        'valid_until', 'granted_by', 'revoked_at', 'revoked_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'valid_until' => 'date',
        'revoked_at' => 'datetime',
    ];

    public function coversContext(string $context): bool {
        return $this->context === $context
            && $this->revoked_at === null
            && $this->valid_until->endOfDay()->isFuture();
    }

    /** @return BelongsTo<AssetBlock, $this> */
    public function block(): BelongsTo {
        return $this->belongsTo(AssetBlock::class, 'asset_block_id');
    }

    /** @return BelongsTo<User, $this> */
    public function grantor(): BelongsTo {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
