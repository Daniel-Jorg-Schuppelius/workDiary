<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourAlreadyMaterializedException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Diary\Tour;
use RuntimeException;

/** Fahrten einer Tour werden genau einmal erzeugt (MVP-1172). */
class TourAlreadyMaterializedException extends RuntimeException {
    public function __construct(public readonly Tour $tour) {
        parent::__construct((string) __('Die Fahrten dieser Tour wurden bereits ins Fahrtenbuch übernommen.'));
    }
}
