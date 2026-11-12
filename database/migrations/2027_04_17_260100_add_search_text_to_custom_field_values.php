<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_260100_add_search_text_to_custom_field_values.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Models\Fields\CustomFieldValue;
use App\Services\Fields\CustomFieldService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Eigene Felder in der globalen Suche (MVP-1615): Die Suchbereiche fragen
 * live per SQL; die gespeicherten Werte sind JSON mit Schlüsseln statt
 * Anzeigetexten (Auswahl, Datum, Ja/Nein). Deshalb liegt der Anzeigetext je
 * Wertesatz in `search_text`; Bestandswerte werden hier befüllt.
 */
return new class extends Migration {
    public function up(): void {
        if (! Schema::hasColumn('custom_field_values', 'search_text')) {
            Schema::table('custom_field_values', function (Blueprint $table): void {
                $table->text('search_text')->nullable()->after('values');
            });
        }

        $service = app(CustomFieldService::class);
        CustomFieldValue::query()->withoutGlobalScopes()->orderBy('id')->chunkById(200, function ($rows) use ($service): void {
            foreach ($rows as $row) {
                /** @var CustomFieldValue $row */
                $schema = $service->schemaFor($row->subject_type, (int) $row->organization_id);
                DB::table('custom_field_values')->where('id', $row->id)->update(['search_text' => $service->searchText($schema, $row->values)]);
            }
        });
    }

    public function down(): void {
        Schema::table('custom_field_values', function (Blueprint $table): void {
            $table->dropColumn('search_text');
        });
    }
};
