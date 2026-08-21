<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebdavBackupTargetController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Webdav\Http\Controllers;

use App\Enums\Backup\{BackupProvider, BackupTargetStatus};
use App\Http\Controllers\Controller;
use App\Models\Backup\BackupTargetConnection;
use App\Models\User;
use App\Plugins\Webdav\WebdavPlugin;
use App\Services\Backup\BackupNaming;
use App\Support\{Sqid, UrlSafety};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use Throwable;

/**
 * Generisches WebDAV-Backupziel anbinden (Feature 123, MVP-612).
 *
 * Nur Plattform-Admin. Vor dem endgültigen Speichern läuft ein echter
 * Schreib-/Lese-/Löschtest gegen den Server: Ein Backupziel, das erst im
 * Ernstfall als unbrauchbar auffällt, ist schlimmer als keins.
 */
class WebdavBackupTargetController extends Controller {
    public function connectForm(Request $request): View {
        Gate::authorize('create', BackupTargetConnection::class);

        return view('admin.backup-targets._webdav_connect_dialog', [
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
            'access_token' => $data['password'],
            'last_error' => null,
            'last_error_at' => null,
            'consecutive_failures' => 0,
            'disabled_at' => null,
        ])->save();

        try {
            $client = app(WebdavPlugin::class)->backupClient($connection);
            $account = $client->account();
            $root = $client->ensureFolder($this->targetPath($data['base_path']));
            // Erst schreiben, lesen, löschen — dann gilt das Ziel als brauchbar.
            $client->selfTest($root);
            $quota = $client->quota();

            $connection->forceFill([
                'external_account_id' => $account->externalId,
                'external_account_label' => $account->label,
                'quota_total' => $quota['total'],
                'quota_used' => $quota['used'],
                'quota_checked_at' => now(),
                'root_folder_ref' => $root,
                'status' => BackupTargetStatus::Active,
            ])->save();
        } catch (Throwable $e) {
            // Nur die Fehlerklasse — nie Server-URL/Passwort.
            $connection->recordConnectionFailure(class_basename($e));

            return back()->with('error', __('backup_targets.webdav.flash.selftest_failed', ['class' => class_basename($e)]));
        }

        $connection->audit('backupTarget.connected', ['by_user_id' => (int) $admin->id, 'provider' => BackupProvider::Webdav->value]);

        return redirect()->route('admin.backup-targets.index')->with('success', __('backup_targets.flash.connected'));
    }

    /** Backupbereich: optionaler Basispfad + Pseudonym-Ordner. */
    private function targetPath(string $basePath): string {
        $base = trim($basePath, '/');
        $pseudonym = app(BackupNaming::class)->pseudonym();

        return $base === '' ? $pseudonym : $base . '/' . $pseudonym;
    }

    /**
     * @return array{name: string, server_url: string, username: string, password: string, base_path: string}
     */
    private function validated(Request $request): array {
        $allowPrivate = (bool) config('plugins.webdav.allow_private_targets', false);

        /** @var array{name: string, server_url: string, username: string, password: string, base_path?: string} $data */
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'server_url' => ['required', 'string', 'max:512', function (string $attribute, mixed $value, callable $fail) use ($allowPrivate): void {
                $url = trim((string) $value);
                if (! str_starts_with(strtolower($url), 'https://')) {
                    $fail((string) __('backup_targets.webdav.validation.https_required'));

                    return;
                }
                if (! $allowPrivate && ! UrlSafety::isAcceptableExternalHttpUrl($url)) {
                    $fail((string) __('backup_targets.webdav.validation.unsafe_url'));
                }
            }],
            'username' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:512'],
            'base_path' => ['nullable', 'string', 'max:255'],
        ]);

        return $data + ['base_path' => ''];
    }

    private function existingConnection(string $rawSqid): ?BackupTargetConnection {
        if ($rawSqid === '') {
            return null;
        }
        $id = Sqid::decode(BackupTargetConnection::class, $rawSqid);

        return is_int($id)
            ? BackupTargetConnection::query()->where('provider', BackupProvider::Webdav->value)->find($id)
            : null;
    }

    private function resolveConnection(string $rawSqid, User $admin): BackupTargetConnection {
        if ($rawSqid !== '') {
            $id = Sqid::decode(BackupTargetConnection::class, $rawSqid);
            if (is_int($id)) {
                $existing = BackupTargetConnection::query()->where('provider', BackupProvider::Webdav->value)->find($id);
                if ($existing !== null) {
                    return $existing;
                }
            }
        }

        return BackupTargetConnection::query()->create([
            'provider' => BackupProvider::Webdav,
            'name' => 'WebDAV',
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
