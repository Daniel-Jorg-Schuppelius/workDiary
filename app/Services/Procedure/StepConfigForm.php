<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StepConfigForm.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procedure;

use App\Enums\Procedure\{ProcedureBackupScope, ProcedureBackupStorageTarget, ProcedureBackupVerifyMethod, ProcedureStepType};
use CommonToolkit\Helper\Data\{JsonHelper, NumberHelper, StringHelper};
use Illuminate\Validation\Rule;

/**
 * Übersetzt die Schrittkonfiguration (`config`) zwischen Speicherform und den
 * Formularfeldern von Designer und Schrittbibliothek (MVP-1569). Schlüssel,
 * die das Formular nicht kennt, reisen in `config_extra` mit und bleiben
 * erhalten — etwa aus Branchenprofilen oder Importen.
 */
final class StepConfigForm {
    /** Schlüssel, die das Formular selbst pflegt. */
    public const array MANAGED = [
        'depends_on', 'options', 'unit', 'min', 'max', 'wait_seconds',
        'requires_prior_backup', 'prior_backup_max_age_minutes',
        'backup_scope', 'min_size_kb', 'max_age_minutes', 'verify_method', 'storage_target',
        'second_person_role', 'second_person_min_qualification', 'second_person_self_exclusion',
    ];

    /** Leere Formularwerte einer neuen Zeile. */
    public const array BLANK = [
        'condition_step' => '',
        'condition_equals' => '',
        'options' => '',
        'unit' => '',
        'min' => '',
        'max' => '',
        'wait_seconds' => '',
        'requires_prior_backup' => false,
        'prior_backup_max_age_minutes' => '',
        'backup_scope' => '',
        'min_size_kb' => '',
        'max_age_minutes' => '',
        'verify_method' => '',
        'storage_target' => '',
        'second_person_role' => '',
        'second_person_min_qualification' => '',
        'second_person_allow_self' => false,
        'config_extra' => '',
    ];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(string $prefix = ''): array {
        return [
            $prefix . 'condition_step' => ['nullable', 'string', 'max:60'],
            $prefix . 'condition_equals' => ['nullable', 'string', 'max:120'],
            $prefix . 'options' => ['nullable', 'string', 'max:2000'],
            $prefix . 'unit' => ['nullable', 'string', 'max:20'],
            $prefix . 'min' => ['nullable', 'numeric'],
            $prefix . 'max' => ['nullable', 'numeric'],
            $prefix . 'wait_seconds' => ['nullable', 'integer', 'min:1', 'max:2592000'],
            $prefix . 'requires_prior_backup' => ['nullable'],
            $prefix . 'prior_backup_max_age_minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
            $prefix . 'backup_scope' => ['nullable', Rule::enum(ProcedureBackupScope::class)],
            $prefix . 'min_size_kb' => ['nullable', 'integer', 'min:0', 'max:2000000000'],
            $prefix . 'max_age_minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
            $prefix . 'verify_method' => ['nullable', Rule::enum(ProcedureBackupVerifyMethod::class)],
            $prefix . 'storage_target' => ['nullable', Rule::enum(ProcedureBackupStorageTarget::class)],
            $prefix . 'second_person_role' => ['nullable', 'string', 'max:40'],
            $prefix . 'second_person_min_qualification' => ['nullable', 'string', 'max:60'],
            $prefix . 'second_person_allow_self' => ['nullable'],
            $prefix . 'config_extra' => ['nullable', 'json', 'max:10000'],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $config
     * @return array<string, mixed>
     */
    public function toForm(?array $config): array {
        $config ??= [];
        $extra = array_diff_key($config, array_flip(self::MANAGED));
        $text = static fn (string $key): string => is_scalar($config[$key] ?? null) ? (string) $config[$key] : '';

        return [
            'condition_step' => (string) (data_get($config, 'depends_on.step_code') ?? ''),
            'condition_equals' => (string) (data_get($config, 'depends_on.equals') ?? ''),
            'options' => implode(', ', array_filter(array_map(static fn (mixed $o): string => is_scalar($o) ? trim((string) $o) : '', (array) ($config['options'] ?? [])), static fn (string $o): bool => $o !== '')),
            'unit' => $text('unit'),
            'min' => $text('min'),
            'max' => $text('max'),
            'wait_seconds' => $text('wait_seconds'),
            'requires_prior_backup' => ! empty($config['requires_prior_backup']),
            'prior_backup_max_age_minutes' => $text('prior_backup_max_age_minutes'),
            'backup_scope' => $text('backup_scope'),
            'min_size_kb' => $text('min_size_kb'),
            'max_age_minutes' => $text('max_age_minutes'),
            'verify_method' => $text('verify_method'),
            'storage_target' => $text('storage_target'),
            'second_person_role' => $text('second_person_role'),
            'second_person_min_qualification' => $text('second_person_min_qualification'),
            'second_person_allow_self' => ! ($config['second_person_self_exclusion'] ?? true),
            'config_extra' => $extra === [] ? '' : JsonHelper::encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * Baut `config` aus einer Formularzeile. Geschrieben wird nur, was zur
     * Schrittart gehört; die Felder anderer Arten sind im Formular bloß verborgen.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    public function fromForm(array $row, ProcedureStepType $type, bool $secondPerson): ?array {
        $raw = is_string($row['config_extra'] ?? null) ? $row['config_extra'] : '';
        $extra = $raw !== '' && JsonHelper::isValid($raw) ? JsonHelper::decode($raw) : null;
        $config = is_array($extra) ? array_diff_key($extra, array_flip(self::MANAGED)) : [];
        $text = static fn (string $key): ?string => StringHelper::trimToNull(is_scalar($row[$key] ?? null) ? (string) $row[$key] : null);
        $int = static fn (string $key): ?int => NumberHelper::toIntOrNull($row[$key] ?? null);

        if (($conditionStep = $text('condition_step')) !== null) {
            $config['depends_on'] = ['step_code' => $conditionStep, 'equals' => $text('condition_equals')];
        }

        if ($type === ProcedureStepType::Choice) {
            $options = array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', (string) ($row['options'] ?? '')) ?: []), static fn (string $o): bool => $o !== ''));
            if ($options !== []) {
                $config['options'] = $options;
            }
        }

        if (in_array($type, [ProcedureStepType::Number, ProcedureStepType::Messreihe], true)) {
            $config += array_filter([
                'unit' => $text('unit'),
                'min' => is_numeric($row['min'] ?? null) ? (float) $row['min'] : null,
                'max' => is_numeric($row['max'] ?? null) ? (float) $row['max'] : null,
            ], static fn (mixed $v): bool => $v !== null);
        }

        if ($type === ProcedureStepType::Wait && ($seconds = $int('wait_seconds')) !== null && $seconds > 0) {
            $config['wait_seconds'] = $seconds;
        }

        if ($type === ProcedureStepType::Backup) {
            $config += array_filter([
                'backup_scope' => ProcedureBackupScope::tryFrom((string) $text('backup_scope'))?->value,
                'min_size_kb' => $int('min_size_kb'),
                'max_age_minutes' => $int('max_age_minutes'),
                'verify_method' => ProcedureBackupVerifyMethod::tryFrom((string) $text('verify_method'))?->value,
                'storage_target' => ProcedureBackupStorageTarget::tryFrom((string) $text('storage_target'))?->value,
            ], static fn (mixed $v): bool => $v !== null && $v !== 0);
        } elseif (self::flag($row['requires_prior_backup'] ?? false)) {
            $config['requires_prior_backup'] = true;
            if (($age = $int('prior_backup_max_age_minutes')) !== null && $age > 0) {
                $config['prior_backup_max_age_minutes'] = $age;
            }
        }

        if ($secondPerson || $type === ProcedureStepType::Freigabe) {
            $config += array_filter([
                'second_person_role' => $text('second_person_role'),
                'second_person_min_qualification' => $text('second_person_min_qualification'),
            ], static fn (mixed $v): bool => $v !== null);
            // Selbstausschluss ist der Regelfall; gespeichert wird nur die Ausnahme.
            if (self::flag($row['second_person_allow_self'] ?? false)) {
                $config['second_person_self_exclusion'] = false;
            }
        }

        return $config === [] ? null : $config;
    }

    public static function flag(mixed $value): bool {
        return in_array($value, [true, '1', 1, 'on', 'true'], true);
    }
}
