<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NextcloudBackupTargetController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Nextcloud\Http\Controllers;

use App\Enums\Backup\{BackupProvider, BackupTargetStatus};
use App\Http\Controllers\Controller;
use App\Models\Backup\BackupTargetConnection;
use App\Models\User;
use App\Plugins\Nextcloud\Api\NextcloudBackupClient;
use App\Plugins\Nextcloud\NextcloudConfig;
use App\Services\Backup\BackupNaming;
use App\Support\{Sqid, UrlSafety};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use Throwable;

/**
 * Credential-Anbindung des SYSTEMWEITEN Nextcloud-Backupziels (Feature 017
 * Phase 32, MVP-383). Nur Plattform-Admin (Policy hart auf `is_platform_admin`).
 * Server-URL + Nutzer + App-Passwort statt OAuth; nach dem Speichern werden
 * Kontoidentität + Quota bestätigt und der Pseudonym-Backupordner angelegt.
 */
class NextcloudBackupTargetController extends Controller {
    /** Verbindungsdialog (Modal); optional `?connection=<sqid>` für Re-Auth. */
    public function connectForm(Request $request): View {
        Gate::authorize('create', BackupTargetConnection::class);

        return view('admin.backup-targets._nextcloud_connect_dialog', [
            'connection' => $this->existingConnection((string) $request->query('connection', '')),
        ]);
    }

    public function connect(Request $request): RedirectResponse {
        Gate::authorize('create', BackupTargetConnection::class);
        $admin = $this->admin();

        $data = $this->validated($request);

        $connection = $this->resolveConnection((string) $request->input('connection', ''), $admin);
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

        // Kontoidentität + Quota bestätigen, Pseudonym-Backupordner anlegen.
        try {
            $client = new NextcloudBackupClient($connection);
            $account = $client->account();
            $quota = $client->quota();
            $rootRef = $client->ensureFolder(app(BackupNaming::class)->pseudonym());
            $connection->forceFill([
                'external_account_id' => $account->externalId,
                'external_account_label' => $account->label,
                'quota_total' => $quota['total'],
                'quota_used' => $quota['used'],
                'quota_checked_at' => now(),
                'root_folder_ref' => $rootRef,
                'status' => BackupTargetStatus::Active,
            ])->save();
        } catch (Throwable $e) {
            // Nur die Fehlerklasse — nie Server-URL/Passwort.
            $connection->recordConnectionFailure(class_basename($e));

            return back()->with('error', __('backup_targets.flash.account_failed', ['class' => class_basename($e)]));
        }

        $connection->audit('backupTarget.connected', ['by_user_id' => (int) $admin->id, 'provider' => BackupProvider::Nextcloud->value]);

        return redirect()->route('admin.backup-targets.index')->with('success', __('backup_targets.flash.connected'));
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
                    $fail((string) __('backup_targets.nextcloud.validation.https_required'));

                    return;
                }
                if (! $allowPrivate && ! UrlSafety::isAcceptableExternalHttpUrl($url)) {
                    $fail((string) __('backup_targets.nextcloud.validation.unsafe_url'));
                }
            }],
            'username' => ['required', 'string', 'max:190'],
            'app_password' => ['required', 'string', 'max:512'],
        ]);

        return $data;
    }

    private function existingConnection(string $rawSqid): ?BackupTargetConnection {
        if ($rawSqid === '') {
            return null;
        }
        $id = Sqid::decode(BackupTargetConnection::class, $rawSqid);

        return is_int($id)
            ? BackupTargetConnection::query()->where('provider', BackupProvider::Nextcloud->value)->find($id)
            : null;
    }

    private function resolveConnection(string $rawSqid, User $admin): BackupTargetConnection {
        if ($rawSqid !== '') {
            $id = Sqid::decode(BackupTargetConnection::class, $rawSqid);
            if (is_int($id)) {
                $existing = BackupTargetConnection::query()->where('provider', BackupProvider::Nextcloud->value)->find($id);
                if ($existing !== null) {
                    return $existing;
                }
            }
        }

        return BackupTargetConnection::query()->create([
            'provider' => BackupProvider::Nextcloud,
            'name' => 'Nextcloud',
            'status' => BackupTargetStatus::Draft,
            'created_by_user_id' => $admin->id,
        ]);
    }

    private function admin(): User {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
