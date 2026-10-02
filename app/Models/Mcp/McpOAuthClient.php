<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : McpOAuthClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Mcp;

use Illuminate\Database\Eloquent\{Builder, MassPrunable, Model};

/**
 * Öffentlicher OAuth-Client aus der dynamischen Registrierung (MVP-1065), etwa
 * ein Web-Connector von Claude oder ChatGPT. Kein Geheimnis — der Schutz liegt
 * in exakt registrierten Rücksprungadressen und PKCE.
 *
 * @property int $id
 * @property string $client_key
 * @property string $name
 * @property list<string> $redirect_uris
 * @property \Illuminate\Support\Carbon|null $last_used_at
 */
class McpOAuthClient extends Model {
    use MassPrunable;

    protected $table = 'mcp_oauth_clients';

    protected $fillable = ['client_key', 'name', 'redirect_uris', 'last_used_at'];

    /** @var array<string, string> */
    protected $casts = [
        'redirect_uris' => 'array',
        'last_used_at' => 'datetime',
    ];

    /**
     * Registrierungen räumen: nie genutzt nach 30 Tagen, sonst nach einem Jahr
     * ohne Nutzung — dann sind auch alle Refresh-Token längst abgelaufen.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder {
        return static::query()->where(static fn (Builder $q) => $q
            ->where(static fn (Builder $never) => $never->whereNull('last_used_at')->where('created_at', '<', now()->subDays(30)))
            ->orWhere('last_used_at', '<', now()->subYear()));
    }

    public function allowsRedirect(string $uri): bool {
        return in_array($uri, $this->redirect_uris, true);
    }
}
