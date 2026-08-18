<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_01_10_120000_add_package_fields_to_gaeb_imports.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paketeingang (Feature 108, MVP-627).
 *
 * Vergabeunterlagen kommen als **Paket**, nicht als Einzeldatei: ein ZIP mit
 * Leistungsverzeichnis, Bewerbungsbedingungen, Plänen und Vordrucken. Damit
 * ein erkanntes GAEB-Dokument als *Vorschlag* liegen bleiben kann, bis jemand
 * es prüft, muss die Datei erhalten bleiben — der Import selbst passiert erst
 * auf Zuruf.
 *
 * `stored_path` zeigt auf die abgelegte Datei, `package_name` sagt, aus
 * welchem Paket sie stammt, `application_opportunity_id` an welchen
 * Vergabevorgang das Paket ging.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('gaeb_imports', function (Blueprint $table): void {
            $table->string('stored_path', 500)->nullable()->after('file_hash');
            $table->string('package_name', 300)->nullable()->after('stored_path');
            $table->foreignId('application_opportunity_id')->nullable()->after('bill_of_quantity_id')
                ->constrained('application_opportunities', indexName: 'gaebimp_opp_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('gaeb_imports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('application_opportunity_id');
            $table->dropColumn(['stored_path', 'package_name']);
        });
    }
};
