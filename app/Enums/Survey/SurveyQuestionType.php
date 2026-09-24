<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurveyQuestionType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Survey;

use App\Enums\Fields\Contracts\FieldTyped;
use App\Enums\Fields\FieldType;

/**
 * Fragetypen eines Fragebogens (Feature 090): NPS 0–10, Skala 1–5, Auswahl,
 * Freitext — erfasst über den Feldschema-Baustein (MVP-867).
 */
enum SurveyQuestionType: string implements FieldTyped {
    case Nps = 'nps';
    case Scale = 'scale';
    case Choice = 'choice';
    case Text = 'text';

    public function fieldType(): FieldType {
        return match ($this) {
            self::Nps, self::Scale => FieldType::Scale,
            self::Choice => FieldType::Choice,
            self::Text => FieldType::Textarea,
        };
    }

    public function fieldExtension(): ?string {
        return null;
    }

    /** @return array{0: int, 1: int}|null Wertebereich der Skala */
    public function bounds(): ?array {
        return match ($this) {
            self::Nps => [0, 10],
            self::Scale => [1, 5],
            default => null,
        };
    }

    /** @return list<string> */
    public static function values(): array {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
