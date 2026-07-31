<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttachesImportedTags.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Support;

use App\Models\{Organization, Tag, TimeEntry};

/**
 * Hängt Tag-Namen aus einem Fremdsystem-Import an einen Zeiteintrag.
 *
 * Bewusst additiv (syncWithoutDetaching): ein Re-Import darf manuell
 * angehängte lokale Tags nie entfernen; eine leere Liste ist ein No-op.
 * Läuft console-/queue-sicher über die org-explizite Tag-Auflösung —
 * Scheduler-Importe haben kein currentOrganization-Binding.
 */
trait AttachesImportedTags {
    /**
     * @param  list<string>  $tagNames
     */
    protected function attachImportedTags(Organization $organization, TimeEntry $timeEntry, array $tagNames): void {
        $names = collect($tagNames)
            ->map(fn ($name): string => trim((string) $name))
            ->filter()
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->take(20) // Deckel wie TagInput::MAX_NEW_NAMES
            ->all();
        if ($names === []) {
            return;
        }

        $ids = array_map(
            fn (string $name): int => Tag::findOrCreateByNameForOrganization($name, (int) $organization->id)->id,
            $names,
        );

        $timeEntry->tags()->syncWithoutDetaching($ids);
    }
}
