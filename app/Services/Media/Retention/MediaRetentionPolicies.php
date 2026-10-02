<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MediaRetentionPolicies.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Media\Retention;

use App\Models\Media\Dictation;
use App\Services\Retention\Contracts\RetentionPolicyProvider;
use App\Services\Retention\RetentionPolicy;
use Illuminate\Support\Facades\Storage;

/** Löschbereiche der Medien (Aufbewahrung, Feature 130) — gemeldet über das Manifest (MVP-863). */
final class MediaRetentionPolicies implements RetentionPolicyProvider {
    public function policies(): array {
        return [
            // Diktate (MVP-1060): das Transkript ist übernommen oder verworfen;
            // eine nie verarbeitete Aufnahme geht mit.
            new RetentionPolicy(
                area: 'dictations',
                modelClass: Dictation::class,
                overdueQuery: fn ($organization, $cutoff) => Dictation::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('created_at', '<', $cutoff),
                purge: function (Dictation $subject): void {
                    if ($subject->audio_path !== null) {
                        Storage::disk((string) $subject->audio_disk)->delete($subject->audio_path);
                    }
                    $subject->delete();
                },
            ),
        ];
    }
}
