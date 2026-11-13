<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldAutosaver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Fields;

use App\Exceptions\FieldDocumentConflictException;
use App\Models\Contracts\AutosavesFieldDocument;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\ValidationException;

/**
 * Speichern je Feld (Feature 203, MVP-1650) für jeden Träger von
 * {@see AutosavesFieldDocument}: prüft nur das eine Feld (Pflicht erst beim
 * Abschluss), vergleicht die Version unter Zeilensperre und schreibt Wert,
 * Ergänzung und neue Version. Dateien und Unterschriften laufen nicht hier
 * durch — sie kommen mit dem Abschluss.
 */
final class FieldAutosaver {
    /** Länge einer Ergänzung (wie das Eingabefeld). */
    public const int NOTE_MAX = 2000;

    public function __construct(
        private readonly FieldValidator $validator,
        private readonly FieldExtensionRegistry $extensions,
    ) {}

    /**
     * @param  Model&AutosavesFieldDocument  $document
     * @param  array{value?: mixed, note?: mixed}  $input  `value` und/oder `note`; was fehlt, bleibt unverändert
     *
     * @throws FieldDocumentConflictException veraltete Version oder abgeschlossener Träger
     * @throws ValidationException unbekanntes Feld oder ungültiger Wert (Schlüssel `value`/`note`)
     */
    public function save(Model $document, string $key, array $input, int $expectedVersion): FieldAutosaveResult {
        return DB::transaction(function () use ($document, $key, $input, $expectedVersion): FieldAutosaveResult {
            /** @var (Model&AutosavesFieldDocument)|null $locked */
            $locked = $document->newQuery()->whereKey($document->getKey())->lockForUpdate()->first();
            if (! $locked instanceof AutosavesFieldDocument) {
                throw new FieldDocumentConflictException(FieldDocumentConflictException::CLOSED, 0);
            }
            $current = $locked->fieldDocumentVersion();
            if (! $locked->acceptsFieldAutosave()) {
                throw new FieldDocumentConflictException(FieldDocumentConflictException::CLOSED, $current);
            }
            if ($current !== $expectedVersion) {
                throw new FieldDocumentConflictException(FieldDocumentConflictException::STALE, $current);
            }

            $schema = $locked->fieldDocumentSchema();
            $values = $locked->fieldDocumentValues();
            $field = $schema->get($key);
            if ($field === null || ! $field->type->hasValue() || $field->type->storesAttachment()) {
                throw ValidationException::withMessages(['value' => (string) __('fields.autosave.unknown_field')]);
            }

            if (array_key_exists('value', $input)) {
                $values = $values->with([$key => $this->normalizedValue($field, $input['value'])]);
            }
            if (array_key_exists('note', $input)) {
                $values = $values->withNote($key, $this->note($field, $values, $input['note']));
            }

            $version = $current + 1;
            $locked->storeFieldAutosave($values, $version);

            return new FieldAutosaveResult($version, $values->answeredBySection($schema), $values->isAnswered($field));
        });
    }

    /** Nur dieses Feld prüfen, ohne Pflicht — ein halber Bogen soll sich zwischenspeichern lassen. */
    private function normalizedValue(FieldDefinition $field, mixed $raw): mixed {
        $single = new FieldSchema([$field->optional()]);
        $validator = Validator::make([$field->key => $raw], $this->validator->rules($single, ''), [], $single->attributeNames(''));
        if ($validator->fails()) {
            throw ValidationException::withMessages(['value' => (string) $validator->errors()->first()]);
        }

        return FieldValues::normalize($single, [$field->key => $raw], $this->extensions)->get($field->key);
    }

    /** Ergänzung nur, wo die Frage sie erlaubt oder schon eine trägt (übernommene frühere Antwort). */
    private function note(FieldDefinition $field, FieldValues $values, mixed $raw): ?string {
        if (! $field->allowsNote && $values->note($field->key) === null) {
            throw ValidationException::withMessages(['note' => (string) __('fields.autosave.note_not_allowed')]);
        }
        if ($raw !== null && ! is_scalar($raw)) {
            throw ValidationException::withMessages(['note' => (string) __('validation.string', ['attribute' => __('fields.note.label')])]);
        }
        $note = StringHelper::trimToNull($raw === null ? null : (string) $raw);
        if ($note !== null && mb_strlen($note) > self::NOTE_MAX) {
            throw ValidationException::withMessages(['note' => (string) __('validation.max.string', ['attribute' => __('fields.note.label'), 'max' => self::NOTE_MAX])]);
        }

        return $note;
    }
}
