<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupNaming.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Backup;

use App\Models\SystemSetting;
use Illuminate\Support\Str;

/**
 * Remote-Namensgebung der Cloud-Backups (Feature 017 Phase 32, Konzept
 * §Betrieb): Dateien heißen `<installations-pseudonym>/<snapshot-uuid>/part-<n>`
 * + `commit.manifest` — das Pseudonym ist ein einmalig erzeugter Zufallswert
 * in system_settings, KEINE Kunden-/DB-Namen im Cloudziel.
 */
class BackupNaming {
    public const PSEUDONYM_KEY = 'backup.installation_pseudonym';

    public const COMMIT_NAME = 'commit.manifest';

    /** Installations-Pseudonym; wird beim ersten Zugriff erzeugt. */
    public function pseudonym(): string {
        $setting = SystemSetting::query()->firstOrNew(['key' => self::PSEUDONYM_KEY]);
        $value = $setting->exists ? $setting->resolvedValue() : null;
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $pseudonym = 'wd-' . Str::lower(Str::random(12));
        $setting->setResolvedValue($pseudonym, false);
        $setting->save();

        return $pseudonym;
    }

    /** Remote-Prefix einer Generation (relativ zum Backupbereich). */
    public function remotePrefix(string $snapshotUuid): string {
        return $this->pseudonym() . '/' . $snapshotUuid;
    }

    public function partName(string $remotePrefix, int $partNo): string {
        return $remotePrefix . '/part-' . $partNo;
    }

    public function commitName(string $remotePrefix): string {
        return $remotePrefix . '/' . self::COMMIT_NAME;
    }
}
