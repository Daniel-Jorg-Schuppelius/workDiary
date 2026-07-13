<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteCalendarConnection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Calendar;

/**
 * Verbindungs-Vertrag für den {@see RemoteCalendarPublishService}
 * (MVP-328, Bauturbo A8): das Publish braucht von der Provider-Verbindung
 * nur die Organisation, den Publish-Zeitstempel und die einheitliche
 * Verbindungs-Gesundheit ({@see \App\Models\Concerns\HasConnectionHealth}
 * liefert record*-Methoden inkl. Auto-Disable-Zählung).
 */
interface RemoteCalendarConnection {
    public function organizationId(): int;

    /** Setzt `last_published_at` nach einem Publish-Lauf. */
    public function markPublished(): void;

    /** {@see \App\Models\Concerns\HasConnectionHealth} */
    public function recordConnectionFailure(string $error): void;

    public function recordConnectionSuccess(): void;
}
