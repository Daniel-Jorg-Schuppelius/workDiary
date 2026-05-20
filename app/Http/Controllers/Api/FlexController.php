<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FlexController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Flextime\FlexCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlexController extends Controller {
    public function __construct(protected FlexCalculator $calc) {
    }

    public function summary(Request $request): JsonResponse {
        $year = (int) $request->input('year', CarbonImmutable::now()->year);
        $month = (int) $request->input('month', CarbonImmutable::now()->month);

        return response()->json(['data' => $this->calc->monthlyBalance($this->authUser(), $year, $month)]);
    }
}
