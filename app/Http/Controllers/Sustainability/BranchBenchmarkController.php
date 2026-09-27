<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BranchBenchmarkController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Sustainability;

use App\Http\Controllers\Concerns\RequiresPlatformOperator;
use App\Http\Controllers\Controller;
use App\Services\Sustainability\BranchEmissionBenchmarkService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Anonymer Branchenvergleich der Emissionen über alle Mandanten (MVP-949), nur für Betreiber. */
class BranchBenchmarkController extends Controller {
    use RequiresPlatformOperator;

    public function index(Request $request, BranchEmissionBenchmarkService $benchmark): View {
        $this->assertPlatformOperator();
        $year = max(2000, min((int) now()->year, $request->integer('year', (int) now()->subYear()->year)));

        return view('sustainability.branch-benchmark', ['benchmark' => $benchmark->benchmark($year), 'year' => $year]);
    }
}
