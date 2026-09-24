<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormTemplateFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Factories\Form;

use App\Enums\Fields\FieldType;
use App\Enums\Form\FormTemplateStatus;
use App\Models\Form\FormTemplate;
use App\Models\Platform\User;
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
            ['key' => 'bemerkung', 'label' => 'Bemerkung', 'type' => FieldType::Text->value, 'required' => true, 'options' => [], 'help' => null, 'unit' => null],
            ['key' => 'beschreibung', 'label' => 'Beschreibung', 'type' => FieldType::Textarea->value, 'required' => false, 'options' => [], 'help' => 'Freitext', 'unit' => null],
            ['key' => 'messwert', 'label' => 'Messwert', 'type' => FieldType::Number->value, 'required' => false, 'options' => [], 'help' => null, 'unit' => 'kWh'],
            ['key' => 'datum', 'label' => 'Datum', 'type' => FieldType::Date->value, 'required' => false, 'options' => [], 'help' => null, 'unit' => null],
            ['key' => 'zustand', 'label' => 'Zustand', 'type' => FieldType::Choice->value, 'required' => true, 'options' => ['gut', 'mittel', 'schlecht'], 'help' => null, 'unit' => null],
            ['key' => 'geprueft', 'label' => 'Geprüft', 'type' => FieldType::Boolean->value, 'required' => false, 'options' => [], 'help' => null, 'unit' => null],
        ];
    }
}
