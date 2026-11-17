<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldLayout.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Fields;

use App\Enums\Fields\FieldType;

/**
 * Gestaltungsregeln langer Formulare (Feature 203, MVP-1649/1651): wann ein
 * Formular in Abschnittskarten zerfällt, ab wann es eine Inhaltsleiste hat,
 * wann eine Auswahl als Knöpfe erscheint und wie breit ein Feld ist. Die
 * Grenzen stehen nur hier; `<x-field-form>` und `<x-field-input>` lesen sie.
 */
final class FieldLayout {
    /**
     * Auswahl und Mehrfachauswahl mit höchstens so vielen Möglichkeiten
     * erscheinen als Knöpfe, darüber als Auswahlliste — mehr Knöpfe machen
     * die Fläche unruhig.
     */
    public const int CHOICE_BUTTONS_MAX = 6;

    /** Bis zu so vielen Fragen bleibt ein Formular eine Karte. */
    public const int SINGLE_CARD_MAX_QUESTIONS = 6;

    /** Ab so vielen Abschnitten zeigt das Formular eine Inhaltsleiste. */
    public const int NAVIGATION_MIN_SECTIONS = 4;

    /** Auswahl als Knöpfe statt Auswahlliste? Fachtypen rendern sich selbst. */
    public static function choiceAsButtons(FieldDefinition $field): bool {
        return $field->extension === null
            && $field->type->needsOptions()
            && $field->options !== []
            && count($field->options) <= self::CHOICE_BUTTONS_MAX;
    }

    /**
     * Volle Breite statt Halbspalte: Freitext, Abschnitt, Anhänge,
     * Unterschrift, Mehrfachauswahl und Knopfreihen; Kurzes steht nebeneinander.
     */
    public static function fullWidth(FieldDefinition $field): bool {
        return match ($field->type) {
            FieldType::Textarea, FieldType::Section, FieldType::Photo, FieldType::File, FieldType::Signature, FieldType::Multichoice => true,
            FieldType::Choice => self::choiceAsButtons($field),
            FieldType::Scale => ((int) ($field->max ?? 5)) - ((int) ($field->min ?? 1)) >= 6,
            default => false,
        };
    }

    /** Anzahl der Fragen (Felder mit Wert, ohne Abschnitte). */
    public static function questionCount(FieldSchema $schema): int {
        $count = 0;
        foreach ($schema as $field) {
            if ($field->type->hasValue()) {
                $count++;
            }
        }

        return $count;
    }

    /** Langer Bogen: mehr Fragen, als eine Karte trägt — Abschnittskarten und großer Dialog. */
    public static function isLong(FieldSchema $schema): bool {
        return self::questionCount($schema) > self::SINGLE_CARD_MAX_QUESTIONS;
    }

    /** Abschnittskarten nur bei langen Bögen, die Abschnitte haben. */
    public static function usesCards(FieldSchema $schema): bool {
        if (! self::isLong($schema)) {
            return false;
        }
        foreach ($schema->sections() as $section) {
            if ($section['field'] !== null) {
                return true;
            }
        }

        return false;
    }

    public static function showsNavigation(FieldSchema $schema): bool {
        return self::usesCards($schema) && count($schema->sections()) >= self::NAVIGATION_MIN_SECTIONS;
    }
}
