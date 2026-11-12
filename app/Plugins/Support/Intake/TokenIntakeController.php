<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TokenIntakeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Intake;

use App\Enums\CloudIntake\{CloudIntakeConnectionStatus, CloudIntakeProvider};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Models\Platform\Organization;
use App\Plugins\Support\TaskSync\SelfHostedInstance;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Verbinden einer selbst gehosteten Quelle des Cloud-Dokumenteingangs mit
 * API-Token (Paperless-ngx, Seafile; Phase 158, Konsolidierung kon-03):
 * eine Verbindung je Organisation, Zugangsdaten aus den Plugin-Einstellungen.
 * Erneutes Verbinden prüft das Konto und hält die Instanz fest — zeigen die
 * Einstellungen inzwischen auf eine andere Instanz, muss die Verbindung
 * erst getrennt werden. Gegenstück für OAuth-Quellen: {@see IntakeOAuthController}.
 * Dialog `<id>::cloud-intake._connect_dialog`, Meldungen `<id>::<id>.flash.*`.
 */
abstract class TokenIntakeController extends Controller {
    use ResolvesCurrentOrganization;

    abstract protected function provider(): CloudIntakeProvider;

    /** Basis-URL und Token in den Plugin-Einstellungen der Organisation vorhanden? */
    abstract protected function isConfigured(int $organizationId): bool;

    /** Basis-URL der eingestellten Instanz; null, wenn nicht eingerichtet. */
    abstract protected function baseUrl(int $organizationId): ?string;

    /** Konto bei der Quelle bestätigen; wirft, wenn die Instanz nicht antwortet oder den Zugang ablehnt. */
    abstract protected function account(CloudDocumentConnection $connection): IntakeAccount;

    /** @return view-string */
    abstract protected function connectDialogView(): string;

    public function connectForm(): View {
        Gate::authorize('create', CloudDocumentConnection::class);
        $organization = $this->currentOrganizationOrAbort();
        $baseUrl = $this->baseUrl((int) $organization->id);

        return view($this->connectDialogView(), [
            'configured' => $this->isConfigured((int) $organization->id),
            'instance' => $baseUrl !== null ? SelfHostedInstance::name($baseUrl) : null,
            'connection' => $this->existing($organization),
            'pluginId' => $this->provider()->pluginId(),
        ]);
    }

    public function connect(Request $request): RedirectResponse {
        Gate::authorize('create', CloudDocumentConnection::class);
        $admin = $this->authUser();
        $organization = $this->currentOrganizationOrAbort();
        $data = $request->validate(['name' => ['required', 'string', 'max:190']]);

        if (! $this->isConfigured((int) $organization->id)) {
            return back()->with('error', $this->flash('not_configured'));
        }

        $connection = $this->existing($organization) ?? CloudDocumentConnection::query()->create([
            'organization_id' => $organization->id,
            'provider' => $this->provider(),
            'name' => $data['name'],
            'status' => CloudIntakeConnectionStatus::Draft,
            'created_by_user_id' => $admin->id,
        ]);
        $connection->forceFill(['name' => $data['name']])->save();

        try {
            $account = $this->account($connection);
        } catch (Throwable $e) {
            $connection->recordConnectionFailure(class_basename($e));

            return back()->with('error', (string) __('cloud_intake.flash.account_failed', ['class' => class_basename($e)]));
        }

        $previous = (string) $connection->external_account_id;
        if ($previous !== '' && Str::before($previous, ':') !== Str::before($account->externalId, ':')) {
            return back()->with('error', $this->flash('instance_changed'));
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
            'status' => $connection->readyStatus($ready),
        ])->save();
        $connection->audit('cloudIntake.connected', ['by_user_id' => (int) $admin->id, 'provider' => $this->provider()->value]);

        return redirect()->route('admin.cloud-intake.index')->with('success', (string) __('cloud_intake.flash.connected'));
    }

    private function flash(string $key): string {
        $pluginId = $this->provider()->pluginId();

        return (string) __($pluginId . '::' . $pluginId . '.flash.' . $key);
    }

    private function existing(Organization $organization): ?CloudDocumentConnection {
        return CloudDocumentConnection::query()
            ->where('organization_id', $organization->id)
            ->where('provider', $this->provider()->value)
            ->first();
    }
}
