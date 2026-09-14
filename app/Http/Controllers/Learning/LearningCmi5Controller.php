<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCmi5Controller.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Enums\Learning\LearningUnitKind;
use App\Http\Controllers\Controller;
use App\Models\Learning\{LearningCourse, LearningUnit};
use App\Models\User;
use App\Services\Learning\LearningCmi5Service;
use Illuminate\Http\{RedirectResponse, Request, UploadedFile};
use Illuminate\Support\Facades\{Auth, Gate};

/** cmi5-Kurse in der Autorenansicht (Feature 149). */
final class LearningCmi5Controller extends Controller {
    public function __construct(private readonly LearningCmi5Service $cmi5) {}

    public function import(Request $request, LearningCourse $course, LearningUnit $unit): RedirectResponse {
        Gate::authorize('update', $course);
        abort_unless($unit->learning_course_id === $course->id, 404);
        abort_unless($unit->kind === LearningUnitKind::Cmi5, 404);

        $validated = $request->validate([
            'package' => ['required', 'file', 'mimes:zip,xml', 'max:524288'],
        ]);

        /** @var UploadedFile $file */
        $file = $validated['package'];
        /** @var User $actor */
        $actor = Auth::user();

        $package = $this->cmi5->import($unit, (string) $file->getRealPath(), $file->getClientOriginalName(), $actor);

        return redirect()
            ->route('learning.courses.units.edit', ['course' => $course->sqid, 'unit' => $unit->sqid])
            ->with('success', __('learning.cmi5.imported', ['title' => $package->title]));
    }
}
