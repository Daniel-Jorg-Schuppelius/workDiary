<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReportsVideoProgress.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning\Concerns;

use App\Models\Attachments\Attachment;
use App\Models\Learning\{LearningEnrollment, LearningUnit};
use App\Services\Learning\LearningVideoProgressService;
use Illuminate\Http\{JsonResponse, Request};

/**
 * Meldung des Players über neu gesehene Videosekunden (Feature 149, MVP-1598) —
 * für „Meine Schulungen" und das Kundenportal gleich. Die Eigentümerprüfung
 * der Einschreibung leistet der aufrufende Controller.
 */
trait ReportsVideoProgress {
    private function recordVideoProgress(Request $request, LearningEnrollment $enrollment, LearningUnit $unit, Attachment $attachment): JsonResponse {
        abort_unless($unit->learning_course_id === $enrollment->learning_course_id, 404);
        abort_unless(
            $attachment->attachable_type === $unit->getMorphClass()
            && (int) $attachment->attachable_id === (int) $unit->id,
            404
        );
        // Abgeschlossen, abgelaufen oder noch gesperrt: es gibt nichts mehr anzurechnen.
        abort_if($enrollment->status->isFinal() || $enrollment->isAccessExpired() || ! $unit->isReleasedFor($enrollment), 409);

        $data = $request->validate([
            'watched_seconds' => ['required', 'integer', 'min:0', 'max:600'],
        ]);

        return response()->json(
            app(LearningVideoProgressService::class)->record($enrollment, $unit, $attachment, (int) $data['watched_seconds'])
        );
    }
}
