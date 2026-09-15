<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearnDashImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Learning\{LearningEnrollmentSource, LearningEnrollmentStatus, LearningQuestionKind, LearningUnitKind};
use App\Models\Learning\{LearningCourse, LearningEnrollment, LearningQuiz, LearningSection, LearningUnit};
use App\Models\{Organization, User};
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * LearnDash-Importer (Feature 149, MVP-792). Liest das Export-ZIP von
 * LearnDash 4.3+/5.x (`post_type_<key>.ld` als JSONL, `proquiz.ld`,
 * `user_activity.ld`, `user.ld`) und legt daraus Entwürfe an:
 *
 *  - Kurs → Kurs (Entwurf, neuer Code), Lektion → Abschnitt + Inhaltseinheit,
 *    Thema → Einheit im Abschnitt, Prüfung → Prüfungseinheit mit Einstellungen;
 *  - `post_content`-HTML → Blöcke (Überschrift/Text/Checkliste); Bilder werden
 *    als Platzhalter-Text markiert (ein Bild ohne Alternativtext ist kein
 *    Inhalt), Lektionsvideos als Videoblock, wenn der Host freigegeben ist;
 *  - ProQuiz-Fragen → Fragenkatalog mit Kategorie (`answer_data` ist
 *    JSON oder PHP-`serialize`, letzteres nur ohne Klassen);
 *  - abgeschlossene Kursaktivität → abgeschlossene Einschreibung
 *    `source = import` für per E-Mail gefundene Personen — **ohne**
 *    Zertifikat und **ohne** Rückfluss in den Unterweisungsnachweis: ein
 *    importierter Abschluss ist kein eigener Nachweis.
 *
 * Der Bericht nennt Angelegtes und Übergangenes mit Grund; `dryRun` rechnet
 * nur.
 */
class LearnDashImportService {
    /** ProQuiz-Kopf: „WPQ" + 10 Ziffern, danach Base64 (JSON oder serialize). */
    private const PROQUIZ_HEADER = 13;

    private const ANSWER_KINDS = [
        'single' => LearningQuestionKind::Single,
        'multiple' => LearningQuestionKind::Multiple,
        'free_answer' => LearningQuestionKind::ShortText,
        'sort_answer' => LearningQuestionKind::Sort,
        'matrix_sort_answer' => LearningQuestionKind::Matrix,
        'cloze_answer' => LearningQuestionKind::Cloze,
        'essay' => LearningQuestionKind::Essay,
        'assessment_answer' => LearningQuestionKind::Assessment,
    ];

    public function __construct(
        private readonly LearningCourseService $courses,
        private readonly LearningQuestionEditorService $questions,
        private readonly LearningQuestionCatalogService $catalog,
        private readonly LearningContentService $content,
    ) {}

    /**
     * @return array<string, mixed>  courses, sections, units, quizzes, questions, enrollments, skipped (type/ref/reason), dry_run
     */
    public function import(Organization $organization, string $zipPath, ?User $actor = null, bool $dryRun = false): array {
        $data = $this->read($zipPath);
        $report = ['courses' => 0, 'sections' => 0, 'units' => 0, 'quizzes' => 0, 'questions' => 0, 'enrollments' => 0, 'skipped' => [], 'dry_run' => $dryRun];

        if ($data['courses'] === []) {
            throw ValidationException::withMessages(['file' => (string) __('learning.errors.learndash_no_courses')]);
        }

        $run = function () use ($organization, $actor, $data, &$report): void {
            $courseMap = [];
            foreach ($data['courses'] as $post) {
                $course = $this->importCourse($organization, $actor, $post, $data, $report);
                $courseMap[(int) $post['wp_post']['ID']] = $course;
            }
            $this->importActivity($organization, $courseMap, $data, $report);
        };

        if ($dryRun) {
            DB::beginTransaction();
            try {
                $run();
            } finally {
                DB::rollBack();
            }
        } else {
            DB::transaction($run);
        }

        return $report;
    }

    // ── ZIP lesen ───────────────────────────────────────────────────────

    /**
     * @return array{courses: list<array<string, mixed>>, lessons: list<array<string, mixed>>, topics: list<array<string, mixed>>, quizzes: list<array<string, mixed>>, proquiz: array<int, array<string, mixed>>, activity: list<array<string, mixed>>, users: array<int, string>}
     */
    private function read(string $zipPath): array {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw ValidationException::withMessages(['file' => (string) __('learning.errors.learndash_zip')]);
        }

        $lines = function (string $name) use ($zip): array {
            $raw = $zip->getFromName($name);
            if ($raw === false) {
                // Ältere Exporte legen die Dateien in einem Unterordner ab.
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entry = (string) $zip->getNameIndex($i);
                    if (str_ends_with($entry, '/' . $name)) {
                        $raw = $zip->getFromIndex($i);
                        break;
                    }
                }
            }
            if ($raw === false || trim($raw) === '') {
                return [];
            }
            $rows = [];
            foreach (preg_split('/\R/', $raw) ?: [] as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $decoded = json_decode($line, true);
                if (is_array($decoded)) {
                    $rows[] = $decoded;
                }
            }

            return $rows;
        };

        $posts = fn (string $key): array => array_values(array_filter(
            $lines('post_type_' . $key . '.ld'),
            static fn (array $row): bool => is_array($row['wp_post'] ?? null) && isset($row['wp_post']['ID']),
        ));

        $proquiz = [];
        foreach ($lines('proquiz.ld') as $row) {
            $decoded = $this->decodeProQuiz((string) ($row['proquiz_data'] ?? ''));
            if ($decoded !== null && isset($row['proquiz_post_id'])) {
                $proquiz[(int) $row['proquiz_post_id']] = $decoded;
            }
        }

        $users = [];
        foreach ($lines('user.ld') as $row) {
            $user = is_array($row['user'] ?? null) ? $row['user'] : $row;
            $data = is_array($user['data'] ?? null) ? $user['data'] : $user;
            $id = (int) ($data['ID'] ?? $user['ID'] ?? 0);
            $email = strtolower(trim((string) ($data['user_email'] ?? $user['user_email'] ?? '')));
            if ($id > 0 && $email !== '') {
                $users[$id] = $email;
            }
        }

        // Alles lesen, BEVOR das Archiv geschlossen wird — die Closures
        // greifen sonst auf ein geschlossenes ZipArchive zu.
        $result = [
            'courses' => $posts('course'),
            'lessons' => $posts('lesson'),
            'topics' => $posts('topic'),
            'quizzes' => $posts('quiz'),
            'proquiz' => $proquiz,
            'activity' => $lines('user_activity.ld'),
            'users' => $users,
        ];
        $zip->close();

        return $result;
    }

    /**
     * ProQuiz-Export: Kopf abschneiden, Base64 lösen, JSON — oder (Legacy)
     * PHP-serialize **ohne** Klassen, sonst wäre der Import ein Deserialisierungs-Einfallstor.
     *
     * @return array<string, mixed>|null
     */
    private function decodeProQuiz(string $payload): ?array {
        if (strlen($payload) <= self::PROQUIZ_HEADER || ! str_starts_with($payload, 'WPQ')) {
            return null;
        }
        $raw = base64_decode(substr($payload, self::PROQUIZ_HEADER), true);
        if ($raw === false) {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            $decoded = @unserialize($raw, ['allowed_classes' => false]);
        }

        return is_array($decoded) ? $decoded : null;
    }

    // ── Kurs ────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $post
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $report
     */
    private function importCourse(Organization $organization, ?User $actor, array $post, array $data, array &$report): LearningCourse {
        $wp = $post['wp_post'];
        $courseId = (int) $wp['ID'];

        $course = $this->courses->createCourse($organization, $actor, [
            'title' => $this->title($wp['post_title'] ?? null, 'Kurs ' . $courseId),
            'description' => $this->plain((string) ($wp['post_excerpt'] ?? '')) ?: $this->plain((string) ($wp['post_content'] ?? '')),
        ]);
        $report['courses']++;

        $lessons = $this->children($data['lessons'], 'course_id', $courseId);
        $topicsByLesson = [];
        foreach ($this->children($data['topics'], 'course_id', $courseId) as $topic) {
            $topicsByLesson[(int) $this->meta($topic, 'lesson_id', 0)][] = $topic;
        }
        $quizzesByParent = ['course' => [], 'lesson' => []];
        foreach ($this->children($data['quizzes'], 'course_id', $courseId) as $quiz) {
            $lessonId = (int) $this->meta($quiz, 'lesson_id', 0);
            $quizzesByParent[$lessonId > 0 ? 'lesson' : 'course'][$lessonId][] = $quiz;
        }

        foreach ($lessons as $lesson) {
            $lessonId = (int) $lesson['wp_post']['ID'];
            $section = $this->courses->addSection($course, [
                'title' => $this->title($lesson['wp_post']['post_title'] ?? null, 'Lektion ' . $lessonId),
            ]);
            $report['sections']++;

            $this->addContentUnit($organization, $course, $section, $lesson, $report);
            foreach ($topicsByLesson[$lessonId] ?? [] as $topic) {
                $this->addContentUnit($organization, $course, $section, $topic, $report);
            }
            foreach ($quizzesByParent['lesson'][$lessonId] ?? [] as $quiz) {
                $this->addQuizUnit($organization, $actor, $course, $section, $quiz, $data, $report);
            }
        }
        foreach ($quizzesByParent['course'][0] ?? [] as $quiz) {
            $this->addQuizUnit($organization, $actor, $course, null, $quiz, $data, $report);
        }

        return $course;
    }

    /**
     * @param  array<string, mixed>  $post
     * @param  array<string, mixed>  $report
     */
    private function addContentUnit(Organization $organization, LearningCourse $course, ?LearningSection $section, array $post, array &$report): LearningUnit {
        $wp = $post['wp_post'];
        $blocks = $this->blocksFromHtml((string) ($wp['post_content'] ?? ''), (string) ($wp['post_title'] ?? ''), $report);

        // Lektionsvideo: nur mit freigegebenem Host, sonst bleibt die Adresse als Text stehen.
        $settings = $this->settings($post, '_sfwd-lessons') ?: $this->settings($post, '_sfwd-topic');
        $videoUrl = trim((string) ($settings['sfwd-lessons_lesson_video_url'] ?? $settings['sfwd-topic_lesson_video_url'] ?? ''));
        if ($videoUrl !== '' && filter_var($videoUrl, FILTER_VALIDATE_URL)) {
            $host = mb_strtolower((string) parse_url($videoUrl, PHP_URL_HOST));
            $allowed = array_map('mb_strtolower', $this->content->allowedHosts($organization));
            if (in_array($host, $allowed, true)) {
                array_unshift($blocks, ['type' => 'video', 'url' => $videoUrl]);
            } else {
                array_unshift($blocks, ['type' => 'text', 'text' => (string) __('learning.import.video_host_blocked', ['url' => $videoUrl])]);
                $report['skipped'][] = ['type' => 'video', 'ref' => $videoUrl, 'reason' => 'host_not_allowed'];
            }
        }

        $unit = $this->courses->addUnit($course, [
            'title' => $this->title($wp['post_title'] ?? null, 'Einheit ' . (int) $wp['ID']),
            'section' => $section,
            'content' => $blocks,
        ]);
        $report['units']++;

        return $unit;
    }

    /**
     * @param  array<string, mixed>  $post
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $report
     */
    private function addQuizUnit(Organization $organization, ?User $actor, LearningCourse $course, ?LearningSection $section, array $post, array $data, array &$report): void {
        $wp = $post['wp_post'];
        $quizPostId = (int) $wp['ID'];
        $pro = $data['proquiz'][$quizPostId] ?? null;
        $master = null;
        if (is_array($pro) && is_array($pro['master'] ?? null)) {
            $master = array_values($pro['master'])[0] ?? null;
        }
        $settings = $this->settings($post, '_sfwd-quiz');

        $unit = $this->courses->addUnit($course, [
            'title' => $this->title($wp['post_title'] ?? null, 'Prüfung ' . $quizPostId),
            'section' => $section,
            'kind' => LearningUnitKind::Quiz->value,
        ]);
        $report['units']++;

        $repeats = $settings['sfwd-quiz_repeats'] ?? null;
        $timeLimit = (int) ($master['_timeLimit'] ?? 0);
        $subset = ! empty($master['_showMaxQuestion']) ? (int) ($master['_showMaxQuestionValue'] ?? 0) : 0;
        $modus = (int) ($master['_quizModus'] ?? 0);

        $quiz = LearningQuiz::query()->create([
            'organization_id' => $organization->id,
            'learning_unit_id' => $unit->id,
            'title' => (string) $unit->title,
            'description' => $this->plain((string) ($master['_text'] ?? '')) ?: null,
            'pass_percent' => max(1, min(100, (int) round((float) ($settings['sfwd-quiz_passingpercentage'] ?? 80)))),
            'time_limit_minutes' => $timeLimit > 0 ? max(1, (int) ceil($timeLimit / 60)) : null,
            // LearnDash: leer = unbegrenzt, n = n Wiederholungen ⇒ n + 1 Versuche.
            'max_attempts' => $repeats === null || $repeats === '' ? 0 : max(1, (int) $repeats + 1),
            'retry_wait_hours' => 0,
            'questions_per_attempt' => $subset > 0 ? $subset : null,
            'shuffle_questions' => ! empty($master['_questionRandom']),
            'shuffle_answers' => ! empty($master['_answerRandom']),
            'feedback_mode' => 'end',
            'show_solutions' => ! empty($master['_showReviewQuestion']),
            'display_mode' => $modus === 3 ? 'single' : 'all',
            'allow_back' => $modus !== 3 || empty($master['_forcingQuestionSolve']),
            'allow_skip' => empty($master['_skipQuestionDisabled']),
            'require_all_answered' => ! empty($master['_forcingQuestionSolve']),
        ]);
        $report['quizzes']++;

        if ($master === null) {
            $report['skipped'][] = ['type' => 'quiz', 'ref' => (string) $unit->title, 'reason' => 'no_proquiz_data'];

            return;
        }

        $questionSets = is_array($pro['question'] ?? null) ? $pro['question'] : [];
        $questions = array_values($questionSets)[0] ?? [];
        foreach (is_array($questions) ? $questions : [] as $question) {
            if (! is_array($question)) {
                continue;
            }
            $this->importQuestion($organization, $actor, $quiz, $question, $report);
        }
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>  $report
     */
    private function importQuestion(Organization $organization, ?User $actor, LearningQuiz $quiz, array $question, array &$report): void {
        $type = (string) ($question['_answerType'] ?? 'single');
        $kind = self::ANSWER_KINDS[$type] ?? null;
        $title = $this->plain((string) ($question['_title'] ?? ''));
        if ($kind === null) {
            $report['skipped'][] = ['type' => 'question', 'ref' => $title, 'reason' => 'unsupported_type:' . $type];

            return;
        }

        $answers = [];
        foreach (is_array($question['_answerData'] ?? null) ? $question['_answerData'] : [] as $answer) {
            if (is_array($answer)) {
                $answers[] = $answer;
            }
        }

        $lines = $this->optionLines($kind, $question, $answers);
        if ($kind !== LearningQuestionKind::Essay && $lines === []) {
            $report['skipped'][] = ['type' => 'question', 'ref' => $title, 'reason' => 'no_answers'];

            return;
        }

        $categoryName = trim((string) ($question['_categoryName'] ?? ''));
        $category = $categoryName !== '' ? $this->catalog->categoryByName($organization, $categoryName) : null;

        $this->questions->create((int) $organization->id, [
            'kind' => $kind->value,
            'title' => $title !== '' ? mb_substr($title, 0, 180) : null,
            'prompt' => $this->plain((string) ($question['_question'] ?? '')) ?: ($title !== '' ? $title : 'Frage'),
            'points' => max(1, (int) ($question['_points'] ?? 1)),
            'options' => implode("\n", $lines),
            'category_id' => $category?->id,
            'hint' => ! empty($question['_tipEnabled']) ? $this->plain((string) ($question['_tipMsg'] ?? '')) : null,
            'feedback_correct' => $this->plain((string) ($question['_correctMsg'] ?? '')) ?: null,
            'feedback_incorrect' => ! empty($question['_correctSameText']) ? null : ($this->plain((string) ($question['_incorrectMsg'] ?? '')) ?: null),
            'partial_credit' => ! empty($question['_answerPointsActivated']),
        ], null, $actor, $quiz);
        $report['questions']++;
    }

    /**
     * Antworten in die Zeilensyntax des Frageneditors: `*` = richtig,
     * Sortierung in Reihenfolge, Matrix „Zeile = Spalte", Lückentext
     * `{[a][b]}` → `a|b`, Freitext eine Lösung je Zeile.
     *
     * @param  array<string, mixed>  $question
     * @param  list<array<string, mixed>>  $answers
     * @return list<string>
     */
    private function optionLines(LearningQuestionKind $kind, array $question, array $answers): array {
        $lines = [];
        switch ($kind) {
            case LearningQuestionKind::Single:
            case LearningQuestionKind::Multiple:
                foreach ($answers as $answer) {
                    $label = $this->plain((string) ($answer['_answer'] ?? ''));
                    if ($label !== '') {
                        $lines[] = (! empty($answer['_correct']) ? '*' : '') . $label;
                    }
                }
                break;
            case LearningQuestionKind::Sort:
                foreach ($answers as $answer) {
                    $label = $this->plain((string) ($answer['_answer'] ?? ''));
                    if ($label !== '') {
                        $lines[] = $label;
                    }
                }
                break;
            case LearningQuestionKind::Matrix:
                foreach ($answers as $answer) {
                    $row = $this->plain((string) ($answer['_sortString'] ?? ''));
                    $column = $this->plain((string) ($answer['_answer'] ?? ''));
                    if ($row !== '' && $column !== '') {
                        $lines[] = $row . ' = ' . $column;
                    }
                }
                break;
            case LearningQuestionKind::ShortText:
                foreach ($answers as $answer) {
                    foreach (preg_split('/\R/', (string) ($answer['_answer'] ?? '')) ?: [] as $solution) {
                        $solution = $this->plain($solution);
                        if ($solution !== '') {
                            $lines[] = $solution;
                        }
                    }
                }
                break;
            case LearningQuestionKind::Assessment:
                // LearnDash: „{ [1] [2] [3] }" — eine Stufe je Klammer.
                $text = (string) ($answers[0]['_answer'] ?? '');
                preg_match_all('/\[(.*?)\]/s', $text, $levels);
                foreach ($levels[1] as $level) {
                    $label = $this->plain($level);
                    if ($label !== '') {
                        $lines[] = $label;
                    }
                }
                break;
            case LearningQuestionKind::Cloze:
                $text = (string) ($answers[0]['_answer'] ?? $question['_question'] ?? '');
                if (preg_match_all('/\{(.*?)\}/s', $text, $gaps) > 0) {
                    foreach ($gaps[1] as $gap) {
                        preg_match_all('/\[(.*?)\]/s', $gap, $alternatives);
                        $parts = array_values(array_filter(array_map(fn (string $a): string => $this->plain($a), $alternatives[1])));
                        if ($parts === []) {
                            $parts = [$this->plain($gap)];
                        }
                        $lines[] = implode('|', $parts);
                    }
                }
                break;
            default:
                break;
        }

        return $lines;
    }

    // ── Aktivität ───────────────────────────────────────────────────────

    /**
     * @param  array<int, LearningCourse>  $courseMap
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $report
     */
    private function importActivity(Organization $organization, array $courseMap, array $data, array &$report): void {
        $seen = [];
        foreach ($data['activity'] as $row) {
            if ((string) ($row['activity_type'] ?? '') !== 'course' || empty($row['activity_status'])) {
                continue;
            }
            $course = $courseMap[(int) ($row['course_id'] ?? $row['post_id'] ?? 0)] ?? null;
            $email = $data['users'][(int) ($row['user_id'] ?? 0)] ?? null;
            if ($course === null || $email === null) {
                $report['skipped'][] = ['type' => 'activity', 'ref' => (string) ($row['user_id'] ?? '?'), 'reason' => $course === null ? 'course_unknown' : 'user_email_unknown'];

                continue;
            }
            $user = User::query()->where('organization_id', $organization->id)->whereRaw('LOWER(email) = ?', [$email])->first();
            if ($user === null) {
                $report['skipped'][] = ['type' => 'activity', 'ref' => $email, 'reason' => 'user_not_found'];

                continue;
            }
            $key = $course->id . ':' . $user->id;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $completedAt = ! empty($row['activity_completed']) ? Carbon::createFromTimestamp((int) $row['activity_completed']) : Carbon::now();
            // Bewusst KEIN Weg über completeIfDone(): kein Zertifikat, kein
            // Unterweisungsnachweis, keine Qualifikation — importiert ist
            // dokumentiert, nicht nachgewiesen.
            LearningEnrollment::query()->create([
                'organization_id' => $organization->id,
                'learning_course_id' => $course->id,
                'user_id' => $user->id,
                'status' => LearningEnrollmentStatus::Completed->value,
                'source' => LearningEnrollmentSource::Import->value,
                'started_at' => ! empty($row['activity_started']) ? Carbon::createFromTimestamp((int) $row['activity_started']) : $completedAt,
                'completed_at' => $completedAt,
            ]);
            $report['enrollments']++;
        }
    }

    // ── Helfer ──────────────────────────────────────────────────────────

    /**
     * HTML → Blöcke: Überschriften, Absätze, Listen; Bilder als markierter
     * Platzhalter (Alternativtext-Pflicht, Medien werden nicht übernommen).
     *
     * @param  array<string, mixed>  $report
     * @return list<array<string, mixed>>
     */
    private function blocksFromHtml(string $html, string $ref, array &$report): array {
        $blocks = [];
        if (trim($html) === '') {
            return $blocks;
        }

        // Gutenberg-Kommentare und Shortcodes tragen keinen Stoff —
        // vor dem Bild-Platzhalter, der selbst in eckigen Klammern steht.
        $html = (string) preg_replace('/<!--.*?-->/s', '', $html);
        $html = (string) preg_replace('/\[[^\]]+\]/', '', $html);

        // Bilder: Platzhalter statt Bildblock — ein Bild ohne Alternativtext
        // ist für Menschen, die es nicht sehen können, nicht vorhanden.
        $html = (string) preg_replace_callback('/<img[^>]*>/i', function (array $m) use (&$report, $ref): string {
            preg_match('/alt=["\']([^"\']*)["\']/i', $m[0], $alt);
            preg_match('/src=["\']([^"\']*)["\']/i', $m[0], $src);
            $report['skipped'][] = ['type' => 'image', 'ref' => $ref, 'reason' => 'media_not_imported:' . basename((string) ($src[1] ?? ''))];

            return '<p>' . e((string) __('learning.import.image_placeholder', ['alt' => trim((string) ($alt[1] ?? '')) ?: basename((string) ($src[1] ?? ''))])) . '</p>';
        }, $html);

        $pattern = '/<(h[1-6]|p|ul|ol|div|blockquote|pre)\b[^>]*>(.*?)<\/\1>/is';
        $rest = $html;
        while (preg_match($pattern, $rest, $m, PREG_OFFSET_CAPTURE) === 1) {
            $before = substr($rest, 0, (int) $m[0][1]);
            $this->pushText($blocks, $before);
            $tag = strtolower($m[1][0]);
            $inner = $m[2][0];
            if ($tag[0] === 'h') {
                $text = $this->plain($inner);
                if ($text !== '') {
                    $blocks[] = ['type' => 'heading', 'text' => $text];
                }
            } elseif ($tag === 'ul' || $tag === 'ol') {
                preg_match_all('/<li\b[^>]*>(.*?)<\/li>/is', $inner, $items);
                $list = array_values(array_filter(array_map(fn (string $i): string => $this->plain($i), $items[1])));
                if ($list !== []) {
                    $blocks[] = ['type' => 'checklist', 'items' => $list];
                }
            } else {
                $this->pushText($blocks, $inner);
            }
            $rest = substr($rest, (int) $m[0][1] + strlen($m[0][0]));
        }
        $this->pushText($blocks, $rest);

        return $blocks;
    }

    /** @param  list<array<string, mixed>>  $blocks */
    private function pushText(array &$blocks, string $html): void {
        $text = $this->plain($html);
        if ($text !== '') {
            $blocks[] = ['type' => 'text', 'text' => $text];
        }
    }

    private function plain(string $html): string {
        $text = strip_tags((string) preg_replace('/<br\s*\/?>/i', "\n", $html));

        return trim(StringHelper::normalizeWhitespace(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function title(mixed $raw, string $fallback): string {
        $title = $this->plain((string) $raw);

        return $title !== '' ? mb_substr($title, 0, 180) : $fallback;
    }

    /**
     * Beiträge, deren Meta `$key` auf `$id` zeigt (LearnDash schreibt
     * course_id/lesson_id als Meta), sortiert nach `menu_order`, dann ID.
     *
     * @param  list<array<string, mixed>>  $posts
     * @return list<array<string, mixed>>
     */
    private function children(array $posts, string $key, int $id): array {
        $matches = array_values(array_filter($posts, fn (array $post): bool => (int) $this->meta($post, $key, 0) === $id));
        usort($matches, static fn (array $a, array $b): int => [(int) ($a['wp_post']['menu_order'] ?? 0), (int) $a['wp_post']['ID']] <=> [(int) ($b['wp_post']['menu_order'] ?? 0), (int) $b['wp_post']['ID']]);

        return $matches;
    }

    /** @param  array<string, mixed>  $post */
    private function meta(array $post, string $key, mixed $default): mixed {
        $meta = is_array($post['wp_post_meta'] ?? null) ? $post['wp_post_meta'] : [];
        $value = $meta[$key] ?? $default;
        // WordPress liefert Meta als Werteliste je Schlüssel (`['10']`);
        // ein assoziatives Array ist dagegen bereits der Wert (Einstellungen).
        if (is_array($value) && array_is_list($value)) {
            $value = $value[0] ?? $default;
        }

        return $value;
    }

    /**
     * LearnDash-Einstellungen (`_sfwd-quiz`, `_sfwd-lessons`) — als Array oder
     * serialisiert (ohne Klassen).
     *
     * @param  array<string, mixed>  $post
     * @return array<string, mixed>
     */
    private function settings(array $post, string $key): array {
        $value = $this->meta($post, $key, null);
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : @unserialize($value, ['allowed_classes' => false]);
        }

        return is_array($value) ? $value : [];
    }
}
