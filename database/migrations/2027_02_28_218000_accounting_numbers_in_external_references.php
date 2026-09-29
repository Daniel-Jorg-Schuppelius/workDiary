<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_218000_accounting_numbers_in_external_references.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * MVP-1028: Die Nummer eines Datensatzes im Fremdsystem (z. B. die
 * Kundennummer in Lexoffice) steht an der Referenz (`external_number`) statt
 * als `customers.lexoffice_contact_number`. Kunden ohne Lexoffice-Referenz
 * verlieren die Nummer; der nächste Kontakt-Sync setzt sie beim Verknüpfen neu.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('external_references', function (Blueprint $table): void {
            $table->string('external_number', 64)->nullable();
            $table->index(['organization_id', 'external_number'], 'extref_org_number_idx');
        });

        DB::table('customers')->whereNotNull('lexoffice_contact_number')->where('lexoffice_contact_number', '!=', '')
            ->orderBy('id')->select(['id', 'lexoffice_contact_number'])
            ->chunkById(500, static function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('external_references')
                        ->where('plugin_id', 'lexoffice')->where('external_type', 'contact')
                        ->where('referenceable_type', 'customers')->where('referenceable_id', $row->id)
                        ->update(['external_number' => $row->lexoffice_contact_number]);
                }
            });
        DB::table('suppliers')->whereNotNull('vendor_number')->where('vendor_number', '!=', '')
            ->orderBy('id')->select(['id', 'vendor_number'])
            ->chunkById(500, static function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('external_references')
                        ->where('plugin_id', 'lexoffice')->where('external_type', 'contact')
                        ->where('referenceable_type', 'suppliers')->where('referenceable_id', $row->id)
                        ->update(['external_number' => $row->vendor_number]);
                }
            });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('lexoffice_contact_number');
        });
    }

    public function down(): void {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('lexoffice_contact_number', 64)->nullable()->after('number');
        });

        $refs = DB::table('external_references')
            ->where('plugin_id', 'lexoffice')->where('external_type', 'contact')->where('referenceable_type', 'customers')
            ->whereNotNull('external_number')
            ->get(['referenceable_id', 'external_number']);
        foreach ($refs as $ref) {
            DB::table('customers')->where('id', $ref->referenceable_id)->update(['lexoffice_contact_number' => $ref->external_number]);
        }

        Schema::table('external_references', function (Blueprint $table): void {
            $table->dropIndex('extref_org_number_idx');
            $table->dropColumn('external_number');
        });
    }
};
