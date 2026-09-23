<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningQuestionEditorService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\Learning\LearningQuestionKind;
use App\Models\Attachments\Attachment;
use App\Models\Learning\{LearningQuestion, LearningQuiz};
use App\Models\Platform\User;
use App\Services\Attachments\FileAttacher;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};

/**
 * Fragen-Editor (Feature 149, MVP-738/779): die Zeilen-Syntax des
 * Prüfungseditors in Einstellungen und Antwortoptionen übersetzen — und
 * zurück, damit eine gespeicherte Frage im selben Textfeld bearbeitet werden
 * kann.
 *
 * Syntax je Art (eine Zeile je Eintrag):
 *  - Auswahl/Wahr-Falsch: `*` vorne markiert die richtige Option.
 *  - Sortieren: die Zeilen in der richtigen Reihenfolge.
 *  - Zuordnung: `links = rechts` je Paar.
 *  - Freitext: jede Zeile eine akzeptierte Lösung.
 *  - Lückentext: eine Zeile je Lücke, Alternativen mit `|`.
 *  - Bildmarkierung: `x,y,breite,höhe: Bezeichnung` in Prozent, `*` = richtig.
 *  - Matrix: `Zeile = Spalte`, dieselbe Spalte darf mehrfach vorkommen.
 *
 * Bearbeiten ersetzt die Optionen vollständig. Das ist unkritisch, weil jeder
 * Versuch gegen seinen eigenen `questions_snapshot` bewertet wird — eine
 * Änderung wirkt nur auf künftige Versuche.
 */
class LearningQuestionEditorService {
    public const ATTACHMENT_FOLDER = 'learning-questions';

    public function __construct(
        private readonly FileAttacher $attacher,
        private readonly LearningQuestionCatalogService $catalog,
    ) {}

    /**
     * Frage im Katalog anlegen und — wenn eine Prüfung übergeben wird — ans
     * Ende dieser Prüfung setzen (MVP-782).
     *
     * @param  array<string, mixed>  $data  validierte Editor-Felder (kind, prompt, title, category_id, explanation, points, options, partial_credit, case_sensitive)
     */
    public function create(int $organizationId, array $data, ?UploadedFile $image, ?User $uploader, ?LearningQuiz $quiz = null): LearningQuestion {
        $kind = LearningQuestionKind::from((string) $data['kind']);
        $lines = self::linesOf((string) ($data['options'] ?? ''));
        $parsed = $this->parse($kind, $data, $lines);

        return DB::transaction(function () use ($organizationId, $kind, $data, $parsed, $image, $uploader, $quiz): LearningQuestion {
            $question = LearningQuestion::query()->create([
                'organization_id' => $organizationId,
                'learning_question_category_id' => $data['category_id'] ?? null,
                'kind' => $kind->value,
                'title' => $this->titleOf($data),
                'prompt' => $data['prompt'],
                'explanation' => $data['explanation'] ?? null,
                'points' => (int) $data['points'],
                'position' => 0,
                'settings' => $parsed['settings'] !== [] ? $parsed['settings'] : null,
            ]);

            $this->replaceOptions($question, $parsed['options']);
            $this->attachImage($question, $kind, $image, $uploader);

            if ($quiz !== null) {
                $this->catalog->attach($quiz, $question);
            }

            return $question->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(LearningQuestion $question, array $data, ?UploadedFile $image, ?User $uploader): LearningQuestion {
        $kind = LearningQuestionKind::from((string) $data['kind']);
        $lines = self::linesOf((string) ($data['options'] ?? ''));
        $parsed = $this->parse($kind, $data, $lines);

        return DB::transaction(function () use ($question, $kind, $data, $parsed, $image, $uploader): LearningQuestion {
            $settings = $parsed['settings'];
            // Ein vorhandenes Bild bleibt, solange kein neues hochgeladen wird
            // und die Art weiterhin eines braucht.
            $existingImage = $question->settings['image_attachment_id'] ?? null;
            if ($kind->needsImage() && $existingImage !== null && ! $image instanceof UploadedFile) {
                $settings['image_attachment_id'] = $existingImage;
            }

            $question->update([
                'learning_question_category_id' => array_key_exists('category_id', $data) ? $data['category_id'] : $question->learning_question_category_id,
                'kind' => $kind->value,
                'title' => $this->titleOf($data),
                'prompt' => $data['prompt'],
                'explanation' => $data['explanation'] ?? null,
                'points' => (int) $data['points'],
                'settings' => $settings !== [] ? $settings : null,
            ]);

            $question->options()->delete();
            $this->replaceOptions($question, $parsed['options']);
            $this->attachImage($question, $kind, $image, $uploader);

            return $question->refresh();
        });
    }

    /** Kopie samt Optionen und Bild — im Katalog, und wenn gewünscht ans Ende einer Prüfung. */
    public function duplicate(LearningQuestion $question, ?User $actor, ?LearningQuiz $attachTo = null): LearningQuestion {
        return DB::transaction(function () use ($question, $actor, $attachTo): LearningQuestion {
            $settings = $question->settings ?? [];
            unset($settings['image_attachment_id']);

            $copy = LearningQuestion::query()->create([
                'organization_id' => $question->organization_id,
                'learning_question_category_id' => $question->learning_question_category_id,
                'kind' => $question->kind->value,
                'title' => $question->title,
                'prompt' => $question->prompt,
                'explanation' => $question->explanation,
                'points' => $question->points,
                'position' => 0,
                'settings' => $settings !== [] ? $settings : null,
            ]);

            foreach ($question->options()->orderBy('position')->get() as $option) {
                $copy->options()->create([
                    'organization_id' => $option->organization_id,
                    'label' => $option->label,
                    'is_correct' => $option->is_correct,
                    'match_key' => $option->match_key,
                    'position' => $option->position,
                ]);
            }

            $imageId = $question->settings['image_attachment_id'] ?? null;
            // Nur das Bild genau dieser Frage kopieren: die ID steht in frei
            // importierbaren Settings (Sicherheitsaudit 2026-09-17, files-idor-1).
            $image = $imageId !== null
                ? Attachment::query()
                    ->where('attachable_type', $question->getMorphClass())
                    ->where('attachable_id', $question->id)
                    ->find((int) $imageId)
                : null;
            if ($image !== null) {
                // Eigene Datei je Frage — sonst risse das Löschen der einen
                // Frage der anderen das Bild weg.
                $attachment = $this->attacher->storeContent(
                    $copy,
                    Storage::disk($image->disk)->get($image->path) ?? '',
                    $image->original_name,
                    $image->mime,
                    $actor?->id,
                    ['organization_id' => $copy->organization_id],
                    self::ATTACHMENT_FOLDER,
                );
                $settings['image_attachment_id'] = $attachment->id;
                $copy->forceFill(['settings' => $settings])->save();
            }

            if ($attachTo !== null) {
                $this->catalog->attach($attachTo, $copy);
            }

            return $copy->refresh();
        });
    }

    /** Reihenfolge innerhalb einer Prüfung (Position liegt in der Zwischentabelle). */
    public function move(LearningQuiz $quiz, LearningQuestion $question, int $direction): void {
        $this->catalog->move($quiz, $question, $direction);
    }

    /**
     * Umkehrung des Parsers: die gespeicherte Frage als Zeilenliste für das
     * Editor-Textfeld. parse(toLines(parse(x))) ergibt dasselbe wie parse(x).
     */
    public function toLines(LearningQuestion $question): string {
        $settings = $question->settings ?? [];
        $options = $question->options()->orderBy('position')->get();

        $lines = match ($question->kind) {
            LearningQuestionKind::Single,
            LearningQuestionKind::Multiple,
            LearningQuestionKind::TrueFalse => $options->map(fn ($o): string => ($o->is_correct ? '*' : '') . $o->label . ($o->points !== null ? ' {' . $o->points . '}' : ''))->all(),
            LearningQuestionKind::Sort => $options->map(fn ($o): string => (string) $o->label)->all(),
            LearningQuestionKind::Matching => $options
                ->groupBy('match_key')
                ->map(fn ($pair): string => implode(' = ', $pair->sortBy('position')->pluck('label')->all()))
                ->values()
                ->all(),
            LearningQuestionKind::ShortText => array_map('strval', (array) ($settings['answers'] ?? [])),
            LearningQuestionKind::Cloze => array_map(
                static fn ($gap): string => implode(' | ', array_map('strval', (array) $gap)),
                (array) ($settings['gaps'] ?? []),
            ),
            LearningQuestionKind::Hotspot => array_map(
                static fn (array $spot): string => sprintf(
                    '%s%s,%s,%s,%s: %s',
                    ! empty($spot['is_correct']) ? '*' : '',
                    NumberHelper::toUSFormat((float) ($spot['x'] ?? 0), 2, trimTrailingZeros: true),
                    NumberHelper::toUSFormat((float) ($spot['y'] ?? 0), 2, trimTrailingZeros: true),
                    NumberHelper::toUSFormat((float) ($spot['w'] ?? 0), 2, trimTrailingZeros: true),
                    NumberHelper::toUSFormat((float) ($spot['h'] ?? 0), 2, trimTrailingZeros: true),
                    (string) ($spot['label'] ?? ''),
                ),
                array_values((array) ($settings['hotspots'] ?? [])),
            ),
            LearningQuestionKind::Matrix => array_map(
                static fn (array $row): string => $row['label'] . ' = ' . (string) (($settings['columns'] ?? [])[(int) ($row['column'] ?? 0)] ?? ''),
                array_values((array) ($settings['rows'] ?? [])),
            ),
            LearningQuestionKind::Essay => [],
            LearningQuestionKind::Assessment => array_map('strval', (array) ($settings['scale'] ?? [])),
        };

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function titleOf(array $data): ?string {
        $title = trim((string) ($data['title'] ?? ''));

        return $title !== '' ? $title : null;
    }

    /**
     * @return list<string>
     */
    public static function linesOf(string $text): array {
        $lines = preg_split('/\R/', $text) ?: [];

        return array_values(array_filter(array_map('trim', $lines), static fn (string $l): bool => $l !== ''));
    }

    /**
     * Zeilen in Einstellungen und Optionen übersetzen — rein, ohne DB.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $lines
     * @return array{settings: array<string, mixed>, options: list<array{label: string, is_correct: bool, match_key: string|null}>}
     */
    public function parse(LearningQuestionKind $kind, array $data, array $lines): array {
        $settings = [];
        $options = [];

        // Tipp und getrennte Rückmeldungen (MVP-783) gelten für jede Art.
        foreach (['hint', 'feedback_correct', 'feedback_incorrect'] as $key) {
            $text = trim((string) ($data[$key] ?? ''));
            if ($text !== '') {
                $settings[$key] = $text;
            }
        }
        if ($kind === LearningQuestionKind::Multiple) {
            $settings['partial_credit'] = (bool) ($data['partial_credit'] ?? false);
        }
        // Selbsteinschätzung (MVP-793): eine Zeile je Stufe, keine richtige Antwort.
        if ($kind === LearningQuestionKind::Assessment) {
            $settings['scale'] = array_values(array_filter(array_map('trim', $lines), static fn (string $l): bool => $l !== ''));
        }
        // Aufsatz (MVP-793): Text, Datei oder beides.
        if ($kind === LearningQuestionKind::Essay) {
            $submission = (string) ($data['submission_kind'] ?? 'text');
            $settings['submission_kind'] = in_array($submission, ['text', 'upload', 'both'], true) ? $submission : 'text';
        }
        if (in_array($kind, [LearningQuestionKind::ShortText, LearningQuestionKind::Cloze], true)) {
            $settings['case_sensitive'] = (bool) ($data['case_sensitive'] ?? false);
        }
        if ($kind === LearningQuestionKind::ShortText) {
            // Freitext: jede Zeile ist eine akzeptierte Lösung.
            $settings['answers'] = array_map(static fn (string $line): string => ltrim($line, '*'), $lines);
        }
        if ($kind === LearningQuestionKind::Hotspot) {
            // Prozent statt Pixel, sonst hinge die richtige Antwort an der
            // Anzeigegröße.
            $settings['hotspots'] = $this->parseHotspots($lines);
        }
        if ($kind === LearningQuestionKind::Matrix) {
            $settings = array_merge($settings, $this->parseMatrix($lines));
        }
        if ($kind === LearningQuestionKind::Cloze) {
            // Der Bewerter liest `gaps` — hieße es hier `answers`, gäbe die
            // Frage immer null Punkte.
            $settings['gaps'] = array_map(
                static fn (string $line): array => array_values(array_filter(
                    array_map('trim', explode('|', $line)),
                    static fn (string $part): bool => $part !== ''
                )),
                $lines
            );
        }

        if ($kind === LearningQuestionKind::Matching) {
            // Beide Seiten teilen sich einen `match_key` — ohne den findet der
            // Bewerter kein einziges Paar. Zeilen ohne „=" werden übergangen.
            foreach ($lines as $index => $line) {
                $parts = array_map('trim', explode('=', $line, 2));
                if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
                    continue;
                }
                foreach ($parts as $label) {
                    $options[] = ['label' => $label, 'is_correct' => true, 'match_key' => 'p' . ($index + 1)];
                }
            }
        } elseif ($kind->needsOptions()) {
            foreach ($lines as $line) {
                // Punkte je Option (MVP-793): „Label {3}" — NULL = Fragepunkte gelten.
                $points = null;
                if (preg_match('/\s*\{(-?\d{1,4})\}\s*$/', $line, $m) === 1) {
                    $points = (int) $m[1];
                    $line = (string) preg_replace('/\s*\{-?\d{1,4}\}\s*$/', '', $line);
                }
                $options[] = [
                    'label' => trim(ltrim($line, '*')),
                    'is_correct' => str_starts_with($line, '*'),
                    'match_key' => null,
                    'points' => $points,
                ];
            }
        }

        return ['settings' => $settings, 'options' => $options];
    }

    /**
     * @param  list<array{label: string, is_correct: bool, match_key: string|null, points?: int|null}>  $options
     */
    private function replaceOptions(LearningQuestion $question, array $options): void {
        foreach ($options as $index => $option) {
            $question->options()->create([
                'organization_id' => $question->organization_id,
                'label' => $option['label'],
                'is_correct' => $option['is_correct'],
                'match_key' => $option['match_key'],
                'points' => $option['points'] ?? null,
                'position' => $index + 1,
            ]);
        }
    }

    private function attachImage(LearningQuestion $question, LearningQuestionKind $kind, ?UploadedFile $image, ?User $uploader): void {
        if (! $kind->needsImage() || ! $image instanceof UploadedFile) {
            return;
        }

        $attachment = $this->attacher->store(
            $question,
            $image,
            $uploader?->id,
            ['organization_id' => $question->organization_id],
            self::ATTACHMENT_FOLDER,
        );

        $settings = $question->settings ?? [];
        $settings['image_attachment_id'] = $attachment->id;
        $question->forceFill(['settings' => $settings])->save();
    }

    /**
     * Trefferflächen: „x,y,breite,höhe" in Prozent, `*` = richtig, optional
     * „: Bezeichnung". Auch falsche Flächen müssen möglich sein — sonst wäre
     * die Tastatur-Auswahlliste eine Liste ausschließlich richtiger Antworten.
     * Zeilen ohne vier Zahlen werden übergangen.
     *
     * @param  list<string>  $lines
     * @return list<array{x: float, y: float, w: float, h: float, is_correct: bool, label: string}>
     */
    private function parseHotspots(array $lines): array {
        $spots = [];

        foreach ($lines as $index => $line) {
            $correct = str_starts_with($line, '*');
            $rest = trim(ltrim($line, '*'));
            [$coords, $label] = array_pad(explode(':', $rest, 2), 2, null);
            $parts = array_map('trim', preg_split('/[,;]/', (string) $coords) ?: []);

            if (count($parts) < 4) {
                continue;
            }

            $values = array_map(static fn (string $p): float => (float) str_replace(',', '.', $p), array_slice($parts, 0, 4));

            if ($values[2] <= 0 || $values[3] <= 0) {
                continue;
            }

            $spots[] = [
                'x' => $values[0],
                'y' => $values[1],
                'w' => $values[2],
                'h' => $values[3],
                'is_correct' => $correct,
                'label' => trim((string) $label) !== '' ? trim((string) $label) : (string) ($index + 1),
            ];
        }

        return $spots;
    }

    /**
     * Matrix aus „Zeile = Spalte"-Zeilen; Spalten in Reihenfolge ihres ersten
     * Auftretens, dieselbe Spalte darf mehrfach genannt werden.
     *
     * @param  list<string>  $lines
     * @return array{rows: list<array{label: string, column: int}>, columns: list<string>}
     */
    private function parseMatrix(array $lines): array {
        $columns = [];
        $rows = [];

        foreach ($lines as $line) {
            $parts = array_map('trim', explode('=', $line, 2));

            if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
                continue;
            }

            $index = array_search($parts[1], $columns, true);

            if ($index === false) {
                $columns[] = $parts[1];
                $index = count($columns) - 1;
            }

            $rows[] = ['label' => $parts[0], 'column' => (int) $index];
        }

        return ['rows' => $rows, 'columns' => $columns];
    }
}
