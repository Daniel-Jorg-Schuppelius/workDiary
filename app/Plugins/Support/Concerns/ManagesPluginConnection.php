<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManagesPluginConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Concerns;

use App\Plugins\Support\Contracts\ManagedPluginConnection;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;

/**
 * Wiederkehrende Aktionen der Plugin-Admin-Controller (Vollscan 2026-10-21):
 * manuellen Lauf anstoßen und Verbindung trennen. Setzt
 * {@see ResolvesPluginOrgContext} im selben Controller voraus.
 */
trait ManagesPluginConnection {
    /**
     * Läuft über die Queue: ein Voll-Abgleich im Web-Request lief in den
     * PHP-Timeout (Vollscan 2026-08-23, J17).
     *
     * @param  class-string<Model&ManagedPluginConnection>  $connectionClass
     * @param  array<string, mixed>  $options
     * @param  (Closure(ManagedPluginConnection): bool)|null  $ready  Vorgabe: aktiv
     */
    private function queueConnectionRun(string $connectionClass, string $command, array $options, string $auditEvent, string $doneKey, string $missingKey, ?Closure $ready = null): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = $connectionClass::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof ManagedPluginConnection || ! ($ready ?? static fn (ManagedPluginConnection $c): bool => $c->isActive())($connection)) {
            return back()->with('error', __($missingKey));
        }

        Artisan::queue($command, ['--organization' => (string) $organization->id, ...$options]);
        $connection->audit($auditEvent, ['by_user_id' => (int) $admin->id]);

        return back()->with('success', __($doneKey));
    }

    /**
     * Trennen heißt deaktivieren: Zugangsdaten und Zuordnungen bleiben für ein
     * erneutes Verbinden erhalten.
     *
     * @param  class-string<Model&ManagedPluginConnection>  $connectionClass
     */
    private function deactivateConnection(string $connectionClass, string $auditEvent, string $doneKey): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = $connectionClass::query()->where('organization_id', $organization->id)->first();
        if ($connection !== null) {
            $connection->forceFill(['active' => false])->save();
            $connection->audit($auditEvent, ['by_user_id' => (int) $admin->id]);
        }

        return back()->with('success', __($doneKey));
    }
}
