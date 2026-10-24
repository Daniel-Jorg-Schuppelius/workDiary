<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentIntakeOrigins.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Contracts;

use App\Models\CloudIntake\CloudDocumentConnection;
use App\Plugins\Support\Intake\IntakeItem;
use Illuminate\Database\Eloquent\Model;

/**
 * Optional neben {@see DocumentIntakeSource}: die Quelle hält nach einer
 * Übernahme die Herkunft am lokalen Datensatz fest (ExternalReference),
 * damit `<x-origin-badge>` zum Original verlinkt (Feature 170).
 */
interface DocumentIntakeOrigins {
    public function intakeImported(CloudDocumentConnection $connection, IntakeItem $item, Model $imported): void;
}
