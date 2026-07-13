<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirrorConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Mirror;

/**
 * Vertrag einer Ablage-Verbindung je Organisation (MVP-330, Bauturbo A10):
 * das, was der gemeinsame Spiegel-Kern ({@see DocumentMirrorService},
 * {@see MirrorOutboxDispatcher}) von einer Verbindung braucht — Org-Bezug,
 * Quellen-Gating, deterministische Zielpfade und die Verbindungs-Gesundheit
 * (MVP-178). Implementiert von `WebdavConnection` und `SharepointConnection`;
 * die Pfadlogik liefert das Trait {@see MirrorsDocumentFolders}.
 */
interface MirrorConnection {
    public function organizationId(): int;

    /** Spiegelt diese Anbindung die angegebene Quelle? Ohne Auswahl nur `document`. */
    public function mirrorsSource(string $source): bool;

    /** Relativer Zielpfad eines Dokuments (Ordner nach Typ + stabiler Dateiname). */
    public function relativePathFor(string $documentType, int $documentId, string $originalName): string;

    /** Vermerkt den letzten erfolgreichen Spiegel-Zeitpunkt (`last_mirrored_at`). */
    public function markMirrored(): void;

    /** Verbindungs-Gesundheit (MVP-178): Fehler zählen, ggf. Auto-Disable. */
    public function recordConnectionFailure(string $error): void;

    /** Verbindungs-Gesundheit (MVP-178): Erfolg setzt den Fehlerzähler zurück. */
    public function recordConnectionSuccess(): void;
}
