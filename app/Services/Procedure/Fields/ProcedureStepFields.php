<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureStepFields.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procedure\Fields;

use App\Models\Procedure\ProcedureStepDef;
use App\Services\Fields\FieldDefinition;

/**
 * Feld eines Prozedurschritts (MVP-867): Schritte mit Erfassungswert
 * (Text, Zahl, Auswahl, Messreihe) beschreiben ihr Feld aus Schritttyp und
 * `config` (`options`, `unit`, `min`, `max`); Schrittarten ohne Wert
 * (Bestätigung, Warten, Backup, Freigabe, …) liefern null. Gespeichert wird
 * weiterhin `value_json = {value: …}`.
 */
class ProcedureStepFields {
    public const KEY = 'value';

    public function definition(ProcedureStepDef $def): ?FieldDefinition {
        $type = $def->step_type->fieldType();
        if ($type === null || $type->storesAttachment()) {
            return null; // Foto/Datei/Unterschrift laufen über den Nachweis-Kanal (`proof`).
        }
        $config = is_array($def->config) ? $def->config : [];
        $options = [];
        foreach ((array) ($config['options'] ?? []) as $option) {
            if (is_scalar($option) && trim((string) $option) !== '') {
                $options[] = trim((string) $option);
            }
        }

        return new FieldDefinition(
            key: self::KEY,
            type: $type,
            label: $def->label,
            required: (bool) $def->required,
            options: $options,
            help: $def->description,
            unit: is_scalar($config['unit'] ?? null) && (string) $config['unit'] !== '' ? (string) $config['unit'] : null,
            min: is_numeric($config['min'] ?? null) ? (float) $config['min'] : null,
            max: is_numeric($config['max'] ?? null) ? (float) $config['max'] : null,
        );
    }
}
