<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PersonalOnboardingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Services\Onboarding\PersonalOnboardingResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};

/** Persönlicher Einstieg je Rolle (Feature 037, MVP-911). */
class PersonalOnboardingController extends Controller {
    public function __construct(private readonly PersonalOnboardingResolver $onboarding) {}

    public function index(Request $request): View {
        return view('me.onboarding', ['checklist' => $this->onboarding->forUser($this->authUser())]);
    }

    public function done(string $step): RedirectResponse {
        $this->onboarding->markDone($this->authUser(), $step);

        return back()->with('success', __('onboarding.personal.marked'));
    }

    public function dismiss(): RedirectResponse {
        $this->onboarding->dismiss($this->authUser());

        return back()->with('success', __('onboarding.personal.dismissed'));
    }
}
