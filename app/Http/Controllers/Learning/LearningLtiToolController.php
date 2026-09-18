<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningLtiToolController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Learning\Concerns\RendersLtiAutoPost;
use App\Services\Learning\LearningLtiToolService;
use CommonToolkit\Helper\Data\WebLinkHelper;
use ELearningToolkit\Lti\{DeepLinkingResponse, LtiException};
use Illuminate\Http\{RedirectResponse, Request, Response};

/** WorkDiary als LTI-1.3-Tool (Feature 149): Login, Start und Deep Linking fremder Plattformen. */
final class LearningLtiToolController extends Controller {
    use RendersLtiAutoPost;

    private const DEEP_LINKING_SESSION_KEY = 'learning.lti.tool.deep_linking';

    public function __construct(private readonly LearningLtiToolService $tool) {}

    /** Login-Anstoß der Plattform (GET oder POST). */
    public function login(Request $request): Response|RedirectResponse {
        try {
            return redirect()->away($this->tool->login($request->isMethod('POST') ? $request->post() : $request->query->all()));
        } catch (LtiException $e) {
            return $this->rejected($e);
        }
    }

    /**
     * Start durch die Plattform. Der fremde POST trägt kein Sitzungscookie; die Sitzung
     * entsteht hier und gilt ab der Weiterleitung.
     */
    public function launch(Request $request): Response|RedirectResponse {
        try {
            $result = $this->tool->launch($request->string('id_token')->toString(), $request->string('state')->toString());
        } catch (LtiException $e) {
            return $this->rejected($e);
        }

        if ($result['kind'] === 'launch') {
            $request->session()->put(ExternalLearningController::SESSION_KEY, $result['enrollment']->id);
            // Nachweis des Zugangs für die laufende Prüfung im externen Bereich
            // (Audit 2026-09-17, learning-ext-1).
            $request->session()->put(ExternalLearningController::LTI_SESSION_KEY, $result['enrollment']->id);
            $request->session()->regenerate();

            return redirect()->route('learning.external.show');
        }

        $request->session()->put(self::DEEP_LINKING_SESSION_KEY, $result['context']);
        $request->session()->regenerate();

        return redirect()->route('learning.lti.tool.deep-linking');
    }

    public function deepLinking(Request $request): Response {
        try {
            $choices = $this->tool->deepLinkingChoices($this->context($request));
        } catch (LtiException $e) {
            return $this->rejected($e);
        }

        return response()->view('learning.lti.tool-deep-linking', $choices);
    }

    public function deepLinkingSubmit(Request $request): Response {
        $course = $request->string('course')->toString();

        try {
            $result = $this->tool->deepLinkingResponse($this->context($request), $course !== '' ? $course : null);
        } catch (LtiException $e) {
            return $this->rejected($e);
        }

        // Eine Auswahl gilt einmal.
        $request->session()->forget(self::DEEP_LINKING_SESSION_KEY);

        return $this->autoPost($result['returnUrl'], [DeepLinkingResponse::FORM_PARAMETER => $result['jwt']], WebLinkHelper::origin($result['returnUrl']) ?? "'none'");
    }

    /** @return array<mixed> */
    private function context(Request $request): array {
        $context = $request->session()->get(self::DEEP_LINKING_SESSION_KEY);

        return is_array($context) ? $context : [];
    }

    private function rejected(LtiException $e): Response {
        return response()->view('learning.lti.error', ['reason' => $e->reason], 400);
    }
}
