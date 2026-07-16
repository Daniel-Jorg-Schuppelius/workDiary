<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NextcloudIntakeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Nextcloud\Http\Controllers;

use App\Enums\CloudIntake\{CloudIntakeConnectionStatus, CloudIntakeProvider};
use App\Http\Controllers\Controller;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Models\{Organization, User};
use App\Plugins\Nextcloud\Api\NextcloudIntakeClient;
use App\Plugins\Nextcloud\NextcloudConfig;
use App\Support\{Sqid, UrlSafety};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use Throwable;

/**
 * Credential-Anbindung einer Nextcloud-Quelle (Feature 080, MVP-382). Anders
 * als die OAuth-Provider (Dropbox/Graph/Google) gibt der Admin Server-URL,
 * Nutzer und ein widerrufbares App-Passwort ein — kein Redirect-Flow. Nur
 * HTTPS und (außer bei On-Premise-Freigabe) öffentlich routbare Ziele; aktiv
 * importiert wird erst nach Stammordner-Wahl + gültiger Regel.
 */
class NextcloudIntakeController extends Controller {
    /** Verbindungsdialog (Modal); optional `?connection=<sqid>` für Re-Auth. */
    public function connectForm(Request $request): View {
        Gate::authorize('create', CloudDocumentConnection::class);

        return view('admin.cloud-intake._nextcloud_connect_dialog', [
            'connection' => $this->existingConnection((string) $request->query('connection', '')),
        ]);
    }

    public function connect(Request $request): RedirectResponse {
        Gate::authorize('create', CloudDocumentConnection::class);
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $this->validated($request);

        $connection = $this->resolveConnection($organization, (string) $request->input('connection', ''), $admin);
        $connection->forceFill([
            'name' => $data['name'],
            'server_url' => rtrim(trim($data['server_url']), '/'),
            'username' => $data['username'],
            'access_token' => $data['app_password'],
            'last_error' => null,
            'last_error_at' => null,
            'consecutive_failures' => 0,
            'disabled_at' => null,
        ])->save();

        try {
            $account = (new NextcloudIntakeClient($connection))->account();
            $connection->forceFill([
                'external_account_id' => $account->externalId,
                'external_account_label' => $account->label,
                'container_id' => $connection->container_id ?? 'files',
            ])->save();
        } catch (Throwable $e) {
            // Nur die Fehlerklasse — nie Server-URL/Passwort.
            $connection->recordConnectionFailure(class_basename($e));

            return back()->with('error', __('cloud_intake.flash.account_failed', ['class' => class_basename($e)]));
        }

        // Aktiv erst mit Stammordner + aktiver Regel, sonst Entwurf.
        $ready = $connection->root_folder_path !== null && $connection->routes()->where('active', true)->exists();
        $connection->forceFill([
            'status' => $ready ? CloudIntakeConnectionStatus::Active : CloudIntakeConnectionStatus::Draft,
        ])->save();

        $connection->audit('cloudIntake.connected', ['by_user_id' => (int) $admin->id, 'provider' => CloudIntakeProvider::Nextcloud->value]);

        return redirect()->route('admin.cloud-intake.index')->with('success', __('cloud_intake.flash.connected'));
    }

    /**
     * @return array{name: string, server_url: string, username: string, app_password: string}
     */
    private function validated(Request $request): array {
        $allowPrivate = NextcloudConfig::allowPrivateTargets();

        /** @var array{name: string, server_url: string, username: string, app_password: string} $data */
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'server_url' => ['required', 'string', 'max:512', function (string $attribute, mixed $value, callable $fail) use ($allowPrivate): void {
                $url = trim((string) $value);
                if (! str_starts_with(strtolower($url), 'https://')) {
                    $fail((string) __('cloud_intake.nextcloud.validation.https_required'));

                    return;
                }
                // Konfigurationszeit-Prüfung ohne DNS; die verbindliche SSRF-
                // Prüfung erfolgt zur Laufzeit im Transport (DNS-Rebinding-sicher).
                if (! $allowPrivate && ! UrlSafety::isAcceptableExternalHttpUrl($url)) {
                    $fail((string) __('cloud_intake.nextcloud.validation.unsafe_url'));
                }
            }],
            'username' => ['required', 'string', 'max:190'],
            'app_password' => ['required', 'string', 'max:512'],
        ]);

        return $data;
    }

    private function existingConnection(string $rawSqid): ?CloudDocumentConnection {
        if ($rawSqid === '') {
            return null;
        }
        $id = Sqid::decode(CloudDocumentConnection::class, $rawSqid);

        return is_int($id)
            ? CloudDocumentConnection::query()->where('provider', CloudIntakeProvider::Nextcloud->value)->find($id)
            : null;
    }

    private function resolveConnection(Organization $organization, string $rawSqid, User $admin): CloudDocumentConnection {
        if ($rawSqid !== '') {
            $id = Sqid::decode(CloudDocumentConnection::class, $rawSqid);
            if (is_int($id)) {
                $existing = CloudDocumentConnection::query()
                    ->where('organization_id', $organization->id)
                    ->where('provider', CloudIntakeProvider::Nextcloud->value)
                    ->find($id);
                if ($existing !== null) {
                    return $existing;
                }
            }
        }

        return CloudDocumentConnection::query()->create([
            'organization_id' => $organization->id,
            'provider' => CloudIntakeProvider::Nextcloud,
            'name' => 'Nextcloud',
            'status' => CloudIntakeConnectionStatus::Draft,
            'created_by_user_id' => $admin->id,
        ]);
    }

    private function admin(): User {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function organization(User $admin): Organization {
        $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        abort_unless($organization instanceof Organization, 403);
        abort_unless((int) $organization->id === (int) $admin->organization_id, 403);

        return $organization;
    }
}
