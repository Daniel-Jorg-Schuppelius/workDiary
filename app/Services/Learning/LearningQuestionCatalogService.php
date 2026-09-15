<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningQuestionCatalogService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning;

use App\Models\Learning\{LearningQuestion, LearningQuestionCategory, LearningQuiz, LearningQuizDrawRule};
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Fragenkatalog (Feature 149, MVP-782) — einzige Schreibstelle für
 * Kategorien, Prüfungszuordnung und Ziehregeln.
 *
 * Regeln:
 *  1. Eine Frage gehört der Organisation; eine Prüfung ZEIGT auf sie. Dieselbe
 *     Frage darf in beliebig vielen Prüfungen stehen.
 *  2. Gelöscht wird nur, was in keiner Prüfung steht — sonst verlöre eine
 *     Prüfung still eine Frage. Aus einer Prüfung ENTFERNEN ist erlaubt und
 *     lässt die Katalogfrage stehen.
 *  3. Eine Kategorie verschwindet nur ohne Fragen und ohne Ziehregeln.
 *  4. Prüfungsakten (Snapshots) bleiben von alldem unberührt.
 */
class LearningQuestionCatalogService {
    // ── Kategorien ───────────────────────────────────────────────────────

    public function createCategory(Organization $organization, string $name): LearningQuestionCategory {
        $name = trim($name);
        $slug = $this->uniqueSlug($organization, $name);

        return LearningQuestionCategory::query()->create([
            'organization_id' => $organization->id,
            'name' => $name,
            'slug' => $slug,
            'position' => (int) LearningQuestionCategory::query()->where('organization_id', $organization->id)->max('position') + 1,
        ]);
    }

    public function renameCategory(LearningQuestionCategory $category, string $name): LearningQuestionCategory {
        $category->update(['name' => trim($name)]);

        return $category->refresh();
    }

    public function deleteCategory(LearningQuestionCategory $category): void {
        if ($category->questions()->exists() || $category->drawRules()->exists()) {
            throw ValidationException::withMessages([
                'category' => (string) __('learning.errors.category_in_use'),
            ]);
        }

        $category->delete();
    }

    /** Kategorie nach Name finden oder anlegen (Import, Seeder). */
    public function categoryByName(Organization $organization, string $name): LearningQuestionCategory {
        $existing = LearningQuestionCategory::query()
            ->where('organization_id', $organization->id)
            ->where('name', trim($name))
            ->first();

        return $existing ?? $this->createCategory($organization, $name);
    }

    // ── Zuordnung Prüfung ↔ Frage ────────────────────────────────────────

    /** Frage ans Ende der Prüfung setzen; doppelt ist ein No-Op. */
    public function attach(LearningQuiz $quiz, LearningQuestion $question): void {
        if ($question->organization_id !== $quiz->organization_id) {
            throw ValidationException::withMessages([
                'question' => (string) __('learning.errors.question_foreign'),
            ]);
        }

        DB::transaction(function () use ($quiz, $question): void {
            if ($quiz->questions()->whereKey($question->id)->exists()) {
                return;
            }

            $position = (int) DB::table('learning_quiz_question')
                ->where('learning_quiz_id', $quiz->id)
                ->max('position') + 1;

            $quiz->questions()->attach($question->id, [
                'organization_id' => $quiz->organization_id,
                'position' => $position,
            ]);
        });
    }

    /** Aus der Prüfung nehmen — die Katalogfrage bleibt. */
    public function detach(LearningQuiz $quiz, LearningQuestion $question): void {
        DB::transaction(function () use ($quiz, $question): void {
            $quiz->questions()->detach($question->id);
            $this->compactPositions($quiz);
        });
    }

    /** Position mit dem Nachbarn tauschen; ohne Nachbarn passiert nichts Halbes. */
    public function move(LearningQuiz $quiz, LearningQuestion $question, int $direction): void {
        DB::transaction(function () use ($quiz, $question, $direction): void {
            /** @var list<object{id: int, learning_question_id: int, position: int}> $rows */
            $rows = DB::table('learning_quiz_question')
                ->where('learning_quiz_id', $quiz->id)
                ->orderBy('position')
                ->lockForUpdate()
                ->get(['id', 'learning_question_id', 'position'])
                ->values()
                ->all();

            $index = null;
            foreach ($rows as $i => $row) {
                if ((int) $row->learning_question_id === $question->id) {
                    $index = $i;
                    break;
                }
            }
            $target = $index === null ? null : $index + ($direction < 0 ? -1 : 1);

            if ($index === null || $target === null || $target < 0 || $target >= count($rows)) {
                throw ValidationException::withMessages([
                    'question' => (string) __('learning.errors.neighbour_missing'),
                ]);
            }

            $own = $rows[$index];
            $other = $rows[$target];
            DB::table('learning_quiz_question')->where('id', $own->id)->update(['position' => $other->position]);
            DB::table('learning_quiz_question')->where('id', $other->id)->update(['position' => $own->position]);
        });
    }

    public function isInQuiz(LearningQuiz $quiz, LearningQuestion $question): bool {
        return $quiz->questions()->whereKey($question->id)->exists();
    }

    /** Löschen nur, wenn keine Prüfung mehr auf die Frage zeigt. */
    public function delete(LearningQuestion $question): void {
        if ($question->quizzes()->exists()) {
            throw ValidationException::withMessages([
                'question' => (string) __('learning.errors.question_in_use', [
                    'quizzes' => $question->quizzes()->pluck('title')->implode(', '),
                ]),
            ]);
        }

        $question->delete();
    }

    // ── Ziehregeln ───────────────────────────────────────────────────────

    public function setDrawRule(LearningQuiz $quiz, LearningQuestionCategory $category, int $count): LearningQuizDrawRule {
        if ($category->organization_id !== $quiz->organization_id) {
            throw ValidationException::withMessages([
                'category' => (string) __('learning.errors.question_foreign'),
            ]);
        }

        $available = LearningQuestion::query()
            ->where('organization_id', $quiz->organization_id)
            ->where('learning_question_category_id', $category->id)
            ->count();

        if ($count < 1 || $count > $available) {
            throw ValidationException::withMessages([
                'count' => (string) __('learning.errors.draw_rule_count', ['available' => $available]),
            ]);
        }

        return LearningQuizDrawRule::query()->updateOrCreate(
            ['learning_quiz_id' => $quiz->id, 'learning_question_category_id' => $category->id],
            ['organization_id' => $quiz->organization_id, 'count' => $count],
        );
    }

    public function removeDrawRule(LearningQuizDrawRule $rule): void {
        $rule->delete();
    }

    /**
     * Zufällige Katalogfragen gemäß Ziehregeln — ohne die festen Fragen der
     * Prüfung und ohne Doppelung zwischen Regeln. Fehlen Fragen, bricht der
     * Start mit Klartext ab: eine Prüfung mit stillschweigend weniger Fragen
     * als konfiguriert wäre ein anderes Prüfungsniveau.
     *
     * @param  list<int>  $excludeIds
     * @return list<LearningQuestion>
     */
    public function drawFor(LearningQuiz $quiz, array $excludeIds = []): array {
        $drawn = [];
        $taken = $excludeIds;

        foreach ($quiz->drawRules()->with('category')->get() as $rule) {
            $pool = LearningQuestion::query()
                ->with('options')
                ->where('organization_id', $quiz->organization_id)
                ->where('learning_question_category_id', $rule->learning_question_category_id)
                ->whereNotIn('id', $taken)
                ->inRandomOrder()
                ->limit($rule->count)
                ->get();

            if ($pool->count() < $rule->count) {
                throw ValidationException::withMessages([
                    'questions' => (string) __('learning.errors.draw_rule_short', [
                        'category' => (string) ($rule->category->name ?? ''),
                        'count' => $rule->count,
                        'available' => $pool->count(),
                    ]),
                ]);
            }

            foreach ($pool as $question) {
                $drawn[] = $question;
                $taken[] = $question->id;
            }
        }

        return $drawn;
    }

    private function compactPositions(LearningQuiz $quiz): void {
        $rows = DB::table('learning_quiz_question')
            ->where('learning_quiz_id', $quiz->id)
            ->orderBy('position')
            ->get(['id']);

        foreach ($rows->values() as $index => $row) {
            DB::table('learning_quiz_question')->where('id', $row->id)->update(['position' => $index + 1]);
        }
    }

    private function uniqueSlug(Organization $organization, string $name): string {
        $base = Str::slug($name) ?: 'kategorie';
        $slug = $base;
        $n = 2;

        while (LearningQuestionCategory::query()->where('organization_id', $organization->id)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $n++;
        }

        return $slug;
    }
}
