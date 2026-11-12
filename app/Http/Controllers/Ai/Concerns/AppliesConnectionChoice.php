<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppliesConnectionChoice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Ai\Concerns;

use App\Models\Ai\AiProviderConnection;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Ai\{AiCapabilityRegistry, AiConnectionChoice};
use App\Support\Sqid;
use Illuminate\Http\Request;

/**
 * Reicht die Verbindungswahl aus der Vorschlagskomponente an den Aufruf
 * (Feature 025, MVP-1590): `connection_id` (Sqid) und `capability` aus dem
 * Formular. Ohne Wahl bleibt es beim Routing der Organisation.
 */
trait AppliesConnectionChoice {
    protected function applyConnectionChoice(Request $request): void {
        if (! $request->filled('connection_id')) {
            return;
        }
        $request->merge(['connection_id' => Sqid::decodeOrNumeric(AiProviderConnection::class, $request->input('connection_id'))]);
        $data = $request->validate([
            'connection_id' => ['required', 'integer', new ExistsInCurrentOrganization('ai_provider_connections')],
            'capability' => ['required', 'string', 'max:120'],
        ]);
        abort_unless(app(AiCapabilityRegistry::class)->has((string) $data['capability']), 422);

        app(AiConnectionChoice::class)->choose((string) $data['capability'], (int) $data['connection_id']);
    }
}
