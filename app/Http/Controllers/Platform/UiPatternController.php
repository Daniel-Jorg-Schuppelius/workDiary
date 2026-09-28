<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UiPatternController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Concerns\RequiresPlatformOperator;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Vorschau der UI-Grundbausteine in ihren Varianten (MVP-958); nur für Betreiber. */
class UiPatternController extends Controller {
    use RequiresPlatformOperator;

    public const TONES = ['primary', 'success', 'warning', 'error', 'info', 'ghost'];

    public function index(): View {
        $this->assertPlatformOperator();

        return view('admin.ui-patterns', ['tones' => self::TONES]);
    }
}
