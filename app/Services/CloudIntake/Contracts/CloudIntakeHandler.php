<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudIntakeHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\CloudIntake\Contracts;

use App\Enums\CloudIntake\{CloudIntakeItemStatus, CloudIntakeRouteTarget};
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Models\Platform\User;
use App\Plugins\Support\Intake\IntakeItem;
use Illuminate\Database\Eloquent\Model;

/**
 * Erweiterungspunkt des Cloud-Eingangs (MVP-863): Ein Fachmodul übernimmt
 * Dateien seines Routenziels (Vergabepaket, openTRANS-Bestellung) selbst;
 * Rechnung und Dokument bleiben im Kern. Registrierung über `Manifest::extensions()`.
 */
interface CloudIntakeHandler {
    public function target(): CloudIntakeRouteTarget;

    /** @return array{status: CloudIntakeItemStatus, imported: Model|null, reason: string|null} */
    public function intake(CloudDocumentConnection $connection, IntakeItem $item, string $quarantinePath, User $actor): array;
}
