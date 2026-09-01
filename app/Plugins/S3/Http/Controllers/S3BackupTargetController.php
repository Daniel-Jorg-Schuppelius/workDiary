<?php
/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : S3BackupTargetController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\S3\Http\Controllers;

use App\Enums\Backup\{BackupProvider, BackupTargetStatus};
use App\Http\Controllers\Controller;
use App\Models\Backup\BackupTargetConnection;
use App\Models\User;
use App\Plugins\S3\S3Plugin;
use App\Services\Backup\BackupNaming;
use App\Support\{Sqid, UrlSafety};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use Throwable;

/**
 * S3-kompatibles Backupziel anbinden (Feature 123, MVP-726).
 *
 * Nur Plattform-Admin. Vor dem endgültigen Speichern läuft ein echter
 * Schreib-/Lese-/Löschtest gegen den Bucket — nach dem Muster des
 * WebDAV-Ziels: Ein Backupziel, das erst im Ernstfall als unbrauchbar
 * auffällt, ist schlimmer als keins. Bei S3 kommt hinzu, dass reine
 * Leserechte oder eine Objektsperre den Fehler sonst bis zur ersten
 * Aufräumung verstecken.
 */
class S3BackupTargetController extends Controller {
    public function connectForm(Request $request): View {
        Gate::authorize('create', BackupTargetConnection::class);

        return view('admin.backup-targets._s3_connect_dialog', [
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
            'server_url' => $data['endpoint'] === '' ? null : rtrim($data['endpoint'], '/'),
            'username' => $data['access_key'],
            'access_token' => $data['secret_key'],
            'options' => [
                'bucket' => $data['bucket'],
                'region' => $data['region'],
                'path_style' => $data['path_style'],
            ],
            'last_error' => null,
            'last_error_at' => null,
            'consecutive_failures' => 0,
            'disabled_at' => null,
        ])->save();

        try {
            $client = app(S3Plugin::class)->backupClient($connection);
            $account = $client->account();
            $root = $client->ensureFolder($this->targetPath($data['prefix']));
            $client->selfTest($root);

            $connection->forceFill([
                'external_account_id' => $account->externalId,
                'external_account_label' => $account->label,
                'root_folder_ref' => $root,
                'status' => BackupTargetStatus::Active,
            ])->save();
        } catch (Throwable $e) {
            // Nur die Fehlerklasse — nie Endpoint, Schlüssel oder Signaturkopf.
            $connection->recordConnectionFailure(class_basename($e));

            return back()->with('error', __('backup_targets.s3.flash.selftest_failed', ['class' => class_basename($e)]));
        }

        $connection->audit('backupTarget.connected', ['by_user_id' => (int) $admin->id, 'provider' => BackupProvider::S3->value]);

        return redirect()->route('admin.backup-targets.index')->with('success', __('backup_targets.flash.connected'));
    }

    /** Backupbereich: optionales Präfix + Pseudonym-Ordner. */
    private function targetPath(string $prefix): string {
        $base = trim($prefix, '/');
        $pseudonym = app(BackupNaming::class)->pseudonym();

        return $base === '' ? $pseudonym : $base . '/' . $pseudonym;
    }

    /**
     * @return array{name: string, endpoint: string, region: string, bucket: string, access_key: string, secret_key: string, prefix: string, path_style: bool}
     */
    private function validated(Request $request): array {
        $allowPrivate = (bool) config('plugins.s3.allow_private_targets', false);

        /** @var array{name: string, endpoint?: string|null, region: string, bucket: string, access_key: string, secret_key: string, prefix?: string|null, path_style?: mixed} $data */
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            // Leer = AWS S3 (das SDK bildet den Endpoint aus der Region).
            // Alles andere muss HTTPS sein und die SSRF-Prüfung bestehen.
            'endpoint' => ['nullable', 'string', 'max:512', function (string $attribute, mixed $value, callable $fail) use ($allowPrivate): void {
                $url = trim((string) $value);
                if ($url === '') {
                    return;
                }
                if (! str_starts_with(strtolower($url), 'https://')) {
                    $fail((string) __('backup_targets.s3.validation.https_required'));

                    return;
                }
                if (! $allowPrivate && ! UrlSafety::isAcceptableExternalHttpUrl($url)) {
                    $fail((string) __('backup_targets.s3.validation.unsafe_url'));
                }
            }],
            'region' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'bucket' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/'],
            'access_key' => ['required', 'string', 'max:190'],
            'secret_key' => ['required', 'string', 'max:512'],
            'prefix' => ['nullable', 'string', 'max:255'],
            'path_style' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => $data['name'],
            'endpoint' => trim((string) ($data['endpoint'] ?? '')),
            'region' => $data['region'],
            'bucket' => $data['bucket'],
            'access_key' => $data['access_key'],
            'secret_key' => $data['secret_key'],
            'prefix' => (string) ($data['prefix'] ?? ''),
            'path_style' => $request->boolean('path_style'),
        ];
    }

    private function existingConnection(string $rawSqid): ?BackupTargetConnection {
        if ($rawSqid === '') {
            return null;
        }
        $id = Sqid::decode(BackupTargetConnection::class, $rawSqid);

        return is_int($id)
            ? BackupTargetConnection::query()->where('provider', BackupProvider::S3->value)->find($id)
            : null;
    }

    private function resolveConnection(string $rawSqid, User $admin): BackupTargetConnection {
        if ($rawSqid !== '') {
            $id = Sqid::decode(BackupTargetConnection::class, $rawSqid);
            if (is_int($id)) {
                $existing = BackupTargetConnection::query()->where('provider', BackupProvider::S3->value)->find($id);
                if ($existing !== null) {
                    return $existing;
                }
            }
        }

        return BackupTargetConnection::query()->create([
            'provider' => BackupProvider::S3,
            'name' => 'S3',
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
