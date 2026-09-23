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
use App\Models\Learning\{LearningCmi5Package, LearningCmi5Unit, LearningCourse, LearningEnrollment, LearningUnit};
use App\Models\Platform\User;
use App\Services\Learning\{LearningCmi5LaunchService, LearningCmi5Service, ScormContentToken, ScormPackageFiles};
use App\Support\Learning\ScormContentHost;
use ELearningToolkit\Cmi5\Cmi5Exception;
use ELearningToolkit\Scorm\LaunchPath;
use Illuminate\Http\{RedirectResponse, Request, UploadedFile};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** cmi5-Kurse: Import in der Autorenansicht, Start und Auslieferung für Lernende (Feature 149). */
final class LearningCmi5Controller extends Controller {
    public function __construct(
        private readonly LearningCmi5Service $cmi5,
        private readonly LearningCmi5LaunchService $launches,
        private readonly ScormContentToken $contentTokens,
    ) {}

    public function import(Request $request, LearningCourse $course, LearningUnit $unit): RedirectResponse {
        Gate::authorize('update', $course);
        abort_unless($unit->learning_course_id === $course->id, 404);
        abort_unless($unit->kind === LearningUnitKind::Cmi5, 404);

        $validated = $request->validate([
            'package' => ['required', 'file', 'mimes:zip,xml', 'max:524288'],
        ]);

        /** @var UploadedFile $file */
        $file = $validated['package'];

        $package = $this->cmi5->import($unit, (string) $file->getRealPath(), $file->getClientOriginalName(), $this->learner());

        return redirect()
            ->route('learning.courses.units.edit', ['course' => $course->sqid, 'unit' => $unit->sqid])
            ->with('success', __('learning.cmi5.imported', ['title' => $package->title]));
    }

    /** AU starten: Sitzung und Startdaten anlegen, dann zur AU weiterleiten (cmi5 8.1). */
    public function launch(LearningEnrollment $enrollment, LearningUnit $unit, LearningCmi5Unit $au): RedirectResponse {
        $learner = $this->learner();
        $package = $this->packageFor($enrollment, $unit, $learner);

        abort_unless($au->learning_cmi5_package_id === $package->id, 404);

        try {
            $url = $this->launches->launch($enrollment, $learner, $au, $this->auUrl($enrollment, $unit, $package, $au), route('learning.my.show', $enrollment));
        } catch (Cmi5Exception $e) {
            throw ValidationException::withMessages(['cmi5' => (string) __('learning.errors.cmi5.' . $e->reason)]);
        }

        return redirect()->away($url);
    }

    /**
     * Datei einer paketinternen AU am Anwendungs-Ursprung — nur ohne eigenen
     * Inhalts-Host; solange das so läuft, warnt der Systemcheck.
     */
    public function asset(LearningEnrollment $enrollment, LearningUnit $unit, string $path = ''): BinaryFileResponse {
        abort_if(ScormContentHost::isConfigured(), 404);

        $package = $this->packageFor($enrollment, $unit, $this->learner());
        $file = ScormPackageFiles::absolutePath($package, $path);
        abort_if($file === null, 404);

        $response = response()->file($file);
        $response->headers->set('Content-Security-Policy', ScormPackageFiles::contentSecurityPolicy());

        return $response;
    }

    /**
     * Adresse der AU ohne Startparameter: extern wie angegeben, paketintern über den
     * Inhalts-Host — oder, solange es keinen gibt, über den Anwendungs-Ursprung.
     */
    private function auUrl(LearningEnrollment $enrollment, LearningUnit $unit, LearningCmi5Package $package, LearningCmi5Unit $au): string {
        if ($au->isExternal()) {
            return $au->url;
        }

        abort_if($package->storage_path === null, 404);

        // Das Fragment gehört nicht in den kodierten Pfad, sonst würde aus `#` ein `%23`.
        [$href, $fragment] = array_pad(explode('#', $au->url, 2), 2, null);
        $path = LaunchPath::encode((string) $href) . ($fragment !== null ? '#' . $fragment : '');
        $contentOrigin = ScormContentHost::origin();

        if ($contentOrigin !== null) {
            return $contentOrigin . '/cmi5/' . $this->contentTokens->issue($enrollment, $unit, $package) . '/inhalt/' . $path;
        }

        return rtrim(route('learning.my.cmi5.asset', ['enrollment' => $enrollment->sqid, 'unit' => $unit->sqid]), '/') . '/' . $path;
    }

    /** Kurs zur eigenen Einschreibung — fremde Einschreibungen gibt es nicht. */
    private function packageFor(LearningEnrollment $enrollment, LearningUnit $unit, User $learner): LearningCmi5Package {
        abort_unless($enrollment->user_id === $learner->id, 404);
        abort_unless($unit->learning_course_id === $enrollment->learning_course_id, 404);
        abort_unless($unit->kind === LearningUnitKind::Cmi5, 404);

        $package = LearningCmi5Package::query()->where('learning_unit_id', $unit->id)->first();
        abort_if($package === null, 404);

        return $package;
    }

    private function learner(): User {
        /** @var User $learner */
        $learner = Auth::user();

        return $learner;
    }
}
