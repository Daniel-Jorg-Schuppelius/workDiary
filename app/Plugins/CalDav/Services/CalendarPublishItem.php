<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendarPublishItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\CalDav\Services;

/**
 * Ein zu publizierendes Kalenderelement (Feature 058). Providerneutral: eine
 * stabile UID, der CalDAV-Ressourcenname, das ICS-Dokument und die lokale
 * Herkunft (Morph) für die idempotente {@see \App\Models\ExternalReference}.
 * `cancelled` markiert ein zu entfernendes Element (abgesagter Termin).
 */
final class CalendarPublishItem {
    public function __construct(
        public readonly string $uid,
        public readonly string $objectName,
        public readonly string $ics,
        public readonly string $referenceableType,
        public readonly int $referenceableId,
        public readonly bool $cancelled = false,
    ) {}
}
