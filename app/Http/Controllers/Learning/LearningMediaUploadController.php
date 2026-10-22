<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningMediaUploadController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\Learning\{LearningCourse, LearningUnit};
use App\Services\Attachments\ChunkedUploadStore;
use Illuminate\Http\{JsonResponse, Request, UploadedFile};
use Illuminate\Support\Facades\{Auth, Gate};

/**
 * Teil-Upload großer Medien für den Einheiten-Editor (MVP-1265). Abgeschlossen
 * wird im Blockformular: `media_upload` trägt das Kennzeichen, `storeBlock()`
 * setzt die Teile zusammen und legt die Datei als Anhang ab.
 */
class LearningMediaUploadController extends Controller {
    public function __construct(private readonly ChunkedUploadStore $uploads) {}

    public static function scope(LearningUnit $unit): string {
        return 'learning-unit:' . $unit->id;
    }

    public function start(Request $request, LearningCourse $course, LearningUnit $unit): JsonResponse {
        $this->authorizeUnit($course, $unit);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'type' => ['nullable', 'string', 'max:120'],
        ]);

        return response()->json($this->uploads->start((int) Auth::id(), self::scope($unit), $data['name'], (int) $data['size'], $data['type'] ?? null));
    }

    public function status(LearningCourse $course, LearningUnit $unit, string $token): JsonResponse {
        $this->authorizeUnit($course, $unit);
        $received = $this->uploads->received((int) Auth::id(), self::scope($unit), $token);

        return response()->json(['token' => $token, ...$this->uploads->describe((int) Auth::id(), self::scope($unit), $token), 'received' => $received]);
    }

    public function chunk(Request $request, LearningCourse $course, LearningUnit $unit, string $token, int $index): JsonResponse {
        $this->authorizeUnit($course, $unit);
        $request->validate(['chunk' => ['required', 'file']]);
        /** @var UploadedFile $chunk */
        $chunk = $request->file('chunk');
        $this->uploads->put((int) Auth::id(), self::scope($unit), $token, $index, $chunk);

        return response()->json(['received' => $index]);
    }

    private function authorizeUnit(LearningCourse $course, LearningUnit $unit): void {
        Gate::authorize('update', $course);
        abort_unless($unit->learning_course_id === $course->id, 404);
    }
}
