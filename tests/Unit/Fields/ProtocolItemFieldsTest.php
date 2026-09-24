<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolItemFieldsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Fields;

use App\Enums\Fields\FieldType;
use App\Enums\Protocol\ProtocolItemType;
use App\Enums\Survey\SurveyQuestionType;
use App\Models\Protocol\ProtocolItem;
use App\Models\Survey\SurveyQuestion;
use App\Services\Fields\{FieldExtensionRegistry, FieldSchema, FieldValidator};
use App\Services\Protocol\Fields\Extensions\{AttachmentsField, DefectField, MeasurementSeriesField, SignatureField};
use App\Services\Protocol\Fields\ProtocolItemFields;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/** Adapter Protokollpunkt/Umfragefrage → Feld (MVP-867): Definition, Wert, Fachtypen, Regeln. */
class ProtocolItemFieldsTest extends TestCase {
    /** @param  array<string, mixed>|null  $value */
    private function item(ProtocolItemType $type, ?array $value, string $label = 'Punkt'): ProtocolItem {
        $item = new ProtocolItem;
        $item->forceFill(['id' => 42, 'item_type' => $type->value, 'label' => $label, 'required' => false, 'value_json' => $value]);

        return $item;
    }

    public function test_definition_maps_type_options_bounds_and_extension(): void {
        $fields = app(ProtocolItemFields::class);

        $choice = $fields->definition($this->item(ProtocolItemType::Choice, ['selected' => 'b', 'options' => [['key' => 'a', 'label' => 'Gut'], ['key' => 'b', 'label' => 'Schlecht']]]));
        $this->assertSame('item_42', $choice->key);
        $this->assertSame(FieldType::Choice, $choice->type);
        $this->assertSame(['a', 'b'], $choice->options);
        $this->assertSame('Schlecht', $choice->optionLabel('b'));

        $number = $fields->definition($this->item(ProtocolItemType::Range, ['value' => 3, 'min' => 1, 'max' => 5, 'unit' => 'bar']));
        $this->assertSame(FieldType::Number, $number->type);
        $this->assertSame([1, 5, 'bar'], [$number->min, $number->max, $number->unit]);

        $text = $fields->definition($this->item(ProtocolItemType::Text, ['text' => 'x', 'min_length' => 3, 'max_length' => 40]));
        $this->assertSame([FieldType::Textarea, 3, 40], [$text->type, $text->min, $text->max]);

        $photo = $fields->definition($this->item(ProtocolItemType::Photo, ['attachment_ids' => [1], 'min_count' => 2]));
        $this->assertSame([FieldType::Photo, AttachmentsField::KEY, 2], [$photo->type, $photo->extension, $photo->min]);
        $this->assertSame(DefectField::KEY, $fields->definition($this->item(ProtocolItemType::Defect, null))->extension);
        $this->assertSame(MeasurementSeriesField::KEY, $fields->definition($this->item(ProtocolItemType::MeasurementTimestamped, null))->extension);
        $this->assertSame(SignatureField::KEY, $fields->definition($this->item(ProtocolItemType::Signature, null))->extension);
        $this->assertSame(FieldType::Section, $fields->definition($this->item(ProtocolItemType::Group, null))->type);
        $this->assertFalse($fields->definition($this->item(ProtocolItemType::Boolean, ['value' => false]))->required, 'Pflicht prüft der Protokollvalidator selbst.');
    }

    public function test_value_is_read_from_the_canonical_value_json_keys(): void {
        $fields = app(ProtocolItemFields::class);
        $this->assertSame('Alles ok', $fields->value($this->item(ProtocolItemType::Text, ['text' => 'Alles ok'])));
        $this->assertFalse($fields->value($this->item(ProtocolItemType::Boolean, ['value' => false])));
        $this->assertSame(['x', 'z'], $fields->value($this->item(ProtocolItemType::Multichoice, ['selected' => ['x', 'z']])));
        $this->assertSame([1, 2], $fields->value($this->item(ProtocolItemType::File, ['attachment_ids' => [1, 2]])));
        $this->assertSame(7, $fields->value($this->item(ProtocolItemType::Signature, ['signature_id' => 7])));
        $this->assertNull($fields->value($this->item(ProtocolItemType::Group, null)));
    }

    public function test_extensions_validate_and_display_their_values(): void {
        $registry = app(FieldExtensionRegistry::class);
        $this->assertSame([AttachmentsField::KEY, DefectField::KEY, MeasurementSeriesField::KEY, SignatureField::KEY], collect(array_keys($registry->all()))->sort()->values()->all());

        $fields = app(ProtocolItemFields::class);
        $validator = app(FieldValidator::class);
        /** @var list<array{0: ProtocolItemType, 1: array<string, mixed>, 2: bool, 3: string|null}> $cases */
        $cases = [
            [ProtocolItemType::Defect, ['severity' => 'high', 'description' => 'Leck'], true, 'High: Leck'],
            [ProtocolItemType::Defect, ['severity' => 'egal', 'description' => ''], false, null],
            [ProtocolItemType::MeasurementTimestamped, ['samples' => [['value' => 1.5, 'at' => '2026-09-24T10:00:00+02:00']]], true, '1 × 1,5'],
            [ProtocolItemType::MeasurementTimestamped, ['samples' => [['value' => 1.5]]], false, null],
            [ProtocolItemType::Photo, ['attachment_ids' => [1], 'min_count' => 2], false, null],
            [ProtocolItemType::Photo, ['attachment_ids' => [1, 2]], true, (string) trans_choice('fields.value.attachments', 2, ['count' => 2])],
            [ProtocolItemType::Signature, ['signature_id' => 'x'], false, null],
            [ProtocolItemType::Signature, ['signature_id' => 5], true, (string) __('fields.value.signed')],
            [ProtocolItemType::Choice, ['selected' => 'x', 'options' => [['key' => 'a', 'label' => 'A']]], false, null],
            [ProtocolItemType::Choice, ['selected' => 'a', 'options' => [['key' => 'a', 'label' => 'A']]], true, 'A'],
            [ProtocolItemType::Date, ['value' => 'tomorrow'], false, null],
            [ProtocolItemType::DateTime, ['value' => '2026-09-24T10:30:00+02:00'], true, null],
        ];
        foreach ($cases as [$type, $value, $valid, $display]) {
            $item = $this->item($type, $value);
            $definition = $fields->definition($item);
            $schema = new FieldSchema([$definition]);
            $passes = Validator::make(['values' => ['item_42' => $fields->value($item)]], $validator->rules($schema))->passes();
            $this->assertSame($valid, $passes, $type->value . ' ' . json_encode($value));
            if ($display !== null) {
                $this->assertSame($display, $fields->values($item)->display($definition, $registry), $type->value);
            }
        }
    }

    public function test_survey_questions_describe_their_field(): void {
        $nps = new SurveyQuestion;
        $nps->forceFill(['id' => 9, 'type' => 'nps', 'label' => 'Weiterempfehlung', 'required' => true, 'options' => null]);
        $definition = $nps->fieldDefinition();
        $this->assertSame(['q9', FieldType::Scale, 0, 10, true], [$definition->key, $definition->type, $definition->min, $definition->max, $definition->required]);

        $choice = new SurveyQuestion;
        $choice->forceFill(['id' => 10, 'type' => 'choice', 'label' => 'Kanal', 'required' => false, 'options' => ['Mail', 'Telefon']]);
        $this->assertSame(['Mail', 'Telefon'], $choice->fieldDefinition()->options);
        $this->assertSame(SurveyQuestionType::Text->fieldType(), FieldType::Textarea);
        $this->assertSame(['nps', 'scale', 'choice', 'text'], SurveyQuestionType::values());

        $rules = app(FieldValidator::class)->rules(new FieldSchema([$definition]), '');
        $this->assertSame(['required', 'integer', 'min:0', 'max:10'], $rules['q9']);
    }
}
