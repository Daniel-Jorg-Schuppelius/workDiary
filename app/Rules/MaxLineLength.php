<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaxLineLength.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Rules;

use App\Services\Learning\LearningQuestionEditorService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Textfelder mit „eine Zeile je Eintrag": jede Zeile wird ein eigener
 * Datensatz, `max:` am Feld deckt dessen Spalte nicht ab.
 */
final class MaxLineLength implements ValidationRule {
    public function __construct(private readonly int $max) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void {
        foreach (LearningQuestionEditorService::linesOf(is_string($value) ? $value : '') as $line) {
            if (mb_strlen($line) > $this->max) {
                $fail((string) __('Jede Zeile darf höchstens :max Zeichen haben.', ['max' => $this->max]));

                return;
            }
        }
    }
}
