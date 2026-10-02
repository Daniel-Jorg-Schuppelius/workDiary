<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : McpOAuthRefreshToken.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Mcp;

use App\Models\Platform\User;
use Illuminate\Database\Eloquent\{Builder, Model, Prunable};
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Refresh-Token (MVP-1065): gehasht, rotiert bei jeder Einlösung. Alle Token
 * einer Anmeldung teilen die `family`; ein rotiertes bleibt bis zum Ablauf
 * stehen, damit sein erneutes Einlösen als Diebstahl erkannt wird.
 *
 * @property int $id
 * @property int $mcp_oauth_client_id
 * @property int $user_id
 * @property int|null $personal_access_token_id
 * @property string $family
 * @property string $token_hash
 * @property list<string> $scopes
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $rotated_at
 */
class McpOAuthRefreshToken extends Model {
    use Prunable;

    protected $table = 'mcp_oauth_refresh_tokens';

    protected $fillable = ['mcp_oauth_client_id', 'user_id', 'personal_access_token_id', 'family', 'token_hash', 'scopes', 'expires_at', 'rotated_at'];

    /** @var array<string, string> */
    protected $casts = [
        'scopes' => 'array',
        'expires_at' => 'datetime',
        'rotated_at' => 'datetime',
    ];

    /** @return Builder<static> */
    public function prunable(): Builder {
        return static::query()->where('expires_at', '<', now());
    }

    /** Ein noch gesetztes Zugriffstoken geht mit. */
    protected function pruning(): void {
        if ($this->personal_access_token_id !== null) {
            PersonalAccessToken::query()->whereKey($this->personal_access_token_id)->delete();
        }
    }

    /** @return BelongsTo<McpOAuthClient, $this> */
    public function client(): BelongsTo {
        return $this->belongsTo(McpOAuthClient::class, 'mcp_oauth_client_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }
}
