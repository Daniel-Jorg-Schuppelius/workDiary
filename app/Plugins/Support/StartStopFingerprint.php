<?php
/*
 * Created on   : Tue Jul 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StartStopFingerprint.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

/**
 * Fingerabdruck-Bildung für Fremdsysteme mit echten Start-/Stoppzeiten
 * (Toggl, Kimai, Clockify) — dieselbe Ableitung wie beim Import über
 * {@see RemoteTimeFingerprint::of()}, nur aus dem lokalen Stand.
 *
 * Die Fremd-Projekt-ID kommt aus dem Referenz-Payload; nicht-numerische IDs
 * (Clockify) bleiben außen vor, genau wie beim Import.
 */
trait StartStopFingerprint {
    /**
     * @param  array{description: ?string, date: ?\Carbon\CarbonImmutable, started_at: ?\Carbon\CarbonImmutable, ended_at: ?\Carbon\CarbonImmutable, minutes: int, billable: bool}  $entry
     * @param  array<string, mixed>  $context
     */
    public function fingerprintOf(array $entry, array $context): ?string {
        if ($entry['started_at'] === null || $entry['ended_at'] === null) {
            return null;
        }

        return RemoteTimeFingerprint::fromParts(
            $entry['started_at'],
            $entry['ended_at'],
            $entry['description'],
            is_numeric($context['project_id'] ?? null) ? (int) $context['project_id'] : null,
            $entry['billable'],
        );
    }
}
