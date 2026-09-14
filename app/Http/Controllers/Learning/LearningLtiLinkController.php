<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningLtiLinkController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Enums\Learning\LearningUnitKind;
use App\Http\Controllers\Controller;
use App\Models\Learning\{LearningCourse, LearningLtiLink, LearningLtiTool, LearningUnit};
use App\Models\User;
use App\Services\Learning\LearningLtiPlatformService;
use App\Support\Sqid;
use ELearningToolkit\Lti\LtiException;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\ValidationException;

/** LTI-Einheiten in der Autorenansicht (Feature 149): Inhalt beim Tool auswählen oder von Hand verknüpfen. */
final class LearningLtiLinkController extends Controller {
    public function __construct(private readonly LearningLtiPlatformService $platform) {}

    /** Inhalt beim Tool auswählen: Login-Anstoß für Deep Linking. */
    public function deepLinking(Request $request, LearningCourse $course, LearningUnit $unit): RedirectResponse {
        $this->guard($course, $unit);
        $tool = $this->tool($request);

        /** @var User $author */
        $author = Auth::user();

        try {
            return redirect()->away($this->platform->deepLinkingLoginUrl($unit, $tool, $author));
        } catch (LtiException $e) {
            throw ValidationException::withMessages(['tool' => (string) __('learning.errors.lti.failed', ['reason' => $e->reason])]);
        }
    }

    /** Von Hand verknüpfen — für Tools ohne Deep Linking. */
    public function store(Request $request, LearningCourse $course, LearningUnit $unit): RedirectResponse {
        $this->guard($course, $unit);
        $tool = $this->tool($request);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url:https,http', 'max:2000'],
            'custom' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->platform->saveLink(
            $unit,
            $tool,
            isset($validated['title']) ? (string) $validated['title'] : null,
            isset($validated['url']) ? (string) $validated['url'] : null,
            self::customParameters(isset($validated['custom']) ? (string) $validated['custom'] : ''),
        );

        return $this->backToEditor($course, $unit, 'learning.flash.lti_linked');
    }

    public function destroy(LearningCourse $course, LearningUnit $unit): RedirectResponse {
        $this->guard($course, $unit);

        LearningLtiLink::query()->where('learning_unit_id', $unit->id)->delete();

        return $this->backToEditor($course, $unit, 'learning.flash.lti_removed');
    }

    private function guard(LearningCourse $course, LearningUnit $unit): void {
        Gate::authorize('update', $course);
        abort_unless($unit->learning_course_id === $course->id, 404);
        abort_unless($unit->kind === LearningUnitKind::Lti, 404);
    }

    /** Aktives Tool der eigenen Organisation — die Abfrage läuft unter dem Org-Scope. */
    private function tool(Request $request): LearningLtiTool {
        $tool = LearningLtiTool::query()
            ->where('is_active', true)
            ->find(Sqid::decodeOrAbort(LearningLtiTool::class, $request->string('tool')->toString()));

        if (! $tool instanceof LearningLtiTool) {
            throw ValidationException::withMessages(['tool' => (string) __('validation.exists', ['attribute' => __('learning.field.lti_tool')])]);
        }

        return $tool;
    }

    private function backToEditor(LearningCourse $course, LearningUnit $unit, string $flashKey): RedirectResponse {
        return redirect()
            ->route('learning.courses.units.edit', ['course' => $course->sqid, 'unit' => $unit->sqid])
            ->with('success', __($flashKey));
    }

    /**
     * Eine Zeile je Parameter (`name=wert`); Zeilen ohne Namen oder Gleichheitszeichen fallen weg.
     *
     * @return array<string, string>
     */
    private static function customParameters(string $text): array {
        $custom = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            [$name, $value] = array_pad(explode('=', $line, 2), 2, null);
            $name = trim((string) $name);

            if ($name === '' || $value === null) {
                continue;
            }

            $custom[mb_substr($name, 0, 100)] = mb_substr(trim($value), 0, 1000);
        }

        return $custom;
    }
}
