<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadIntakeVisibility.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Intake;

use App\Enums\Sales\IntakeSensitivity;
use App\Models\Platform\User;
use App\Models\Sales\LeadIntake;
use App\Services\Fields\{FieldSchema, FieldValues};
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Enge Sichtbarkeit in der Aufnahmeakte (Feature 200): Ausweisangaben und
 * -kopie (Recht `lead.intake.identity`) und Gesundheitsangaben
 * (`lead.intake.health`) sehen und schreiben nur Personen mit dem Recht —
 * unabhängig davon, ob sie Lead oder Kunde sehen. Eine Stelle für Bogen,
 * Speichern je Feld, Anhänge, PDF und Auskunft. Ohne Person (Hintergrund,
 * Ablage am Kunden) ist alles Enge verborgen.
 */
final class LeadIntakeVisibility {
    public function canSee(?Authenticatable $user, IntakeSensitivity $sensitivity): bool {
        if (! $user instanceof User) {
            return false;
        }

        return $user->can($sensitivity->permission()->value);
    }

    /** Darf die Person die Angabe (bzw. Unterlage) mit diesem Schlüssel sehen? */
    public function canSeeKey(LeadIntake $intake, ?Authenticatable $user, string $key): bool {
        $sensitivity = $intake->sensitiveMap()[$key] ?? null;

        return $sensitivity === null || $this->canSee($user, $sensitivity);
    }

    /**
     * Schlüssel, die der Person verborgen bleiben.
     *
     * @return array<string, IntakeSensitivity>
     */
    public function hiddenFor(LeadIntake $intake, ?Authenticatable $user): array {
        return array_filter($intake->sensitiveMap(), fn (IntakeSensitivity $sensitivity): bool => ! $this->canSee($user, $sensitivity));
    }

    /** Bogen ohne die verborgenen Felder. */
    public function schemaFor(LeadIntake $intake, ?Authenticatable $user): FieldSchema {
        $hidden = $this->hiddenFor($intake, $user);
        if ($hidden === []) {
            return $intake->fullSchema();
        }

        return new FieldSchema(array_values(array_filter($intake->fullSchema()->all(), static fn ($field): bool => ! isset($hidden[$field->key]))));
    }

    /** Werte ohne die verborgenen Angaben (auch ohne deren Ergänzungen). */
    public function valuesFor(LeadIntake $intake, ?Authenticatable $user): FieldValues {
        $values = $intake->fieldDocumentValues();
        $hidden = $this->hiddenFor($intake, $user);
        if ($hidden === []) {
            return $values;
        }
        $raw = array_diff_key($values->toArray(), $hidden);
        $notes = array_diff_key($values->notes(), $hidden);
        unset($raw[FieldValues::NOTES_KEY]);
        if ($notes !== []) {
            $raw[FieldValues::NOTES_KEY] = $notes;
        }

        return new FieldValues($raw);
    }

    /**
     * Verborgene, aber ausgefüllte Angaben je Art — für den Hinweis „n Angaben
     * mit eigenem Recht“ ohne Inhalt.
     *
     * @return array<string, int> Art → Anzahl
     */
    public function hiddenAnswered(LeadIntake $intake, ?Authenticatable $user): array {
        $values = $intake->fieldDocumentValues();
        $schema = $intake->fullSchema();
        $out = [];
        foreach ($this->hiddenFor($intake, $user) as $key => $sensitivity) {
            $field = $schema->get($key);
            if ($field !== null && $values->isAnswered($field)) {
                $out[$sensitivity->value] = ($out[$sensitivity->value] ?? 0) + 1;
            }
        }

        return $out;
    }
}
