<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SeafileIntakeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Seafile\Http\Controllers;

use App\Enums\CloudIntake\{CloudIntakeConnectionStatus, CloudIntakeProvider};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Models\Platform\Organization;
use App\Plugins\Seafile\Api\SeafileClientFactory;
use App\Plugins\Seafile\SeafilePlugin;
use App\Plugins\Seafile\Services\SeafileIntake;
use App\Plugins\Support\TaskSync\SelfHostedInstance;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Seafile-Quelle des Cloud-Dokumenteingangs verbinden (MVP-1346):
 * eine Verbindung je Organisation, Zugangsdaten aus den Plugin-Einstellungen.
 * Erneutes Verbinden prüft das Konto und hält die Instanz fest — zeigen die
 * Einstellungen inzwischen auf eine andere Instanz, muss die Verbindung erst
 * getrennt werden.
 */
class SeafileIntakeController extends Controller {
    use ResolvesCurrentOrganization;

    public function connectForm(SeafileClientFactory $clients): View {
        Gate::authorize('create', CloudDocumentConnection::class);
        $organization = $this->currentOrganizationOrAbort();
        $baseUrl = $clients->baseUrl((int) $organization->id);

        return view('seafile::cloud-intake._connect_dialog', [
            'configured' => $clients->isConfigured((int) $organization->id),
            'instance' => $baseUrl !== null ? SelfHostedInstance::name($baseUrl) : null,
            'connection' => $this->existing($organization),
            'pluginId' => SeafilePlugin::ID,
        ]);
    }

    public function connect(Request $request, SeafileClientFactory $clients, SeafileIntake $intake): RedirectResponse {
        Gate::authorize('create', CloudDocumentConnection::class);
        $admin = $this->authUser();
        $organization = $this->currentOrganizationOrAbort();
        $data = $request->validate(['name' => ['required', 'string', 'max:190']]);

        if (! $clients->isConfigured((int) $organization->id)) {
            return back()->with('error', (string) __('seafile::seafile.flash.not_configured'));
        }

        $connection = $this->existing($organization) ?? CloudDocumentConnection::query()->create([
            'organization_id' => $organization->id,
            'provider' => CloudIntakeProvider::Seafile,
            'name' => $data['name'],
            'status' => CloudIntakeConnectionStatus::Draft,
            'created_by_user_id' => $admin->id,
        ]);
        $connection->forceFill(['name' => $data['name']])->save();

        try {
            $account = $intake->account($connection);
        } catch (Throwable $e) {
            $connection->recordConnectionFailure(class_basename($e));

            return back()->with('error', (string) __('cloud_intake.flash.account_failed', ['class' => class_basename($e)]));
        }

        $previous = (string) $connection->external_account_id;
        if ($previous !== '' && Str::before($previous, ':') !== Str::before($account->externalId, ':')) {
            return back()->with('error', (string) __('seafile::seafile.flash.instance_changed'));
        }

        $connection->forceFill([
            'external_account_id' => $account->externalId,
            'external_account_label' => $account->label,
            'last_error' => null,
            'last_error_at' => null,
            'consecutive_failures' => 0,
            'disabled_at' => null,
        ])->save();

        // Aktiv erst mit gewählter Quelle, Stammordner und aktiver Regel, sonst Entwurf.
        $ready = $connection->container_id !== null && $connection->root_folder_path !== null && $connection->routes()->where('active', true)->exists();
        $connection->forceFill([
            'status' => $ready ? CloudIntakeConnectionStatus::Active : CloudIntakeConnectionStatus::Draft,
        ])->save();
        $connection->audit('cloudIntake.connected', ['by_user_id' => (int) $admin->id, 'provider' => CloudIntakeProvider::Seafile->value]);

        return redirect()->route('admin.cloud-intake.index')->with('success', (string) __('cloud_intake.flash.connected'));
    }

    private function existing(Organization $organization): ?CloudDocumentConnection {
        return CloudDocumentConnection::query()
            ->where('organization_id', $organization->id)
            ->where('provider', CloudIntakeProvider::Seafile->value)
            ->first();
    }
}
