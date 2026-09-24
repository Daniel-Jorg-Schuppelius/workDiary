<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeRetentionPolicies.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\TimeExport\Retention;

use App\Models\Time\TimeExport;
use App\Services\Retention\Contracts\RetentionPolicyProvider;
use App\Services\Retention\RetentionPolicy;

/** Löschbereiche des Moduls (Aufbewahrung, Feature 130) — gemeldet über das Manifest (MVP-863). */
final class TimeRetentionPolicies implements RetentionPolicyProvider {
    public function policies(): array {
        return [
            // Lohn-/Zeitexporte inkl. abgelegter Dateien. Vollaudit 2026-07
            // (N6): Purge auditiert jetzt als export.deleted und räumt Zeilen mit.
            new RetentionPolicy(
                area: 'exports',
                modelClass: TimeExport::class,
                overdueQuery: fn($organization, $cutoff) => TimeExport::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('created_at', '<', $cutoff),
                purge: function (TimeExport $subject): void {
                    $subject->audit('export.deleted', ['reason' => 'retention', 'file_path' => $subject->file_path]);
                    $path = (string) ($subject->file_path ?? '');
                    if ($path !== '') {
                        \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
                    }
                    $subject->lines()->delete();
                    $subject->delete();
                },
            ),
        ];
    }
}
