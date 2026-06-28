<?php
/*
 * Created on   : Sat Jun 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_27_110100_create_supplier_catalog_imports_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisionssicheres Import-Protokoll je Katalogquelle (Feature 050, MVP-091):
 * ein Lauf-Datensatz pro Import (manuell oder geplant) mit Bilanz, Status und
 * Fehlertext. Kurze, explizite FK-Namen wegen des MySQL-64-Zeichen-Limits.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('supplier_catalog_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'scimp_org_fk')->cascadeOnDelete();
            $table->foreignId('supplier_catalog_source_id')->constrained('supplier_catalog_sources', indexName: 'scimp_src_fk')->cascadeOnDelete();
            $table->string('trigger', 16)->default('manual');  // manual / scheduled
            $table->string('status', 16)->default('success');  // success / error
            $table->unsignedInteger('rows')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('unchanged')->default(0);
            $table->unsignedInteger('price_changed')->default(0);
            $table->unsignedInteger('discontinued')->default(0);
            $table->text('error')->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['supplier_catalog_source_id', 'created_at'], 'scimp_src_created_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('supplier_catalog_imports');
    }
};
