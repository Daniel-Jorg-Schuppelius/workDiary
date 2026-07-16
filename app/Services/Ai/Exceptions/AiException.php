<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Exceptions;

use RuntimeException;

/**
 * Basisklasse aller KI-Fundament-Fehler (Feature 025, MVP-398/399).
 * Meldungen sind redigiert: nie Prompt-Inhalte, nie Schlüssel.
 */
class AiException extends RuntimeException {}
