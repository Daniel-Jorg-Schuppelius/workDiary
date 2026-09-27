<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublicSustainabilityExcerptController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Sustainability;

use App\Http\Controllers\Controller;
use App\Models\Platform\Organization;
use App\Services\Sustainability\SustainabilityExcerptService;
use Illuminate\View\View;

/** Öffentlicher Nachhaltigkeitsauszug (MVP-930); ohne Token, Freigabe oder Snapshot → 404. */
class PublicSustainabilityExcerptController extends Controller {
    public function show(string $token, SustainabilityExcerptService $excerpt): View {
        $organization = $excerpt->resolve($token);
        abort_unless($organization instanceof Organization, 404);
        $data = $excerpt->excerpt($organization);
        abort_if($data === null, 404);

        return view('public.sustainability-excerpt', ['orgName' => $organization->name] + $data);
    }
}
