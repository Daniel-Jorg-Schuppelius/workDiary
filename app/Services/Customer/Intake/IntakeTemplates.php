<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakeTemplates.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Intake;

use App\Enums\Customer\IntakeKind;
use App\Enums\Fields\FieldType;
use App\Services\Fields\FieldSchema;

/**
 * Die zwei festen Vorlagen des Kundeneingangs (MVP-1076/1077) über den
 * Feldschema-Baustein — kein Formularbaukasten. Betreff, Beschreibung und
 * Wunschtermin sind gemeinsame Spalten; „Beratung erforderlich" steht als
 * Auswahl, wo der Kunde die Angabe fachlich nicht kennen muss.
 */
final class IntakeTemplates {
    public const CONSULT = 'consult';

    public function schema(IntakeKind $kind): FieldSchema {
        return FieldSchema::fromArray(match ($kind) {
            IntakeKind::Print => $this->print(),
            IntakeKind::It => $this->it(),
        });
    }

    /** @return list<array<string, mixed>> */
    private function print(): array {
        return [
            $this->field('product', FieldType::Text, 'print.product', required: true, help: 'print.product_help'),
            $this->field('quantity', FieldType::Number, 'print.quantity', help: 'print.quantity_help', extra: ['min' => 1, 'step' => 1]),
            $this->choice('final_format', 'print.final_format', ['a6', 'a5', 'a4', 'a3', 'din_long', 'business_card', 'custom', self::CONSULT]),
            $this->choice('color_mode', 'print.color_mode', ['4_4', '4_0', '1_1', '1_0', self::CONSULT]),
            $this->field('material', FieldType::Text, 'print.material', help: 'print.material_help'),
            $this->choice('delivery', 'print.delivery', ['pickup', 'shipping']),
            $this->field('shipping_address', FieldType::Textarea, 'print.shipping_address', required: true, extra: [
                'visible_if' => ['field' => 'delivery', 'op' => 'eq', 'value' => 'shipping'],
                'max' => 1000,
            ]),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function it(): array {
        return [
            $this->field('service', FieldType::Text, 'it.service', required: true),
            $this->field('system', FieldType::Text, 'it.system', help: 'it.system_help'),
            $this->choice('impact', 'it.impact', ['planned', 'single', 'several', 'company', self::CONSULT]),
            $this->choice('execution', 'it.execution', ['remote', 'on_site', 'either', self::CONSULT]),
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function field(string $key, FieldType $type, string $label, bool $required = false, ?string $help = null, array $extra = []): array {
        return [
            'key' => $key,
            'type' => $type->value,
            'label' => (string) __('customer_intake.template.' . $label),
            'required' => $required,
            'help' => $help !== null ? (string) __('customer_intake.template.' . $help) : null,
            ...$extra,
        ];
    }

    /**
     * @param  list<string>  $options
     * @return array<string, mixed>
     */
    private function choice(string $key, string $label, array $options): array {
        $labels = [];
        foreach ($options as $option) {
            $labels[$option] = (string) __('customer_intake.template.' . $label . '_options.' . $option);
        }

        return $this->field($key, FieldType::Choice, $label, required: true, extra: ['options' => $options, 'option_labels' => $labels]);
    }
}
