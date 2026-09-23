<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormTemplateFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories;

use App\Enums\Form\{FormFieldType, FormTemplateStatus};
use App\Models\{FormTemplate, User};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormTemplate>
 */
class FormTemplateFactory extends Factory {
    protected $model = FormTemplate::class;

    public function definition(): array {
        return [
            'name' => 'Protokoll ' . fake()->unique()->numberBetween(1, 999999),
            'description' => fake()->sentence(),
            'status' => FormTemplateStatus::Draft->value,
            'fields' => self::sampleFields(),
            'created_by_user_id' => User::factory(),
        ];
    }

    public function active(): self {
        return $this->state(fn() => ['status' => FormTemplateStatus::Active->value]);
    }

    public function archived(): self {
        return $this->state(fn() => ['status' => FormTemplateStatus::Archived->value]);
    }

    /**
     * Beispiel-Felddefinition mit allen MVP-Feldtypen.
     *
     * @return list<array{key: string, label: string, type: string, required: bool, options: list<string>, help: string|null, unit: string|null}>
     */
    public static function sampleFields(): array {
        return [
            ['key' => 'bemerkung', 'label' => 'Bemerkung', 'type' => FormFieldType::Text->value, 'required' => true, 'options' => [], 'help' => null, 'unit' => null],
            ['key' => 'beschreibung', 'label' => 'Beschreibung', 'type' => FormFieldType::Textarea->value, 'required' => false, 'options' => [], 'help' => 'Freitext', 'unit' => null],
            ['key' => 'messwert', 'label' => 'Messwert', 'type' => FormFieldType::Number->value, 'required' => false, 'options' => [], 'help' => null, 'unit' => 'kWh'],
            ['key' => 'datum', 'label' => 'Datum', 'type' => FormFieldType::Date->value, 'required' => false, 'options' => [], 'help' => null, 'unit' => null],
            ['key' => 'zustand', 'label' => 'Zustand', 'type' => FormFieldType::Select->value, 'required' => true, 'options' => ['gut', 'mittel', 'schlecht'], 'help' => null, 'unit' => null],
            ['key' => 'geprueft', 'label' => 'Geprüft', 'type' => FormFieldType::Checkbox->value, 'required' => false, 'options' => [], 'help' => null, 'unit' => null],
        ];
    }
}
