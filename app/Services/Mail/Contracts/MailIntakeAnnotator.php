<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailIntakeAnnotator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Mail\Contracts;

use App\Models\Mail\EmailConnection;
use App\Models\Platform\Organization;
use App\Services\Mail\ParsedMessage;

/**
 * Erweiterungspunkt des Mail-Eingangs (Feature 166, MVP-1200): ein Fachmodul
 * markiert eine Nachricht, die es erkannt, aber bewusst nicht übernommen hat
 * (mehrere Belege, fremder Absender). Die Ergänzung landet im
 * `remote_snapshot` des Inbox-Eintrags, damit die Klärung von Hand die
 * Kandidaten sieht. Registrierung über `Manifest::extensions()`.
 */
interface MailIntakeAnnotator {
    /**
     * Snapshot-Ergänzung oder leer, wenn nichts zu markieren ist. Schlüssel
     * dürfen keinen Platzhalter des Basis-Snapshots überschreiben.
     *
     * @return array<string, mixed>
     */
    public function annotate(Organization $organization, EmailConnection $connection, ParsedMessage $message): array;
}
