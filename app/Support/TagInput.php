<?php
/*
 * Created on   : Sat Jul 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TagInput.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Support;

use App\Models\Tag;

/**
 * Kanonische Normalisierung der Tag-Formularfelder (Vollaudit 2026-07, M40) —
 * ersetzt vier controller-lokale Sqid-Dekoder-Kopien und acht Kopien der
 * new_tags-Zerlegung. Kanonische Semantik (vorher nur DiaryController):
 * new_tags trennt an Komma, Semikolon UND Zeilenumbruch und deckelt auf
 * 20 Namen pro Request. Die Org-Prüfung der IDs passiert bewusst NICHT hier,
 * sondern zentral in {@see \App\Models\Concerns\HasTags::syncTagsFromInput()}.
 */
final class TagInput {
    /** Deckel für frei eingegebene neue Tag-Namen je Request. */
    private const MAX_NEW_NAMES = 20;

    /**
     * Dekodiert `tag_ids[]` (opake Sqids aus dem Tag-Picker) zu numerischen
     * PKs; rohe numerische IDs bleiben als Backward-Compat-Fallback erlaubt.
     *
     * @return array<int, int>
     */
    public static function ids(mixed $raw): array {
        if (! is_array($raw)) {
            return [];
        }

        return collect($raw)
            ->map(fn($v) => is_scalar($v) ? Sqid::decodeOrNumeric(Tag::class, (string) $v) : null)
            ->filter()
            ->map(fn($v) => (int) $v)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Zerlegt den `new_tags`-Rohstring in bereinigte Tag-Namen.
     *
     * @return array<int, string>
     */
    public static function names(mixed $raw): array {
        $raw = is_scalar($raw) ? (string) $raw : '';
        if (trim($raw) === '') {
            return [];
        }

        return collect(preg_split('/[,;\n]+/', $raw) ?: [])
            ->map(fn($v) => trim((string) $v))
            ->filter()
            ->unique()
            ->take(self::MAX_NEW_NAMES)
            ->values()
            ->all();
    }
}
