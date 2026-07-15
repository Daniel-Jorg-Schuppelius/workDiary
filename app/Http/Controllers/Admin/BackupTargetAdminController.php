<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupTargetAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\Backup\BackupProvider;
use App\Http\Controllers\Controller;
use App\Models\Backup\{BackupGeneration, BackupTargetConnection};
use App\Plugins\Contracts\BackupTarget;
use App\Plugins\PluginManager;
use App\Services\Backup\BackupKeyring;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

/**
 * Verwaltungsseite der SYSTEMWEITEN Cloud-Backupziele (Feature 017
 * Phase 32, MVP-363/366): Provider-Karten mit OAuth-Anbindung,
 * Generationen-Tabelle, Legal-Hold, expliziter Bereinigungsworkflow.
 * Zugriff ausschließlich Plattform-Admin (Policies hart auf
 * `is_platform_admin`); Zieltrennung löscht remote NICHTS.
 */
class BackupTargetAdminController extends Controller {
    public function index(): View {
        Gate::authorize('viewAny', BackupTargetConnection::class);

        $keyring = app(BackupKeyring::class);

        return view('admin.backup-targets.index', [
            'connections' => BackupTargetConnection::query()->orderBy('provider')->orderBy('id')->get(),
            'generations' => BackupGeneration::query()
                ->with('connection')
                ->orderByDesc('started_at')
                ->limit(60)
                ->get(),
            'providers' => BackupProvider::cases(),
            'hasMasterKey' => $keyring->hasMasterKey(),
            'hasRecoveryKey' => $keyring->hasRecoveryKey(),
        ]);
    }

    /** Zieltrennung: Verbindung entfernen — Remote-Daten bleiben unberührt. */
    public function disconnect(BackupTargetConnection $backupConnection): RedirectResponse {
        Gate::authorize('delete', $backupConnection);

        $backupConnection->audit('backupTarget.disconnected', ['provider' => $backupConnection->provider->value]);
        // Nachweise überleben die Trennung (connection_id nullOnDelete).
        $backupConnection->delete();

        return redirect()->route('admin.backup-targets.index')
            ->with('success', __('backup_targets.flash.disconnected'));
    }

    public function toggleHold(BackupGeneration $backupGeneration): RedirectResponse {
        Gate::authorize('hold', $backupGeneration);

        $backupGeneration->forceFill(['legal_hold' => !$backupGeneration->legal_hold])->save();
        $backupGeneration->audit(
            $backupGeneration->legal_hold ? 'backup.holdSet' : 'backup.holdReleased',
            ['snapshot_uuid' => $backupGeneration->snapshot_uuid],
        );

        return redirect()->route('admin.backup-targets.index')->with(
            'success',
            $backupGeneration->legal_hold ? __('backup_targets.flash.hold_set') : __('backup_targets.flash.hold_released'),
        );
    }

    /** Vorschau der Remote-Objekte einer Verbindung (Bereinigungsworkflow). */
    public function cleanupPreview(BackupTargetConnection $backupConnection): View {
        Gate::authorize('view', $backupConnection);

        $objects = [];
        $error = null;
        $adapter = $this->adapter($backupConnection);
        if ($adapter === null) {
            $error = __('backup_targets.flash.not_configured');
        } else {
            try {
                $objects = $adapter->backupList($backupConnection, app(\App\Services\Backup\BackupNaming::class)->pseudonym());
            } catch (Throwable $e) {
                $error = class_basename($e);
            }
        }

        $known = BackupGeneration::query()
            ->whereNotNull('remote_prefix')
            ->pluck('id', 'remote_prefix')
            ->all();

        return view('admin.backup-targets.cleanup', [
            'connection' => $backupConnection,
            'objects' => $objects,
            'knownPrefixes' => $known,
            'error' => $error,
        ]);
    }

    /**
     * Explizite Bereinigung einer Generation nach Vorschau + Bestätigung:
     * löscht den Remote-Ordner der Generation und danach den Nachweis.
     */
    public function destroyGeneration(BackupGeneration $backupGeneration): RedirectResponse {
        Gate::authorize('delete', $backupGeneration);

        if ($backupGeneration->legal_hold) {
            return redirect()->route('admin.backup-targets.index')
                ->with('error', __('backup_targets.flash.hold_blocks_delete'));
        }

        $connection = $backupGeneration->connection;
        $prefix = (string) $backupGeneration->remote_prefix;
        if ($connection !== null && $prefix !== '') {
            $adapter = $this->adapter($connection);
            if ($adapter === null) {
                return redirect()->route('admin.backup-targets.index')
                    ->with('error', __('backup_targets.flash.not_configured'));
            }

            try {
                // Ordner-Löschung ist bei allen drei Providern rekursiv —
                // deckt Teile, Commit-Manifest und Reste in einem Schritt ab.
                $folderName = basename($prefix);
                foreach ($adapter->backupList($connection, dirname($prefix)) as $object) {
                    if ($object->name === $folderName) {
                        $adapter->backupDelete($connection, $object->ref);
                        break;
                    }
                }
            } catch (Throwable $e) {
                return redirect()->route('admin.backup-targets.index')
                    ->with('error', __('backup_targets.flash.cleanup_failed', ['class' => class_basename($e)]));
            }
        }

        $backupGeneration->audit('backup.generationPurged', ['snapshot_uuid' => $backupGeneration->snapshot_uuid]);
        $backupGeneration->delete();

        return redirect()->route('admin.backup-targets.index')
            ->with('success', __('backup_targets.flash.generation_deleted'));
    }

    private function adapter(BackupTargetConnection $connection): ?BackupTarget {
        $plugin = app(PluginManager::class)->find($connection->provider->pluginId());

        return $plugin instanceof BackupTarget ? $plugin : null;
    }
}
