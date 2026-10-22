<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningQuestionSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Learning\Import;

use App\Enums\Import\{ImportEntity, ImportErrorCode};
use App\Enums\Learning\LearningQuestionKind;
use App\Models\Learning\{LearningQuestion, LearningQuestionCategory};
use App\Models\Platform\Organization;
use App\Services\Import\{ImportOutcome, ValidationIssue};
use App\Services\Import\Specs\AbstractEntitySpec;
use App\Services\Learning\{LearningQuestionCatalogService, LearningQuestionEditorService};
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Fragen für den Fragenkatalog per Datei (MVP-1262). Die Antwortzeilen
 * stehen untereinander in einer Zelle und folgen der Syntax des
 * Frageneditors (`*` markiert richtig, `{n}` Punkte, `a = b` Zuordnung).
 * Dieselbe Frage (Art und Fragetext) wird beim zweiten Lauf übersprungen.
 */
class LearningQuestionSpec extends AbstractEntitySpec {
    private const PROMPT_MAX = 2000;

    private const TITLE_MAX = 180;

    public function __construct(
        private readonly LearningQuestionEditorService $editor,
        private readonly LearningQuestionCatalogService $catalog,
    ) {}

    public function entity(): ImportEntity {
        return ImportEntity::LearningQuestions;
    }

    public function columns(): array {
        return ['kind', 'prompt', 'options', 'title', 'category', 'points', 'explanation', 'hint'];
    }

    public function requiredColumns(): array {
        return ['kind', 'prompt'];
    }

    public function headerAliases(): array {
        return [
            'art' => 'kind', 'fragetyp' => 'kind', 'typ' => 'kind', 'type' => 'kind',
            'frage' => 'prompt', 'fragetext' => 'prompt', 'question' => 'prompt',
            'antworten' => 'options', 'optionen' => 'options', 'answers' => 'options', 'zeilen' => 'options',
            'titel' => 'title', 'kategorie' => 'category',
            'punkte' => 'points', 'erklärung' => 'explanation', 'erklaerung' => 'explanation', 'tipp' => 'hint',
        ];
    }

    public function normalize(array $row): array {
        $kind = $this->lowerOrNull($this->trimmedString($row['kind'] ?? null));

        return [
            'kind' => $kind !== null ? $this->kindFromLabel($kind) : null,
            'prompt' => $this->trimmedString($row['prompt'] ?? null),
            'options' => $this->trimmedString($row['options'] ?? null),
            'title' => $this->trimmedString($row['title'] ?? null),
            'category' => $this->trimmedString($row['category'] ?? null),
            'points' => ($raw = $this->trimmedString($row['points'] ?? null)) !== null && ctype_digit($raw) ? (int) $raw : ($raw === null ? 1 : null),
            'explanation' => $this->trimmedString($row['explanation'] ?? null),
            'hint' => $this->trimmedString($row['hint'] ?? null),
        ];
    }

    public function validateRow(array $row, Organization $organization): array {
        $issues = [];
        foreach (['kind', 'prompt'] as $field) {
            if (($row[$field] ?? null) === null) {
                $issues[] = $this->requiredIssue($field);
            }
        }
        $this->validateEnum($issues, $row, 'kind', LearningQuestionKind::class);
        $points = $row['points'] ?? null;
        if (! is_int($points) || $points < 1 || $points > 100) {
            $issues[] = $this->formatIssue('points', (string) __('import.error.learningQuestion.points'));
        }
        if (mb_strlen((string) ($row['prompt'] ?? '')) > self::PROMPT_MAX) {
            $issues[] = $this->tooLongIssue('prompt', self::PROMPT_MAX);
        }
        if (mb_strlen((string) ($row['title'] ?? '')) > self::TITLE_MAX) {
            $issues[] = $this->tooLongIssue('title', self::TITLE_MAX);
        }
        $kind = LearningQuestionKind::tryFrom((string) ($row['kind'] ?? ''));
        // Ein Hotspot braucht sein Bild — das kommt nicht über eine Tabelle.
        if ($kind?->needsImage() === true) {
            $issues[] = $this->formatIssue('kind', (string) __('import.error.learningQuestion.image'));
        }
        if ($kind !== null && $kind->needsOptions() && LearningQuestionEditorService::linesOf((string) ($row['options'] ?? '')) === []) {
            $issues[] = $this->requiredIssue('options');
        }

        return $issues;
    }

    public function upsert(array $row, Organization $organization): array {
        $kind = LearningQuestionKind::from((string) $row['kind']);
        $exists = LearningQuestion::query()
            ->where('organization_id', $organization->id)
            ->where('kind', $kind->value)
            ->where('prompt', (string) $row['prompt'])
            ->exists();
        if ($exists) {
            return [ImportOutcome::Skipped, null];
        }

        try {
            $this->editor->create((int) $organization->id, [
                'kind' => $kind->value,
                'prompt' => $row['prompt'],
                'title' => $row['title'],
                'category_id' => $this->categoryId($organization, $row['category']),
                'points' => $row['points'],
                'explanation' => $row['explanation'],
                'hint' => $row['hint'],
                'options' => $row['options'],
            ], null, null);

            return [ImportOutcome::Created, null];
        } catch (ValidationException $e) {
            return [ImportOutcome::Failed, new ValidationIssue(ImportErrorCode::Format, 'options', (string) collect($e->errors())->flatten()->first())];
        } catch (Throwable $e) {
            return [ImportOutcome::Failed, new ValidationIssue(ImportErrorCode::Persist, null, $e->getMessage())];
        }
    }

    /** Art als Schlüssel (`single`) oder als Bezeichnung in der Sprache der Oberfläche. */
    private function kindFromLabel(string $value): string {
        foreach (LearningQuestionKind::cases() as $case) {
            if ($value === $case->value || $value === mb_strtolower($case->label())) {
                return $case->value;
            }
        }

        return $value;
    }

    private function categoryId(Organization $organization, ?string $name): ?int {
        if ($name === null) {
            return null;
        }
        $category = LearningQuestionCategory::query()
            ->where('organization_id', $organization->id)
            ->where('name', $name)
            ->first();

        return (int) ($category ?? $this->catalog->createCategory($organization, $name))->id;
    }
}
