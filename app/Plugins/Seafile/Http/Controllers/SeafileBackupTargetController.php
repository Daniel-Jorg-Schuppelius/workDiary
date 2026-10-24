<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SeafileBackupTargetController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Seafile\Http\Controllers;

use App\Enums\Backup\{BackupProvider, BackupTargetStatus};
use App\Http\Controllers\Controller;
use App\Models\Backup\BackupTargetConnection;
use App\Models\Platform\User;
use App\Plugins\Seafile\Api\SeafileClientFactory;
use App\Plugins\Seafile\{SeafileConfig, SeafilePlugin};
use App\Plugins\Seafile\Services\SeafileBackupTarget;
use App\Plugins\Support\Backup\BackupTargetSelfTest;
use App\Services\Backup\BackupNaming;
use App\Support\{Sqid, UrlSafety};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

/**
 * Systemweites Seafile-Backupziel anbinden (MVP-1348), nur Plattform-Admin:
 * Adresse, Konto-Token und Bibliothek. Nach dem Speichern werden Konto und
 * Speicher bestätigt, der Backupbereich angelegt und Schreiben, Lesen und
 * Löschen einmal probiert.
 */
class SeafileBackupTargetController extends Controller {
    /** Verbindungsdialog; `?connection=<sqid>` meldet ein bestehendes Ziel neu an. */
    public function connectForm(Request $request): View {
        Gate::authorize('create', BackupTargetConnection::class);

        return view('seafile::backup-targets._connect_dialog', [
            'connection' => $this->existing((string) $request->query('connection', '')),
        ]);
    }

    public function connect(Request $request, SeafileClientFactory $clients, SeafileBackupTarget $target): RedirectResponse {
        Gate::authorize('create', BackupTargetConnection::class);
        $admin = $this->authUser();
        $data = $this->validated($request);

        $connection = $this->existing((string) $request->input('connection', '')) ?? $this->draft($admin);
        $connection->forceFill([
            'name' => $data['name'],
            'server_url' => rtrim(trim($data['server_url']), '/'),
            'access_token' => $data['api_token'],
            'last_error' => null,
            'last_error_at' => null,
            'consecutive_failures' => 0,
            'disabled_at' => null,
        ])->save();

        try {
            $wanted = mb_strtolower(trim($data['library']));
            $matches = array_values(array_filter($clients->forBackup($connection)->libraries(),
                static fn (array $library): bool => $library['id'] === $wanted || mb_strtolower($library['name']) === $wanted));
            $library = count($matches) === 1 ? $matches[0] : null;
            $problem = match (true) {
                $matches === [] => 'library_not_found',
                $library === null => 'library_ambiguous',
                $library['encrypted'] => 'library_encrypted',
                ! $library['writable'] => 'library_read_only',
                default => null,
            };
            if ($problem !== null) {
                $connection->recordConnectionFailure($problem);

                return back()->withInput($request->except('api_token'))
                    ->withErrors(['library' => (string) __('seafile::seafile.backup.' . $problem)]);
            }
            $connection->forceFill(['options' => ['library_id' => $library['id'], 'library_own' => $library['own']] + (array) $connection->options])->save();

            $account = $target->account($connection);
            $quota = $target->quota($connection);
            $pseudonym = app(BackupNaming::class)->pseudonym();
            $rootRef = $target->ensureFolder($connection, $pseudonym);
            app(BackupTargetSelfTest::class)->run(app(SeafilePlugin::class), $connection, $pseudonym);
            $connection->forceFill([
                'external_account_id' => $account->externalId,
                'external_account_label' => $account->label . ' · ' . $library['name'],
                'quota_total' => $quota['total'],
                'quota_used' => $quota['used'],
                'quota_checked_at' => now(),
                'root_folder_ref' => $rootRef,
                'status' => BackupTargetStatus::Active,
            ])->save();
        } catch (Throwable $e) {
            // Nur die Fehlerklasse — nie Adresse oder Token.
            $connection->recordConnectionFailure(class_basename($e));

            return back()->with('error', __('backup_targets.flash.account_failed', ['class' => class_basename($e)]));
        }

        $connection->audit('backupTarget.connected', ['by_user_id' => (int) $admin->id, 'provider' => BackupProvider::Seafile->value]);

        return redirect()->route('admin.backup-targets.index')->with('success', __('backup_targets.flash.connected'));
    }

    /** @return array{name: string, server_url: string, api_token: string, library: string} */
    private function validated(Request $request): array {
        $allowPrivate = SeafileConfig::allowPrivateBackupTargets();

        /** @var array{name: string, server_url: string, api_token: string, library: string} $data */
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'server_url' => ['required', 'string', 'max:512', function (string $attribute, mixed $value, callable $fail) use ($allowPrivate): void {
                $url = trim((string) $value);
                if (! str_starts_with(strtolower($url), 'https://')) {
                    $fail((string) __('seafile::seafile.backup.https_required'));

                    return;
                }
                if (! $allowPrivate && ! UrlSafety::isAcceptableExternalHttpUrl($url)) {
                    $fail((string) __('seafile::seafile.backup.unsafe_url'));
                }
            }],
            'api_token' => ['required', 'string', 'max:512'],
            'library' => ['required', 'string', 'max:255'],
        ]);

        return $data;
    }

    private function existing(string $rawSqid): ?BackupTargetConnection {
        $id = $rawSqid !== '' ? Sqid::decode(BackupTargetConnection::class, $rawSqid) : null;

        return is_int($id) ? BackupTargetConnection::query()->where('provider', BackupProvider::Seafile->value)->find($id) : null;
    }

    private function draft(User $admin): BackupTargetConnection {
        return BackupTargetConnection::query()->create([
            'provider' => BackupProvider::Seafile,
            'name' => 'Seafile',
            'status' => BackupTargetStatus::Draft,
            'created_by_user_id' => $admin->id,
        ]);
    }
}
