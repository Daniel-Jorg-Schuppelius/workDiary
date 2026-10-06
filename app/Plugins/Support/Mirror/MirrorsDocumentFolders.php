<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirrorsDocumentFolders.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Mirror;

use App\Enums\Document\DocumentType;
use Illuminate\Support\{Carbon, Str};

/**
 * Gemeinsame Ordner-/Pfadlogik der Ablage-Verbindungen (MVP-330, Bauturbo A10 —
 * gehoben aus `WebdavConnection`, Feature 058/MVP-127): Regel Dokumenttyp →
 * Zielordner (`folder_map`, sonst `default_folder`), Quellen-Gating (`sources`)
 * und der deterministische Zielpfad `ordner/document-<id>.<ext>`. Erwartet die
 * Spalten `default_folder`, `folder_map`, `sources`, `last_mirrored_at`.
 */
trait MirrorsDocumentFolders {
    /** Spiegelbare Quellen (Rang 19); null/leer = nur DMS-Dokumente (rückwärtskompatibel). */
    public const SOURCES = ['document', 'invoice_pdf', 'protocol_pdf'];

    /**
     * Ordnerabbild aus den zwei Formularspalten (Dokumenttyp, Pfad): nur
     * bekannte Typen mit Pfad, je Typ ein Ordner.
     *
     * @param  array<array-key, mixed>  $types
     * @param  array<array-key, mixed>  $paths
     * @return array<string, string>
     */
    public static function folderMapFromInput(array $types, array $paths): array {
        $valid = array_map(static fn (DocumentType $t): string => $t->value, DocumentType::cases());

        $map = [];
        foreach ($types as $i => $type) {
            $type = is_string($type) ? $type : '';
            $path = isset($paths[$i]) && is_string($paths[$i]) ? trim($paths[$i], '/') : '';
            if ($type !== '' && $path !== '' && in_array($type, $valid, true)) {
                $map[$type] = $path;
            }
        }

        return $map;
    }

    /** Spiegelt diese Anbindung die angegebene Quelle? Ohne Auswahl nur `document`. */
    public function mirrorsSource(string $source): bool {
        $sources = $this->sources;
        if (! is_array($sources) || $sources === []) {
            return $source === 'document';
        }

        return in_array($source, $sources, true);
    }

    /** Zielordner (relativ zum Ablage-Root) für einen Dokumenttyp; sonst der Standardordner. */
    public function folderFor(string $documentType): string {
        $map = $this->folder_map ?? [];
        $folder = $map[$documentType] ?? $this->default_folder;

        return trim((string) $folder, '/');
    }

    /** Relativer Zielpfad eines Dokuments (Ordner nach Typ + stabiler Dateiname). */
    public function relativePathFor(string $documentType, int $documentId, string $originalName): string {
        $ext = '';
        if (str_contains($originalName, '.')) {
            $candidate = strtolower((string) Str::of($originalName)->afterLast('.'));
            if (preg_match('/^[a-z0-9]{1,8}$/', $candidate) === 1) {
                $ext = '.' . $candidate;
            }
        }
        $folder = $this->folderFor($documentType);
        $file = 'document-' . $documentId . $ext;

        return $folder !== '' ? $folder . '/' . $file : $file;
    }

    public function organizationId(): int {
        return (int) $this->organization_id;
    }

    /** Vermerkt den letzten erfolgreichen Spiegel-Zeitpunkt. */
    public function markMirrored(): void {
        $this->forceFill(['last_mirrored_at' => Carbon::now()])->save();
    }
}
