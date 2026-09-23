<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningLtiPlatformController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Enums\Learning\LearningUnitKind;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Learning\Concerns\RendersLtiAutoPost;
use App\Models\Learning\{LearningCourse, LearningEnrollment, LearningLtiLink, LearningUnit};
use App\Models\Platform\User;
use App\Services\Learning\LearningLtiPlatformService;
use App\Support\Sqid;
use CommonToolkit\Helper\Data\WebLinkHelper;
use ELearningToolkit\Lti\{DeepLinkingResponse, LtiException};
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** WorkDiary als LTI-1.3-Plattform (Feature 149): Start einer LTI-Einheit. */
final class LearningLtiPlatformController extends Controller {
    use RendersLtiAutoPost;

    public function __construct(private readonly LearningLtiPlatformService $platform) {}

    public function launch(LearningEnrollment $enrollment, LearningUnit $unit): RedirectResponse {
        /** @var User $learner */
        $learner = Auth::user();

        abort_unless($enrollment->user_id === $learner->id, 404);
        abort_unless($unit->learning_course_id === $enrollment->learning_course_id, 404);
        abort_unless($unit->kind === LearningUnitKind::Lti, 404);

        $link = LearningLtiLink::query()->with('tool')->where('learning_unit_id', $unit->id)->first();
        $tool = $link?->tool;

        abort_if($link === null || $tool === null || ! $tool->is_active, 404);

        try {
            return redirect()->away($this->platform->launchLoginUrl($enrollment, $link, $tool, $learner));
        } catch (LtiException $e) {
            throw ValidationException::withMessages(['lti' => (string) __('learning.errors.lti.failed', ['reason' => $e->reason])]);
        }
    }

    /**
     * Authentifizierungsanfrage des Tools (Security Framework 5.1.1.2).
     *
     * Ein fremder POST trägt das Sitzungscookie nicht (`SameSite=Lax`). Er wird
     * einmal über eine eigene Seite erneut abgeschickt — dieser POST ist same-site.
     */
    public function auth(Request $request): Response {
        $parameters = self::stringFields($request->isMethod('POST') ? $request->post() : $request->query());

        if (! Auth::check()) {
            if ($request->isMethod('POST') && ($parameters['_relay'] ?? null) === null) {
                return $this->autoPost(route('learning.lti.platform.auth'), ['_relay' => '1'] + $parameters, "'self'");
            }

            throw new AuthenticationException();
        }

        /** @var User $user */
        $user = Auth::user();
        unset($parameters['_relay']);

        try {
            $result = $this->platform->authenticate($parameters, $user);
        } catch (LtiException $e) {
            return response()->view('learning.lti.error', ['reason' => $e->reason], 400);
        }

        return $this->autoPost($result['action'], $result['fields'], WebLinkHelper::origin($result['action']) ?? "'none'");
    }

    /** Deep-Linking-Antwort des Tools — sitzungslos; der signierte Zustand trägt Einheit und Tool. */
    public function deepLinkingReturn(Request $request): HttpResponse {
        try {
            $result = $this->platform->completeDeepLinking(
                $request->string('zustand')->toString(),
                $request->string(DeepLinkingResponse::FORM_PARAMETER)->toString(),
            );
        } catch (LtiException $e) {
            return response()->view('learning.lti.error', ['reason' => $e->reason], 400);
        }

        return redirect()->route('learning.courses.units.edit', [
            'course' => Sqid::encode(LearningCourse::class, $result['unit']->learning_course_id),
            'unit' => $result['unit']->sqid,
        ]);
    }

    /**
     * @param  array<mixed>  $input
     * @return array<string, string>
     */
    private static function stringFields(array $input): array {
        $fields = [];

        foreach ($input as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $fields[$name] = $value;
            }
        }

        return $fields;
    }
}
