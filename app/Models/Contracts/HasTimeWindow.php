<?php

/*
 * Created on   : Tue May 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasTimeWindow.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Contracts;

use Carbon\Carbon;

/**
 * Markiert ein Model mit einem zeitlichen Fensterbereich (Start/Ende).
 * Getter statt `@property`, weil PHPStan Property-Annotationen auf Interfaces
 * nicht in der Generic-Auflösung berücksichtigt.
 */
interface HasTimeWindow
{
    public function getStartAt(): ?Carbon;

    public function getEndAt(): ?Carbon;
}
