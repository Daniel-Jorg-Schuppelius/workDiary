<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IcsFeedController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Event\IcsFeedService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class IcsFeedController extends Controller {
    public function __construct(
        private readonly IcsFeedService $ics,
    ) {
    }

    public function personal(): Response {
        /** @var User $user */
        $user = Auth::user();

        return response($this->ics->feedForUser($user), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="workdiary-events.ics"',
        ]);
    }

    public function public(): Response {
        return response($this->ics->feedPublic(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="workdiary-public-events.ics"',
        ]);
    }
}
