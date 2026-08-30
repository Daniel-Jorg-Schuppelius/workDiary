<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningScormController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Enums\Learning\LearningUnitKind;
use App\Http\Controllers\Controller;
use App\Models\Learning\{LearningCourse, LearningEnrollment, LearningScormPackage, LearningUnit};
use App\Models\User;
use App\Services\Learning\{LearningScormService, LearningXapiService};
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, File, Gate};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * SCORM-Import, Player und xAPI-Endpunkt (Feature 149, MVP-743).
 *
 * **Sicherheitslage, offen benannt:** ein SCORM-Paket ist fremder Code, der
 * gleichursprünglich laufen muss — der Inhalt greift über
 * `window.parent.API` auf die Laufzeit zu. Ein `sandbox`-Attribut mit
 * `allow-same-origin` schützt daher nicht. Die Verteidigung liegt woanders:
 * der Extractor verweigert ausführbare Dateien und Pfad-Ausbrüche, die
 * Ausspielung setzt eine eigene, enge CSP ohne Netzwerkziele, und
 * importieren darf nur, wer den Kurs verantwortet.
 */
class LearningScormController extends Controller {
    public function __construct(
        private readonly LearningScormService $scorm,
        private readonly LearningXapiService $xapi,
    ) {}

    /** Autorenseite: Paket an eine Einheit hängen. */
    public function import(Request $request, LearningCourse $course, LearningUnit $unit): RedirectResponse {
        Gate::authorize('update', $course);
        abort_unless($unit->learning_course_id === $course->id, 404);
        abort_unless($unit->kind === LearningUnitKind::Scorm, 404);

        $validated = $request->validate([
            'package' => ['required', 'file', 'mimetypes:application/zip,application/x-zip-compressed,application/octet-stream', 'max:524288'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $validated['package'];

        $package = $this->scorm->import($unit, $file->getRealPath(), $this->actor());

        return redirect()
            ->route('learning.courses.units.edit', ['course' => $course->sqid, 'unit' => $unit->sqid])
            ->with('success', __('learning.scorm.imported', ['title' => $package->title]));
    }

    /** Player-Rahmen: lädt den Inhalt in einen Frame und stellt die Laufzeit bereit. */
    public function play(LearningEnrollment $enrollment, LearningUnit $unit): View {
        $package = $this->packageFor($enrollment, $unit);
        $state = $this->scorm->stateFor($package, $enrollment);

        return view('learning.my.scorm', [
            'enrollment' => $enrollment,
            'unit' => $unit,
            'package' => $package,
            'state' => $state,
            'launchUrl' => $this->launchUrl($enrollment, $unit, $package),
        ]);
    }

    /**
     * Datei aus dem entpackten Paket ausliefern.
     *
     * Der Pfad kommt aus dem Inhalt selbst — er wird nie zusammengesetzt,
     * ohne dass das Ergebnis wieder im Paketverzeichnis liegt.
     */
    public function asset(LearningEnrollment $enrollment, LearningUnit $unit, string $path = ''): BinaryFileResponse {
        $package = $this->packageFor($enrollment, $unit);

        $base = storage_path('app/' . $package->storage_path);
        $target = $path !== '' ? $base . '/' . $path : $base . '/' . (string) $package->launch_href;

        $real = realpath($target);
        $realBase = realpath($base);

        abort_if($real === false || $realBase === false, 404);
        abort_unless(str_starts_with($real, $realBase . DIRECTORY_SEPARATOR), 404);
        abort_unless(File::isFile($real), 404);

        $response = response()->file($real);

        // Eigene, enge CSP: der Inhalt darf inline skripten (fast jedes
        // Autorenwerkzeug erzeugt das), aber nichts nach außen sprechen.
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self' data: blob:; script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
            . "style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; media-src 'self' data: blob:; "
            . "connect-src 'self'; frame-ancestors 'self'; form-action 'none'; base-uri 'none'"
        );

        return $response;
    }

    /** `LMSCommit`/`Commit` der Laufzeit. */
    public function commit(Request $request, LearningEnrollment $enrollment, LearningUnit $unit): JsonResponse {
        $package = $this->packageFor($enrollment, $unit);

        $validated = $request->validate([
            'lesson_status' => ['nullable', 'string', 'max:40'],
            'success_status' => ['nullable', 'string', 'max:40'],
            'score_scaled' => ['nullable', 'numeric', 'between:-1,1'],
            'suspend_data' => ['nullable', 'string', 'max:64000'],
            'location' => ['nullable', 'string', 'max:1000'],
            'session_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ]);

        $state = $this->scorm->commit($package, $enrollment, $validated);

        return response()->json([
            'ok' => true,
            'passed' => $this->scorm->isPassed($package, $state),
        ]);
    }

    /**
     * xAPI-Statement aufnehmen.
     *
     * Bewusst schlank: eine Ablage plus Fortschritt aus eindeutigen Verben,
     * kein vollwertiges Learning Record Store.
     */
    public function xapi(Request $request, LearningEnrollment $enrollment): JsonResponse {
        $this->authorizeOwn($enrollment);

        $request->validate([
            'statement' => ['required', 'array'],
            'statement.verb.id' => ['required', 'string', 'max:500'],
            'statement.object.id' => ['nullable', 'string', 'max:1000'],
            'statement.id' => ['nullable', 'string', 'max:64'],
        ]);

        // Absichtlich der Roh-Eingabewert: `validate()` gäbe nur die
        // geprüften Teilschlüssel zurück, und ein xAPI-Statement wird ganz
        // aufbewahrt — auch was wir nicht auswerten (z. B. `result`).
        /** @var array<string, mixed> $statement */
        $statement = (array) $request->input('statement', []);

        abort_if(strlen((string) json_encode($statement)) > 64000, 413);

        $record = $this->xapi->store($enrollment, $statement);

        return response()->json(['ok' => true, 'id' => $record->statement_id]);
    }

    /**
     * Einstiegsadresse des Inhalts.
     *
     * Bewusst zusammengesetzt statt über den Routen-Parameter: `route()`
     * kodiert Schrägstriche, dann würden relative Verweise im Paket
     * (`js/app.js`, `../bilder/a.png`) ins Leere zeigen.
     */
    private function launchUrl(LearningEnrollment $enrollment, LearningUnit $unit, LearningScormPackage $package): string {
        $base = rtrim(route('learning.my.scorm.asset', [
            'enrollment' => $enrollment->sqid,
            'unit' => $unit->sqid,
        ]), '/');

        $href = ltrim((string) $package->launch_href, '/');
        [$path, $query] = array_pad(explode('?', $href, 2), 2, null);

        $encoded = implode('/', array_map('rawurlencode', explode('/', (string) $path)));

        return $base . '/' . $encoded . ($query !== null && $query !== '' ? '?' . $query : '');
    }

    /** Paket zur eigenen Einschreibung — fremde Einschreibungen gibt es nicht. */
    private function packageFor(LearningEnrollment $enrollment, LearningUnit $unit): LearningScormPackage {
        $this->authorizeOwn($enrollment);

        abort_unless($unit->learning_course_id === $enrollment->learning_course_id, 404);

        $package = LearningScormPackage::query()->where('learning_unit_id', $unit->id)->first();

        abort_if($package === null, 404);

        return $package;
    }

    private function authorizeOwn(LearningEnrollment $enrollment): void {
        abort_unless($enrollment->user_id === $this->actor()->id, 404);
    }

    private function actor(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
