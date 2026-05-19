<?php

/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Controller.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    /**
     * Liefert den eingeloggten User. Setzt voraus, dass die Route hinter
     * der `auth`-Middleware liegt — wird hier per Exception abgesichert,
     * damit PHPStan ohne `?` arbeiten kann.
     */
    protected function authUser(): User
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new \RuntimeException('authUser() ohne authentifizierten User aufgerufen.');
        }

        return $user;
    }
}
