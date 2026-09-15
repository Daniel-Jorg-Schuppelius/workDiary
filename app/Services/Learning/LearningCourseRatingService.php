<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningCourseRatingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use Illuminate\Support\Facades\DB;

/**
 * Sternewert je Kurs (Feature 149, MVP-794) aus der Kursfeedback-Umfrage:
 * Mittel aller Skalenantworten (1–5) der Einladungen mit Kursbezug. Erst ab
 * {@see self::MIN_RESPONSES} Antworten — darunter ließe sich der Wert auf
 * Einzelne zurückrechnen (dieselbe Schwelle wie die Kursanalyse).
 */
class LearningCourseRatingService {
    public const MIN_RESPONSES = 5;

    /**
     * @param  list<int>  $courseIds
     * @return array<int, array{average: float, count: int}>  Kurs-ID ⇒ Wert
     */
    public function ratingsFor(array $courseIds): array {
        if ($courseIds === []) {
            return [];
        }

        $rows = DB::table('survey_answers as a')
            ->join('survey_questions as q', 'q.id', '=', 'a.survey_question_id')
            ->join('survey_responses as r', 'r.id', '=', 'a.survey_response_id')
            ->join('survey_invitations as i', 'i.id', '=', 'r.survey_invitation_id')
            ->whereIn('i.learning_course_id', $courseIds)
            ->where('q.type', 'scale')
            ->whereNotNull('a.value_int')
            ->whereBetween('a.value_int', [1, 5])
            ->groupBy('i.learning_course_id')
            ->selectRaw('i.learning_course_id as course_id, AVG(a.value_int) as average, COUNT(DISTINCT r.id) as responses')
            ->get();

        $ratings = [];
        foreach ($rows as $row) {
            $count = (int) $row->responses;
            if ($count < self::MIN_RESPONSES) {
                continue;
            }
            $ratings[(int) $row->course_id] = ['average' => round((float) $row->average, 1), 'count' => $count];
        }

        return $ratings;
    }
}
