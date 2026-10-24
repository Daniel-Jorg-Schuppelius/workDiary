<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VikunjaVersionUnsupportedException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Vikunja\Exceptions;

use RuntimeException;

/** Vikunja vor 1.0 kennt `GET /tasks` mit Filter nicht und sperrt `/user` für API-Token. */
class VikunjaVersionUnsupportedException extends RuntimeException {}
