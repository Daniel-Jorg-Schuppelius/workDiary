<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlaneVersionUnsupportedException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Plane\Exceptions;

use RuntimeException;

/** Plane vor 0.28 kennt `GET /api/v1/users/me/` nicht. */
class PlaneVersionUnsupportedException extends RuntimeException {}
