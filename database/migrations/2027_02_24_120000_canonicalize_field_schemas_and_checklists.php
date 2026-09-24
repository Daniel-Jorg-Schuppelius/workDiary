<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_24_120000_canonicalize_field_schemas_and_checklists.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Services\Fields\FieldDocument;
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MVP-866 Feldschema-Baustein: Formularfelder tragen die Typnamen des
 * FieldType (`select` → `choice`, `checkbox` → `boolean`), Checklisten die
 * Kanonik `{schema, values}`. Idempotent; Bestandswerte bleiben inhaltlich
 * gleich (Labels, Erledigt-Flags), nur die Form wird vereinheitlicht.
 */
return new class extends Migration {
    private const TYPE_ALIASES = ['select' => 'choice', 'checkbox' => 'boolean'];

    public function up(): void {
        $this->rewriteFieldTypes('form_templates', 'fields');
        $this->rewriteFieldTypes('form_submissions', 'fields_snapshot');
        foreach (['asset_inspection_events', 'rental_handover_reports', 'rental_return_reports', 'employee_drafts'] as $table) {
            $this->canonicalizeChecklists($table);
        }
    }

    public function down(): void {
        // Die Kanonik bleibt lesbar; frühere Typnamen werden nicht zurückgeschrieben.
    }

    private function rewriteFieldTypes(string $table, string $column): void {
        DB::table($table)->select(['id', $column])->whereNotNull($column)->orderBy('id')->chunkById(200, function ($rows) use ($table, $column): void {
            foreach ($rows as $row) {
                $fields = JsonHelper::decode((string) $row->{$column}, true);
                if (! is_array($fields)) {
                    continue;
                }
                $changed = false;
                foreach ($fields as $i => $field) {
                    $type = is_array($field) ? ($field['type'] ?? null) : null;
                    if (is_string($type) && isset(self::TYPE_ALIASES[$type])) {
                        $fields[$i]['type'] = self::TYPE_ALIASES[$type];
                        $changed = true;
                    }
                }
                if ($changed) {
                    DB::table($table)->where('id', $row->id)->update([$column => JsonHelper::encode($fields)]);
                }
            }
        });
    }

    private function canonicalizeChecklists(string $table): void {
        DB::table($table)->select(['id', 'checklist'])->whereNotNull('checklist')->orderBy('id')->chunkById(200, function ($rows) use ($table): void {
            foreach ($rows as $row) {
                $raw = JsonHelper::decode((string) $row->checklist, true);
                if (is_array($raw) && isset($raw['schema'])) {
                    continue;
                }
                $document = FieldDocument::fromStored($raw);
                DB::table($table)->where('id', $row->id)->update([
                    'checklist' => $document === null || $document->isEmpty() ? null : JsonHelper::encode($document->toArray()),
                ]);
            }
        });
    }
};
