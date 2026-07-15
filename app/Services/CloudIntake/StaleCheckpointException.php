<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StaleCheckpointException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\CloudIntake;

use RuntimeException;

/**
 * Ungültiger/abgelaufener Delta-Checkpoint (Feature 080): Adapter werfen sie,
 * der Runner antwortet mit einem BEGRENZTEN Vollabgleich ab Stammordner —
 * nie mit blindem Neuimport (Idempotenz über Übergabenachweise).
 */
class StaleCheckpointException extends RuntimeException {}
