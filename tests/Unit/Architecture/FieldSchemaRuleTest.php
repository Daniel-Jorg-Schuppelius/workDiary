<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldSchemaRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Casts\{FieldDocumentCast, FieldSchemaCast, FieldValuesCast};
use App\Enums\Fields\Contracts\FieldTyped;
use App\Enums\Fields\FieldType;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Gate MVP-866: Feldtypen gibt es nur im Feldschema-Baustein
 * ({@see FieldType}), Checklisten-Spalten laufen über den
 * {@see FieldDocumentCast}, Typ-Verzweigungen liegen im Baustein und seinen
 * Blade-Komponenten — nicht in Diensten oder Einzelansichten.
 */
class FieldSchemaRuleTest extends TestCase {
    use ScansSourceTree;

    /** Werte, die einen Erfassungs-Typkatalog verraten. */
    private const TYPE_MARKERS = ['text', 'textarea', 'number', 'boolean', 'checkbox', 'select', 'choice', 'multichoice', 'date', 'datetime', 'photo', 'file', 'signature', 'scale'];

    /** @var array<string, string> Enums mit Feldtyp-Charakter außerhalb des Bausteins (Grund) */
    private const ENUMS_ALLOWED = [
        'App\Enums\Procedure\ProcedureProofType' => 'Nachweisart eines Prozedurschritts (Fachtyp, keine Erfassungssemantik).',
        'App\Enums\Manufacturing\ParameterType' => 'Fertigungsparameter einer Stückliste (Fachtyp, keine Erfassungssemantik).',
    ];

    /** @var array<string, string> Modell::Spalte mit Schema-/Wertecharakter, die den Baustein anders als per Cast nutzen (Grund) */
    private const JSON_COLUMNS_ALLOWED = [
        'App\Models\Form\FormTemplate::fields' => 'FieldSchema::fromArray() im FormService und in den Views; Rohform bleibt für Dialog und Snapshot.',
        'App\Models\Form\FormSubmission::fields_snapshot' => 'Eingefrorene Definition — gelesen über FieldSchema::fromArray().',
        'App\Models\Form\FormSubmission::values' => 'FieldValues::fromArray(); Rohform für Sync und Export.',
        'App\Models\Protocol\ProtocolItem::value_json' => 'Hash-kanonische Rohform (ProtocolHasher); gelesen über ProtocolItemFields.',
        'App\Models\Procedure\ProcedureStepRun::value_json' => '{value}/{values} für SPC; Feld über ProcedureStepFields.',
        'App\Models\Print\LabelTemplate::fields' => 'Layoutfelder eines Etiketts (Druckposition), keine Erfassung.',
        'App\Models\Audit\AuditRedaction::fields' => 'Liste geschwärzter Spaltennamen, keine Erfassung.',
    ];

    private const SCHEMA_COLUMNS = ['fields', 'fields_snapshot', 'checklist', 'value_json', 'values', 'schema'];

    /** @var array<string, string> Dateien, die auf Feldtypen verzweigen dürfen */
    private const TYPE_SWITCH_ALLOWED = [
        'app/Enums/Fields/' => 'Der Katalog selbst.',
        'app/Services/Fields/' => 'Der Baustein.',
        'app/Services/Protocol/Fields/' => 'Adapter Protokollpunkt → Feld (liest value_json).',
        'app/Services/Procedure/Fields/' => 'Adapter Prozedurschritt → Feld.',
        'resources/views/components/field-input.blade.php' => 'Eingabe je Typ.',
        'resources/views/components/field-display.blade.php' => 'Anzeige je Typ.',
    ];

    public function test_field_type_catalogs_live_only_in_the_fields_module(): void {
        $violations = [];
        foreach ($this->phpFiles('app/Enums') as $file) {
            $source = (string) file_get_contents($file);
            if (preg_match('/^enum\s+\w+/m', $source) !== 1) {
                continue;
            }
            preg_match_all("/case\s+\w+\s*=\s*'([^']+)'/", $source, $matches);
            $values = array_map('strtolower', $matches[1]);
            if (count(array_intersect($values, self::TYPE_MARKERS)) < 3) {
                continue;
            }
            $class = 'App\\' . str_replace('/', '\\', substr($this->relativePath($file), 4, -4));
            // Fach-Enums dürfen bleiben, wenn sie auf den Katalog abbilden (FieldTyped).
            if ($class === FieldType::class || isset(self::ENUMS_ALLOWED[$class]) || (enum_exists($class) && is_subclass_of($class, FieldTyped::class))) {
                continue;
            }
            $violations[] = $class;
        }
        sort($violations);

        $this->assertSame([], $violations, "Erfassungs-Typkataloge gehören in App\\Enums\\Fields\\FieldType oder delegieren als FieldTyped an ihn (Fachtyp: FieldDefinition::extension); Ausnahme mit Grund in ENUMS_ALLOWED:\n" . implode("\n", $violations));
    }

    public function test_schema_and_checklist_columns_use_the_fields_module(): void {
        $violations = [];
        foreach ($this->modelClasses() as $class) {
            /** @var Model $model */
            $model = new $class;
            foreach ($model->getCasts() as $column => $cast) {
                if (! in_array($column, self::SCHEMA_COLUMNS, true)) {
                    continue;
                }
                if ($column === 'checklist') {
                    if ($cast !== FieldDocumentCast::class) {
                        $violations[] = "{$class}::{$column} ({$cast}) — FieldDocumentCast verwenden";
                    }

                    continue;
                }
                if (in_array($cast, [FieldDocumentCast::class, FieldSchemaCast::class, FieldValuesCast::class], true) || isset(self::JSON_COLUMNS_ALLOWED["{$class}::{$column}"])) {
                    continue;
                }
                $violations[] = "{$class}::{$column} ({$cast})";
            }
        }
        sort($violations);

        $this->assertSame([], $violations, "Schema-/Werte-Spalten laufen über den Feldschema-Baustein (FieldDocumentCast oder FieldSchema/FieldValues mit Eintrag in JSON_COLUMNS_ALLOWED):\n" . implode("\n", $violations));
    }

    public function test_type_switches_stay_inside_the_fields_module(): void {
        // Heuristik: Verzweigungen über die Variable `$field` (Definition) oder
        // Blade-Cases auf den Katalog; Match-Arme mit FieldType-Fällen nur in
        // Dateien, die den Katalog des Bausteins referenzieren (Plugins haben
        // einen eigenen Einstellungs-FieldType).
        $generic = '/(?:match\s*\(\s*\$field->type\s*\)|match\s*\(\s*\(string\)\s*\(?\$field\[\'type\'\]|@switch\s*\(\s*\$field(?:->type|\[\'type\'\])|@case\s*\(\s*\\\\?App\\\\Enums\\\\Fields\\\\FieldType)/';
        $arms = '/FieldType::\w+(?:->value)?\s*=>/';
        $violations = [];
        foreach ([...$this->phpFiles('app'), ...$this->bladeFiles()] as $file) {
            $relative = $this->relativePath($file);
            if ($this->isAllowListed($relative, self::TYPE_SWITCH_ALLOWED)) {
                continue;
            }
            $source = str_ends_with($relative, '.blade.php')
                ? $this->stripBladeComments((string) file_get_contents($file))
                : $this->stripComments((string) file_get_contents($file));
            $pattern = str_contains($source, 'Enums\\Fields\\FieldType') ? '/(?:' . substr($generic, 1, -1) . '|' . substr($arms, 1, -1) . ')/' : $generic;
            if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }
            foreach ($matches[0] as [$snippet, $offset]) {
                $violations[] = "{$relative}:{$this->lineOf($source, $offset)}: " . trim($snippet);
            }
        }

        $this->assertSame([], $violations, "Verzweigung auf Feldtypen nur im Baustein und in x-field-input/x-field-display — Ansichten und Dienste nutzen die Komponenten bzw. FieldValidator/FieldValues:\n" . implode("\n", $violations));
    }
}
