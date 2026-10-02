<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : McpOAuthCode.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Mcp;

use App\Models\Platform\User;
use Illuminate\Database\Eloquent\{Builder, MassPrunable, Model};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Autorisierungscode (MVP-1065): gehasht, zehn Minuten gültig, einmal
 * einlösbar; trägt die PKCE-Challenge und die bestätigten Scopes.
 *
 * @property int $id
 * @property int $mcp_oauth_client_id
 * @property int $user_id
 * @property string $code_hash
 * @property string $redirect_uri
 * @property string $code_challenge
 * @property list<string> $scopes
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $used_at
 * @property string|null $family
 */
class McpOAuthCode extends Model {
    use MassPrunable;

    protected $table = 'mcp_oauth_codes';

    protected $fillable = ['mcp_oauth_client_id', 'user_id', 'code_hash', 'redirect_uri', 'code_challenge', 'scopes', 'expires_at', 'used_at', 'family'];

    /** @var array<string, string> */
    protected $casts = [
        'scopes' => 'array',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    /** @return Builder<static> Codes leben zehn Minuten; nach einem Tag brauchen auch Wiederverwendungsprüfungen sie nicht mehr. */
    public function prunable(): Builder {
        return static::query()->where('expires_at', '<', now()->subDay());
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
