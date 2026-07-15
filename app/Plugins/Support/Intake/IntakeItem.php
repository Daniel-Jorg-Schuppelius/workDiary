<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakeItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Support\Intake;

/**
 * Eine reguläre Datei aus dem überwachten Stammordner (Feature 080,
 * MVP-351). Identität sind Provider-IDs + Revision — Pfade sind nur
 * Routing-/Anzeigeinformation (Umbenennen/Verschieben erzeugt kein zweites
 * Dokument, offene Fragen entscheidet die Inbox).
 */
final readonly class IntakeItem {
    public function __construct(
        /** Stabile Provider-Item-ID. */
        public string $itemId,
        /** Pfad relativ zum überwachten Stammordner (führender Slash entfällt). */
        public string $path,
        public string $name,
        /** Revision/ETag des Dateistands — Teil des Übergabenachweis-Uniques. */
        public string $revision,
        public int $size,
        public ?string $mime = null,
        public ?string $modifiedAt = null,
        /** Provider-Content-Hash, sofern verfügbar (Dropbox content_hash, Graph cTag …). */
        public ?string $contentHash = null,
        public ?string $parentId = null,
    ) {}
}
