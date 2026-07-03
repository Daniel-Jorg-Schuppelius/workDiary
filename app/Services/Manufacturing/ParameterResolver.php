<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ParameterResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\Manufacturing\ParameterType;
use App\Models\{ProcedureParameterDefinition, ProcedureTemplateVersion};
use CommonToolkit\Helper\Data\DateHelper;
use RuntimeException;

/**
 * Löst die typisierten Auftragsparameter einer Arbeitsplan-Version gegen die im
 * Auftrag erfassten Werte auf, validiert sie gegen die Constraints und liefert
 * den einzufrierenden Snapshot (Feature 047, MVP-061).
 *
 * Akzeptanzkriterium P2: beim Freigeben werden Definition + Werte vollständig
 * eingefroren — daher enthält der Snapshot je Parameter code, label, type, value
 * und (bei Measure) die Einheit.
 */
class ParameterResolver {
    /**
     * Validiert die Werte und baut den Parameter-Snapshot.
     *
     * @param  array<string, mixed>  $values  code => Wert (aus dem Auftrag)
     * @return list<array{code: string, label: string, type: string, value: mixed, unit: ?string}>
     *
     * @throws RuntimeException Bei fehlendem Pflichtparameter oder ungültigem Wert.
     */
    public function snapshot(ProcedureTemplateVersion $version, array $values): array {
        $definitions = ProcedureParameterDefinition::query()
            ->where('procedure_template_version_id', $version->id)
            ->where('active', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $snapshot = [];
        foreach ($definitions as $definition) {
            $constraints = $definition->constraints ?? [];
            $value = array_key_exists($definition->code, $values)
                ? $values[$definition->code]
                : ($constraints['default'] ?? null);

            $this->validate($definition, $constraints, $value);

            $snapshot[] = [
                'code' => $definition->code,
                'label' => $definition->label,
                'type' => $definition->type->value,
                'value' => $value,
                'unit' => isset($constraints['unit']) ? (string) $constraints['unit'] : null,
            ];
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $constraints
     */
    private function validate(ProcedureParameterDefinition $definition, array $constraints, mixed $value): void {
        $required = (bool) ($constraints['required'] ?? false);
        $isEmpty = $value === null || $value === '';

        if ($isEmpty) {
            if ($required) {
                throw new RuntimeException((string) __('manufacturing.parameter.error.required', ['param' => $definition->label]));
            }

            return; // Optionaler, nicht gesetzter Parameter.
        }

        $invalid = fn (): never => throw new RuntimeException(
            (string) __('manufacturing.parameter.error.invalid', ['param' => $definition->label]),
        );

        switch ($definition->type) {
            case ParameterType::Number:
            case ParameterType::Measure:
                if (! is_numeric($value)) {
                    $invalid();
                }
                $number = (float) $value;
                if (isset($constraints['min']) && $number < (float) $constraints['min']) {
                    $invalid();
                }
                if (isset($constraints['max']) && $number > (float) $constraints['max']) {
                    $invalid();
                }
                break;

            case ParameterType::Choice:
                $options = is_array($constraints['options'] ?? null) ? $constraints['options'] : [];
                if (! in_array($value, $options, true)) {
                    $invalid();
                }
                break;

            case ParameterType::Date:
                // Nur echte Datumsangaben — relative Ausdrücke wie "tomorrow" sind ungültig.
                if (! is_scalar($value) || ! DateHelper::isDate((string) $value)) {
                    $invalid();
                }
                break;

            case ParameterType::Bool:
                if (! is_bool($value) && ! in_array($value, [0, 1, '0', '1', true, false], true)) {
                    $invalid();
                }
                break;

            case ParameterType::Text:
                // Freitext — keine weitere Prüfung.
                break;
        }
    }
}
